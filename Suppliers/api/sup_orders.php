<?php
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function jsonResponse($data, $httpCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function createSupplierOrderNotification(mysqli $conn, int $userId, string $message, string $type = 'surge'): void {
    if ($userId <= 0) return;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, `read`, created_at) VALUES (?, ?, ?, 0, NOW())");
    if (!$stmt) {
        error_log('Notification prepare failed: ' . $conn->error);
        return;
    }
    $stmt->bind_param('iss', $userId, $message, $type);
    if (!$stmt->execute()) {
        error_log('Notification insert failed: ' . $stmt->error);
    }
    $stmt->close();
}

function logSupplierOrderActivity(mysqli $conn, int $userId, string $message): void {
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $actionType = 'other';

    $stmt = $conn->prepare("
        INSERT INTO user_activity (user_id, action_type, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        error_log('Activity prepare failed: ' . $conn->error . ' | ' . $message);
        return;
    }
    $stmt->bind_param('isss', $userId, $actionType, $ipAddress, $userAgent);
    if (!$stmt->execute()) {
        error_log('Activity insert failed: ' . $stmt->error . ' | ' . $message);
    }
    $stmt->close();
}

$dbPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/config/db.php'
];
$conn = null;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
if (!$conn) {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    if ($conn->connect_error) {
        jsonResponse(['success' => false, 'message' => 'DB connection failed'], 500);
    }
}
$conn->set_charset('utf8mb4');
require_once __DIR__ . '/pagination_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supplier') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
if (!$stmt) jsonResponse(['success' => false, 'message' => 'Supplier lookup failed'], 500);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$supplierRow = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$supplierRow) {
    jsonResponse(['success' => false, 'message' => 'Supplier not found'], 404);
}
$supplier_id = (int)$supplierRow['id'];
$_SESSION['supplier_id'] = $supplier_id;

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $pagination = supGetPaginationParams($conn);
        $page = $pagination['page'];
        $limit = $pagination['limit'];
        $offset = $pagination['offset'];
        $status_filter = trim($_GET['status'] ?? '');

        $where = 'WHERE o.supplier_id = ?';
        $countParams = [$supplier_id];
        $countTypes = 'i';

        if ($status_filter === 'active') {
            $where .= " AND o.status NOT IN ('declined', 'fulfilled')";
        } elseif ($status_filter !== '') {
            $where .= ' AND (o.status = ? OR o.delivery_status = ?)';
            $countParams[] = $status_filter;
            $countParams[] = $status_filter;
            $countTypes .= 'ss';
        }

        $countSql = "
            SELECT COUNT(DISTINCT o.id) AS total
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            INNER JOIN medicines m ON oi.medicine_id = m.id
            $where
        ";
        $countStmt = $conn->prepare($countSql);
        if (!$countStmt) throw new Exception('Count prepare failed: ' . $conn->error);
        $countStmt->bind_param($countTypes, ...$countParams);
        $countStmt->execute();
        $totalItems = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();

        $listSql = "
            SELECT
                o.id,
                GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ') AS medicine_name,
                SUM(oi.quantity) AS quantity,
                NULL AS unit_price,
                SUM(oi.subtotal) AS total_cost,
                o.order_date, o.expected_delivery, o.actual_delivery, o.delivered_at,
                o.fulfilled_date, o.total_amount, o.status,
                COALESCE(o.payment_method, 'cash') as payment_method,
                o.delivery_status, o.delivery_updated_at, o.accepted_date, o.ordered_by
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            INNER JOIN medicines m ON oi.medicine_id = m.id
            $where
            GROUP BY o.id, o.order_date, o.expected_delivery, o.actual_delivery,
                     o.delivered_at, o.fulfilled_date, o.total_amount, o.status,
                     o.payment_method, o.delivery_status, o.delivery_updated_at,
                     o.accepted_date, o.ordered_by
            ORDER BY o.order_date DESC, o.id DESC
            LIMIT ? OFFSET ?
        ";
        $listParams = $countParams;
        $listTypes = $countTypes . 'ii';
        $listParams[] = $limit;
        $listParams[] = $offset;

        $stmt = $conn->prepare($listSql);
        if (!$stmt) throw new Exception('Orders prepare failed: ' . $conn->error);
        $stmt->bind_param($listTypes, ...$listParams);
        $stmt->execute();
        $result = $stmt->get_result();
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmt->close();

        jsonResponse([
            'success' => true,
            'data' => $orders,
            'pagination' => supBuildPaginationMeta($page, $limit, $totalItems)
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);
    if ($order_id <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid order ID'], 400);
    }

    $conn->begin_transaction();
    try {
        $detail_stmt = $conn->prepare("
            SELECT o.id, o.status, o.delivery_status, o.ordered_by
            FROM orders o
            WHERE o.id = ? AND o.supplier_id = ?
            FOR UPDATE
        ");
        if (!$detail_stmt) throw new Exception('Order detail prepare failed: ' . $conn->error);
        $detail_stmt->bind_param('ii', $order_id, $supplier_id);
        $detail_stmt->execute();
        $order = $detail_stmt->get_result()->fetch_assoc();
        $detail_stmt->close();

        if (!$order) throw new Exception('Order not found');
        $ordered_by = (int)($order['ordered_by'] ?? 0);
        $total_stmt = $conn->prepare("SELECT COALESCE(SUM(subtotal), 0) AS order_total FROM order_items WHERE order_id = ?");
        if (!$total_stmt) throw new Exception('Order total prepare failed: ' . $conn->error);
        $total_stmt->bind_param('i', $order_id);
        $total_stmt->execute();
        $total_row = $total_stmt->get_result()->fetch_assoc();
        $total_stmt->close();
        $order_total = (float)($total_row['order_total'] ?? 0);
        if ($order_total <= 0) throw new Exception('Invalid order total');

        if ($action === 'accept') {
            if ($order['status'] !== 'pending') throw new Exception('Only pending orders can be accepted');

            $update_stmt = $conn->prepare("
                UPDATE orders
                SET status = 'accepted',
                    delivery_status = 'accepted',
                    delivery_updated_at = NOW(),
                    accepted_date = NOW()
                WHERE id = ? AND supplier_id = ? AND status = 'pending'
            ");
            if (!$update_stmt) throw new Exception('Accept prepare failed: ' . $conn->error);
            $update_stmt->bind_param('ii', $order_id, $supplier_id);
            $update_stmt->execute();
            if ($update_stmt->affected_rows === 0) throw new Exception('Order not found or already processed');
            $update_stmt->close();

            createSupplierOrderNotification($conn, $user_id, "Order #{$order_id} has been accepted. Update delivery status as it progresses.", 'surge');
            createSupplierOrderNotification($conn, $ordered_by, "Supplier accepted Order #{$order_id}. Your order is being prepared.", 'surge');
            logSupplierOrderActivity($conn, $user_id, "Order #{$order_id} accepted");

            $invoice_check = $conn->prepare("SELECT id FROM sales_invoices WHERE order_id = ? LIMIT 1");
            if (!$invoice_check) throw new Exception('Invoice check prepare failed: ' . $conn->error);
            $invoice_check->bind_param('i', $order_id);
            $invoice_check->execute();
            $invoice_exists = $invoice_check->get_result()->fetch_assoc();
            $invoice_check->close();

            if (!$invoice_exists) {
                $customer_name = 'Admin Order';
                $customer_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
                if ($ordered_by) {
                    $customer_stmt->bind_param('i', $ordered_by);
                    $customer_stmt->execute();
                    $customer_row = $customer_stmt->get_result()->fetch_assoc();
                    if ($customer_row && !empty($customer_row['username'])) {
                        $customer_name = $customer_row['username'];
                    }
                }
                if ($customer_stmt) $customer_stmt->close();

                $items_stmt = $conn->prepare("
                    SELECT oi.medicine_id, oi.quantity, oi.unit_price,
                           COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost,
                           (oi.quantity * oi.unit_price) AS subtotal
                    FROM order_items oi
                    LEFT JOIN supplier_inventory si
                        ON si.supplier_id = ? AND si.medicine_id = oi.medicine_id
                    LEFT JOIN supplier_medicines sm
                        ON sm.supplier_id = ? AND sm.medicine_id = oi.medicine_id
                    WHERE oi.order_id = ?
                ");
                if (!$items_stmt) throw new Exception('Order items prepare failed: ' . $conn->error);
                $items_stmt->bind_param('iii', $supplier_id, $supplier_id, $order_id);
                $items_stmt->execute();
                $items_result = $items_stmt->get_result();
                $items = [];
                $invoice_total = 0.00;
                while ($item = $items_result->fetch_assoc()) {
                    $line_total = (float)($item['subtotal'] ?? 0);
                    $invoice_total += $line_total;
                    $items[] = $item;
                }
                $items_stmt->close();

                $invoice_number = 'SUP-' . date('Ymd') . '-' . str_pad((string)rand(1000, 9999), 4, '0');
                $invoice_stmt = $conn->prepare("
                    INSERT INTO sales_invoices
                        (invoice_number, order_id, customer_name, subtotal, discount, tax, total_amount,
                         amount_paid, change_given, payment_method, cashier_id, status, created_at)
                    VALUES (?, ?, ?, ?, 0, 0, ?, ?, 0, 'cash', ?, 'completed', NOW())
                ");
                if (!$invoice_stmt) throw new Exception('Supplier invoice prepare failed: ' . $conn->error);
                $invoice_stmt->bind_param(
                    'sisdddi',
                    $invoice_number,
                    $order_id,
                    $customer_name,
                    $invoice_total,
                    $invoice_total,
                    $invoice_total,
                    $user_id
                );
                if (!$invoice_stmt->execute()) throw new Exception('Supplier invoice insert failed: ' . $invoice_stmt->error);
                $invoice_id = $conn->insert_id;
                $invoice_stmt->close();

                foreach ($items as $item) {
                    $medicine_id = (int)($item['medicine_id'] ?? 0);
                    $quantity = (int)($item['quantity'] ?? 0);
                    $supplier_cost = (float)($item['supplier_cost'] ?? 0);
                    $selling_price = (float)($item['unit_price'] ?? 0);
                    $line_total = $quantity * $selling_price;
                    if ($medicine_id <= 0 || $quantity <= 0) continue;

                    $sale_stmt = $conn->prepare("
                        INSERT INTO supplier_sales
                            (order_id, supplier_id, invoice_id, medicine_id, quantity, unit_price, selling_price, line_total, total_amount, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if (!$sale_stmt) throw new Exception('Supplier sale prepare failed: ' . $conn->error);
                    $sale_stmt->bind_param('iiiiidddd', $order_id, $supplier_id, $invoice_id, $medicine_id, $quantity, $supplier_cost, $selling_price, $line_total, $line_total);
                    if (!$sale_stmt->execute()) throw new Exception('Supplier sale insert failed: ' . $sale_stmt->error);
                    $sale_stmt->close();
                }
            }

            $conn->commit();
            jsonResponse(['success' => true, 'message' => "Order #{$order_id} accepted."]);
        }

        if ($action === 'decline') {
            if ($order['status'] !== 'pending') throw new Exception('Only pending orders can be declined');

            $update_stmt = $conn->prepare("
                UPDATE orders
                SET status = 'declined',
                    delivery_status = NULL,
                    delivery_updated_at = NOW()
                WHERE id = ? AND supplier_id = ? AND status = 'pending'
            ");
            if (!$update_stmt) throw new Exception('Decline prepare failed: ' . $conn->error);
            $update_stmt->bind_param('ii', $order_id, $supplier_id);
            $update_stmt->execute();
            if ($update_stmt->affected_rows === 0) throw new Exception('Order not found or already processed');
            $update_stmt->close();

            createSupplierOrderNotification($conn, $ordered_by, "Supplier declined Order #{$order_id}.", 'surge');
            logSupplierOrderActivity($conn, $user_id, "Order #{$order_id} declined");

            $conn->commit();
            jsonResponse(['success' => true, 'message' => "Order #{$order_id} declined."]);
        }

        if ($action === 'update_delivery' || $action === 'deliver') {
            if ($order['status'] !== 'accepted') throw new Exception('Only accepted orders can be updated');

            $new_delivery_status = trim($_POST['delivery_status'] ?? '');
            $allowed = ['shipped', 'out_for_delivery', 'delivered'];
            if ($action === 'deliver') $new_delivery_status = 'delivered';
            if (!in_array($new_delivery_status, $allowed, true)) {
                throw new Exception('Invalid delivery status: ' . $new_delivery_status);
            }

            $rank = [
                'accepted' => 0,
                'shipped' => 1,
                'out_for_delivery' => 2,
                'delivered' => 3
            ];
            $current = $order['delivery_status'] ?: 'accepted';
            if (($rank[$new_delivery_status] ?? -1) <= ($rank[$current] ?? 0)) {
                throw new Exception('Delivery status cannot move backward or repeat');
            }

            $update_stmt = $conn->prepare("
                UPDATE orders
                SET delivery_status = ?,
                    delivery_updated_at = NOW(),
                    delivered_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE delivered_at END
                WHERE id = ? AND supplier_id = ? AND status = 'accepted'
            ");
            if (!$update_stmt) throw new Exception('Delivery update prepare failed: ' . $conn->error);
            $update_stmt->bind_param('ssii', $new_delivery_status, $new_delivery_status, $order_id, $supplier_id);
            $update_stmt->execute();
            if ($update_stmt->affected_rows === 0) throw new Exception('Order not found or not accepted');
            $update_stmt->close();

            $label = strtoupper(str_replace('_', ' ', $new_delivery_status));
            $message = $new_delivery_status === 'delivered'
                ? "Order #{$order_id} marked delivered. Waiting for admin confirmation."
                : "Order #{$order_id} is now {$label}.";
            createSupplierOrderNotification($conn, $ordered_by, $message, 'surge');
            logSupplierOrderActivity($conn, $user_id, "Order #{$order_id} delivery status updated to {$new_delivery_status}");

            $conn->commit();
            jsonResponse(['success' => true, 'message' => $message]);
        }

        throw new Exception('Invalid action specified');
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
} catch (Exception $e) {
    error_log("Supplier orders error: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
} finally {
    if ($conn) $conn->close();
}
?>
