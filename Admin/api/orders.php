<?php
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function jsonResponse($data, $httpCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function makeInvoiceNumber(mysqli $conn): string {
    do {
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT id FROM sales_invoices WHERE invoice_number = ? LIMIT 1");
        if (!$stmt) throw new Exception('Invoice check prepare failed: ' . $conn->error);
        $stmt->bind_param('s', $invoiceNumber);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $invoiceNumber;
}

function createOrderNotification(mysqli $conn, int $userId, string $message): void {
    if ($userId <= 0) return;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, `read`, created_at) VALUES (?, ?, 'surge', 0, NOW())");
    if (!$stmt) {
        error_log('Order notification prepare failed: ' . $conn->error);
        return;
    }
    $stmt->bind_param('is', $userId, $message);
    if (!$stmt->execute()) {
        error_log('Order notification insert failed: ' . $stmt->error);
    }
    $stmt->close();
}

function ensureSupplierPreferenceColumn(mysqli $conn): void {
    $check = $conn->query("SHOW COLUMNS FROM suppliers LIKE 'is_preferred'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE suppliers ADD COLUMN is_preferred TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id");
    }
    if ($check) $check->close();
}

/**
 * Business/validation errors are the expected, user-actionable kind
 * (bad stock, missing fields, order already responded to, etc). They
 * are NOT server failures and must never be sent back as HTTP 500 —
 * doing so causes well-behaved frontend code that checks `response.ok`
 * to treat them as generic network errors and discard the real
 * message. Always send these as 400 so the message reaches the user.
 */
function jsonError(string $message, int $httpCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $httpCode);
}

$dbPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../Config/db.php'
];
$conn = null;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
if (!$conn) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed. Check logs.'], 500);
}
$conn->set_charset('utf8mb4');
ensureSupplierPreferenceColumn($conn);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Unauthorized - Admin only'], 401);
}

$user_id = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

foreach ([__DIR__ . '/../includes/activity_logger.php', __DIR__ . '/includes/activity_logger.php'] as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            $status_filter = trim($_GET['status'] ?? '');
            $where = $status_filter ? "WHERE (o.status = ? OR o.delivery_status = ?)" : '';

            $sql = "SELECT
                        o.id as order_id, o.supplier_id, o.total_amount, o.status, o.order_date,
                        o.expected_delivery, o.actual_delivery, o.delivered_at, o.fulfilled_date, o.notes,
                        COALESCE(o.payment_method, 'cash') as payment_method,
                        o.delivery_status, o.delivery_updated_at,
                        s.name as supplier_name,
                        COALESCE(oi.medicine_id, NULLIF(o.medicine_id, 0)) AS medicine_id,
                        m.name as medicine_name,
                        m.item_type,
                        COALESCE(oi.quantity, NULLIF(o.quantity, 0)) AS quantity,
                        COALESCE(oi.unit_price, NULLIF(o.unit_price, 0)) AS unit_price,
                        COALESCE(oi.subtotal, NULLIF(o.total_amount, 0), o.total_cost) AS subtotal,
                        CASE
                            WHEN COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) > 0
                            THEN ROUND(
                                COALESCE(oi.quantity, NULLIF(o.quantity, 0)) *
                                ROUND(
                                    COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                                    (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100),
                                    2
                                ),
                                2
                            )
                            ELSE COALESCE(oi.subtotal, NULLIF(o.total_amount, 0), o.total_cost)
                        END AS display_total
                    FROM orders o
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    LEFT JOIN medicines m ON COALESCE(oi.medicine_id, NULLIF(o.medicine_id, 0)) = m.id
                    LEFT JOIN suppliers s ON o.supplier_id = s.id
                    LEFT JOIN supplier_inventory si ON si.supplier_id = o.supplier_id AND si.medicine_id = m.id
                    LEFT JOIN supplier_medicines sm ON sm.supplier_id = o.supplier_id AND sm.medicine_id = m.id
                    $where
                    ORDER BY o.order_date DESC";

            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            if ($status_filter) {
                $stmt->bind_param('ss', $status_filter, $status_filter);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $orders = [];
            while ($row = $result->fetch_assoc()) $orders[] = $row;
            $stmt->close();

            jsonResponse(['success' => true, 'data' => $orders, 'count' => count($orders)]);
        }

        if ($action === 'suppliers') {
            $result = $conn->query("SELECT id, CONCAT(company, ' - ', name) as company_name, contact, is_preferred FROM suppliers ORDER BY is_preferred DESC, company ASC");
            if (!$result) throw new Exception('Query failed: ' . $conn->error);
            $suppliers = [];
            while ($row = $result->fetch_assoc()) $suppliers[] = $row;
            jsonResponse(['success' => true, 'data' => $suppliers, 'count' => count($suppliers)]);
        }

        if ($action === 'get_medicines') {
            $supplier_id = (int)($_GET['supplier_id'] ?? 0);
            if ($supplier_id <= 0) jsonError('Invalid supplier ID', 400);

            $stmt = $conn->prepare("
                SELECT
                    m.id,
                    m.name,
                    m.category AS category,
                    m.item_type AS item_type,
                    COALESCE(si.quantity, sm.quantity_supplied, 0) AS supplier_stock,
                    COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost,
                    ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                        (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS supplier_price,
                    COALESCE(NULLIF(sm.min_order_quantity, 0), 1) AS min_order_quantity,
                    1 AS can_order
                FROM (
                    SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ?
                    UNION
                    SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ?
                ) owned
                INNER JOIN medicines m ON owned.medicine_id = m.id
                LEFT JOIN supplier_inventory si
                    ON si.supplier_id = ?
                   AND si.medicine_id = owned.medicine_id
                LEFT JOIN supplier_medicines sm
                    ON sm.supplier_id = ?
                   AND sm.medicine_id = owned.medicine_id
                WHERE COALESCE(si.quantity, sm.quantity_supplied, 0) > 0
                  AND COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) > 0
                  AND COALESCE(si.quantity, sm.quantity_supplied, 0) >= COALESCE(NULLIF(sm.min_order_quantity, 0), 1)
                ORDER BY m.name ASC
            ");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param('iiii', $supplier_id, $supplier_id, $supplier_id, $supplier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $medicines = [];
            while ($row = $result->fetch_assoc()) $medicines[] = $row;
            $stmt->close();

            jsonResponse(['success' => true, 'data' => $medicines, 'count' => count($medicines)]);
        }

        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

    if ($method !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $expected_delivery = $_POST['expected_delivery'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? 'cash');
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $medicines = json_decode($_POST['medicines'] ?? '[]', true);

        $allowed_payments = ['cash', 'gcash', 'bank_transfer'];
        if (!in_array($payment_method, $allowed_payments, true)) $payment_method = 'cash';
        if ($payment_method === 'cash') $payment_reference = '';
        if ($supplier_id <= 0 || empty($medicines) || !is_array($medicines) || empty($expected_delivery)) {
            jsonError('Invalid input: Missing supplier, medicines, or delivery date', 400);
        }
        if ($payment_method !== 'cash' && $payment_reference === '') {
            jsonError('Please provide the payment reference/account details for this payment method', 400);
        }

        foreach ($medicines as $index => $med) {
            $mid = (int)($med['medicine_id'] ?? 0);
            $qty = (int)($med['quantity'] ?? 0);
            $up  = (float)($med['unit_price'] ?? 0);
            if ($mid <= 0 || $qty <= 0 || $up <= 0) {
                jsonError('Invalid medicine item at row ' . ($index + 1) . ': medicine, quantity, and price are required', 400);
            }
        }

        $conn->begin_transaction();
        try {
            foreach ($medicines as $index => $med) {
                $medicine_id = (int)$med['medicine_id'];
                $quantity = (int)$med['quantity'];
                $checkStmt = $conn->prepare("
                    SELECT
                        COALESCE(si.quantity, sm.quantity_supplied, 0) AS quantity,
                        COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost,
                        ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                            (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS unit_price
                    FROM (
                        SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ? AND medicine_id = ?
                        UNION
                        SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ? AND medicine_id = ?
                    ) owned
                    INNER JOIN medicines m ON m.id = owned.medicine_id
                    LEFT JOIN supplier_inventory si
                        ON si.supplier_id = ?
                       AND si.medicine_id = owned.medicine_id
                    LEFT JOIN supplier_medicines sm
                        ON sm.supplier_id = ?
                       AND sm.medicine_id = owned.medicine_id
                    WHERE COALESCE(si.quantity, sm.quantity_supplied, 0) > 0
                      AND COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) > 0
                ");
                if (!$checkStmt) throw new Exception('Stock check prepare failed: ' . $conn->error);
                $checkStmt->bind_param('iiiiii', $supplier_id, $medicine_id, $supplier_id, $medicine_id, $supplier_id, $supplier_id);
                $checkStmt->execute();
                $stockRow = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();
                if (!$stockRow) throw new Exception('Medicine not available from this supplier');
                if ($quantity > (int)$stockRow['quantity']) throw new Exception('Requested quantity exceeds supplier stock');
                $medicines[$index]['unit_price'] = (float)$stockRow['unit_price'];
            }

            $total_amount = 0.00;
            foreach ($medicines as $index => $med) {
                $total_amount += (int)$med['quantity'] * (float)$med['unit_price'];
            }

            $first_medicine_id = (int)$medicines[0]['medicine_id'];
            $first_quantity = (int)$medicines[0]['quantity'];
            $first_unit_price = (float)$medicines[0]['unit_price'];
            $stmt = $conn->prepare("
                INSERT INTO orders
                    (supplier_id, medicine_id, quantity, unit_price, total_amount, status, order_date, expected_delivery, notes, payment_method, payment_reference, ordered_by)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?, ?, ?)
            ");
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param('iiiddssssi', $supplier_id, $first_medicine_id, $first_quantity, $first_unit_price, $total_amount, $expected_delivery, $notes, $payment_method, $payment_reference, $user_id);
            if (!$stmt->execute()) throw new Exception('Insert order failed: ' . $stmt->error);
            $order_id = $conn->insert_id;
            $stmt->close();

            foreach ($medicines as $index => $med) {
                $medicine_id = (int)$med['medicine_id'];
                $quantity = (int)$med['quantity'];
                $unit_price = (float)$med['unit_price'];
                $subtotal = $quantity * $unit_price;
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                if (!$item_stmt) throw new Exception('Prepare item failed: ' . $conn->error);
                $item_stmt->bind_param('iiidd', $order_id, $medicine_id, $quantity, $unit_price, $subtotal);
                if (!$item_stmt->execute()) throw new Exception('Insert item failed: ' . $item_stmt->error);
                $item_stmt->close();
            }

            $conn->commit();

            // Look up the supplier's user account so they're actually notified —
            // previously this referenced $ord, which doesn't exist in this branch,
            // so the notification silently went to user_id 0 and never sent.
            $supplier_lookup = $conn->prepare("SELECT user_id FROM suppliers WHERE id = ?");
            if ($supplier_lookup) {
                $supplier_lookup->bind_param('i', $supplier_id);
                $supplier_lookup->execute();
                $supplier_row = $supplier_lookup->get_result()->fetch_assoc();
                $supplier_lookup->close();
                if ($supplier_row && (int)$supplier_row['user_id'] > 0) {
                    createOrderNotification($conn, (int)$supplier_row['user_id'], "New Order #{$order_id} placed — please review and accept.");
                }
            }

            if (function_exists('logUserActivity')) {
                logUserActivity($user_id, 'create_order', ['order_id' => $order_id, 'supplier_id' => $supplier_id]);
            }
            jsonResponse(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $order_id, 'total' => $total_amount]);
        } catch (Exception $e) {
            $conn->rollback();
            // Expected, user-actionable failure (bad stock/price/input) — NOT a
            // server error. Sending this as 500 makes the frontend's `!r.ok`
            // check throw before it ever reads this message. Use 400 instead.
            jsonError($e->getMessage(), 400);
        }
    }

    if ($action === 'cancel_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        if ($order_id <= 0) jsonError('Invalid order ID', 400);

        $conn->begin_transaction();
        try {
            $ord_stmt = $conn->prepare("SELECT id, status, supplier_id FROM orders WHERE id = ? FOR UPDATE");
            if (!$ord_stmt) throw new Exception('Order fetch prepare failed: ' . $conn->error);
            $ord_stmt->bind_param('i', $order_id);
            $ord_stmt->execute();
            $ord = $ord_stmt->get_result()->fetch_assoc();
            $ord_stmt->close();

            if (!$ord) throw new Exception('Order not found');
            // Admin can only cancel while the supplier hasn't acted on it yet.
            if (!in_array($ord['status'], ['pending', 'ordered'], true)) {
                throw new Exception('This order can no longer be cancelled — the supplier has already responded to it.');
            }

            $upd_stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status IN ('pending', 'ordered')");
            if (!$upd_stmt) throw new Exception('Cancel prepare failed: ' . $conn->error);
            $upd_stmt->bind_param('i', $order_id);
            $upd_stmt->execute();
            if ($upd_stmt->affected_rows === 0) throw new Exception('Order could not be cancelled');
            $upd_stmt->close();

            $conn->commit();
            if (function_exists('logUserActivity')) {
                logUserActivity($user_id, 'cancel_order', ['order_id' => $order_id]);
            }
            jsonResponse(['success' => true, 'message' => "Order #{$order_id} cancelled.", 'order_id' => $order_id]);
        } catch (Exception $e) {
            $conn->rollback();
            jsonError($e->getMessage(), 400);
        }
    }

    if ($action === 'edit_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $expected_delivery = $_POST['expected_delivery'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? 'cash');
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $medicines = json_decode($_POST['medicines'] ?? '[]', true);

        $allowed_payments = ['cash', 'gcash', 'bank_transfer'];
        if (!in_array($payment_method, $allowed_payments, true)) $payment_method = 'cash';
        if ($payment_method === 'cash') $payment_reference = '';

        if ($order_id <= 0 || empty($medicines) || !is_array($medicines) || empty($expected_delivery)) {
            jsonError('Invalid input: Missing order, medicines, or delivery date', 400);
        }
        if ($payment_method !== 'cash' && $payment_reference === '') {
            jsonError('Please provide the payment reference/account details for this payment method', 400);
        }
        foreach ($medicines as $med) {
            if (empty($med['medicine_id']) || empty($med['quantity']) || empty($med['unit_price']) || $med['quantity'] <= 0 || $med['unit_price'] <= 0) {
                jsonError('Invalid medicine item', 400);
            }
        }

        $conn->begin_transaction();
        try {
            $ord_stmt = $conn->prepare("SELECT id, status, supplier_id FROM orders WHERE id = ? FOR UPDATE");
            if (!$ord_stmt) throw new Exception('Order fetch prepare failed: ' . $conn->error);
            $ord_stmt->bind_param('i', $order_id);
            $ord_stmt->execute();
            $ord = $ord_stmt->get_result()->fetch_assoc();
            $ord_stmt->close();

            if (!$ord) throw new Exception('Order not found');
            // Admin can only edit while the supplier hasn't responded yet — once accepted/declined,
            // the order is out of admin's hands.
            if (!in_array($ord['status'], ['pending', 'ordered'], true)) {
                throw new Exception('This order can no longer be edited — the supplier has already responded to it.');
            }
            $supplier_id = (int)$ord['supplier_id'];

            foreach ($medicines as $index => $med) {
                $medicine_id = (int)$med['medicine_id'];
                $quantity = (int)$med['quantity'];
                $checkStmt = $conn->prepare("
                    SELECT
                        COALESCE(si.quantity, sm.quantity_supplied, 0) AS quantity,
                        COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost,
                        ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                            (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS unit_price
                    FROM (
                        SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ? AND medicine_id = ?
                        UNION
                        SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ? AND medicine_id = ?
                    ) owned
                    INNER JOIN medicines m ON m.id = owned.medicine_id
                    LEFT JOIN supplier_inventory si
                        ON si.supplier_id = ?
                       AND si.medicine_id = owned.medicine_id
                    LEFT JOIN supplier_medicines sm
                        ON sm.supplier_id = ?
                       AND sm.medicine_id = owned.medicine_id
                    WHERE COALESCE(si.quantity, sm.quantity_supplied, 0) > 0
                      AND COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) > 0
                ");
                if (!$checkStmt) throw new Exception('Stock check prepare failed: ' . $conn->error);
                $checkStmt->bind_param('iiiiii', $supplier_id, $medicine_id, $supplier_id, $medicine_id, $supplier_id, $supplier_id);
                $checkStmt->execute();
                $stockRow = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();
                if (!$stockRow) throw new Exception('Medicine not available from this supplier');
                if ($quantity > (int)$stockRow['quantity']) throw new Exception('Requested quantity exceeds supplier stock');
                $medicines[$index]['unit_price'] = (float)$stockRow['unit_price'];
            }

            $total_amount = 0.00;
            foreach ($medicines as $med) {
                $total_amount += (int)$med['quantity'] * (float)$med['unit_price'];
            }

            $first_medicine_id = (int)$medicines[0]['medicine_id'];
            $first_quantity = (int)$medicines[0]['quantity'];
            $first_unit_price = (float)$medicines[0]['unit_price'];

            $upd_stmt = $conn->prepare("
                UPDATE orders
                SET medicine_id = ?, quantity = ?, unit_price = ?, total_amount = ?,
                    expected_delivery = ?, notes = ?, payment_method = ?, payment_reference = ?
                WHERE id = ? AND status IN ('pending', 'ordered')
            ");
            if (!$upd_stmt) throw new Exception('Order update prepare failed: ' . $conn->error);
            $upd_stmt->bind_param('iiddssssi', $first_medicine_id, $first_quantity, $first_unit_price, $total_amount, $expected_delivery, $notes, $payment_method, $payment_reference, $order_id);
            $upd_stmt->execute();
            if ($upd_stmt->affected_rows === 0 && $conn->error) throw new Exception('Order update failed: ' . $conn->error);
            $upd_stmt->close();

            $del_stmt = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
            if (!$del_stmt) throw new Exception('Item clear prepare failed: ' . $conn->error);
            $del_stmt->bind_param('i', $order_id);
            $del_stmt->execute();
            $del_stmt->close();

            foreach ($medicines as $med) {
                $medicine_id = (int)$med['medicine_id'];
                $quantity = (int)$med['quantity'];
                $unit_price = (float)$med['unit_price'];
                $subtotal = $quantity * $unit_price;
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                if (!$item_stmt) throw new Exception('Prepare item failed: ' . $conn->error);
                $item_stmt->bind_param('iiidd', $order_id, $medicine_id, $quantity, $unit_price, $subtotal);
                if (!$item_stmt->execute()) throw new Exception('Insert item failed: ' . $item_stmt->error);
                $item_stmt->close();
            }

            $conn->commit();
            if (function_exists('logUserActivity')) {
                logUserActivity($user_id, 'edit_order', ['order_id' => $order_id]);
            }
            jsonResponse(['success' => true, 'message' => "Order #{$order_id} updated.", 'order_id' => $order_id, 'total' => $total_amount]);
        } catch (Exception $e) {
            $conn->rollback();
            jsonError($e->getMessage(), 400);
        }
    }

    if ($action === 'get_order') {
        $order_id = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
        if ($order_id <= 0) jsonError('Invalid order ID', 400);

        $ord_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        if (!$ord_stmt) throw new Exception('Order fetch prepare failed: ' . $conn->error);
        $ord_stmt->bind_param('i', $order_id);
        $ord_stmt->execute();
        $ord = $ord_stmt->get_result()->fetch_assoc();
        $ord_stmt->close();
        if (!$ord) jsonResponse(['success' => false, 'message' => 'Order not found'], 404);

        $items_stmt = $conn->prepare("SELECT oi.*, m.name AS medicine_name, m.item_type FROM order_items oi JOIN medicines m ON m.id = oi.medicine_id WHERE oi.order_id = ?");
        $items_stmt->bind_param('i', $order_id);
        $items_stmt->execute();
        $items = [];
        $res = $items_stmt->get_result();
        while ($row = $res->fetch_assoc()) $items[] = $row;
        $items_stmt->close();

        $ord['items'] = $items;
        jsonResponse(['success' => true, 'data' => $ord]);
    }

    if ($action === 'mark_delivered') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        if ($order_id <= 0) jsonError('Invalid order ID', 400);

        $conn->begin_transaction();
        try {
            $ord_stmt = $conn->prepare("
                SELECT o.id, o.status, o.delivery_status, o.supplier_id, o.total_amount, o.ordered_by,
                       COALESCE(o.payment_method, 'cash') as payment_method,
                       s.user_id AS supplier_user_id,
                       COALESCE(u.username, 'Admin Order') AS customer_name
                FROM orders o
                LEFT JOIN suppliers s ON s.id = o.supplier_id
                LEFT JOIN users u ON u.id = o.ordered_by
                WHERE o.id = ?
                FOR UPDATE
            ");
            if (!$ord_stmt) throw new Exception('Order fetch prepare failed: ' . $conn->error);
            $ord_stmt->bind_param('i', $order_id);
            $ord_stmt->execute();
            $ord = $ord_stmt->get_result()->fetch_assoc();
            $ord_stmt->close();

            if (!$ord) throw new Exception('Order not found');
            if ($ord['status'] === 'fulfilled') throw new Exception('Order has already been finalized');
            if ($ord['status'] !== 'accepted' || $ord['delivery_status'] !== 'delivered') {
                throw new Exception('The supplier must mark this accepted order as delivered before admin confirmation');
            }

            $invoice_check = $conn->prepare("SELECT id, invoice_number FROM sales_invoices WHERE order_id = ? LIMIT 1");
            if (!$invoice_check) throw new Exception('Invoice check prepare failed: ' . $conn->error);
            $invoice_check->bind_param('i', $order_id);
            $invoice_check->execute();
            $existing_invoice = $invoice_check->get_result()->fetch_assoc();
            $invoice_check->close();
            $has_existing_invoice = (bool)$existing_invoice;
            $invoice_id = $has_existing_invoice ? (int)$existing_invoice['id'] : 0;
            $invoice_number = $has_existing_invoice ? $existing_invoice['invoice_number'] : '';

            $items_stmt = $conn->prepare("
                  SELECT oi.medicine_id, oi.quantity, oi.unit_price, oi.subtotal, m.name AS medicine_name,
                      COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost
                FROM order_items oi
                INNER JOIN medicines m ON m.id = oi.medicine_id
                     LEFT JOIN supplier_inventory si
                          ON si.supplier_id = ?
                         AND si.medicine_id = oi.medicine_id
                LEFT JOIN supplier_medicines sm
                    ON sm.supplier_id = ?
                   AND sm.medicine_id = oi.medicine_id
                WHERE oi.order_id = ?
                FOR UPDATE
            ");
            if (!$items_stmt) throw new Exception('Items fetch prepare failed: ' . $conn->error);
            $items_stmt->bind_param('iii', $ord['supplier_id'], $ord['supplier_id'], $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            $items_stmt->close();
            if (!$items) throw new Exception('No order items found');

            $order_total = 0.00;
            foreach ($items as $item) $order_total += (float)$item['subtotal'];

            if (!$has_existing_invoice) {
                $invoice_number = makeInvoiceNumber($conn);
                $discount = 0.00;
                $tax = 0.00;
                $amount_paid = $order_total;
                $change_given = 0.00;
                $customer_name = $ord['customer_name'] ?: 'Admin Order';
                $invoice_stmt = $conn->prepare(" 
                INSERT INTO sales_invoices
                    (invoice_number, order_id, customer_name, subtotal, discount, tax, total_amount,
                     amount_paid, change_given, payment_method, cashier_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                if (!$invoice_stmt) throw new Exception('Invoice prepare failed: ' . $conn->error);
                $invoice_stmt->bind_param(
                'sisddddddsi',
                $invoice_number, $order_id, $customer_name,
                $order_total, $discount, $tax, $order_total,
                $amount_paid, $change_given, $ord['payment_method'], $user_id
                );
                if (!$invoice_stmt->execute()) throw new Exception('Invoice insert failed: ' . $invoice_stmt->error);
                $invoice_id = $conn->insert_id;
                $invoice_stmt->close();
            }

            foreach ($items as $item) {
                $medicine_id = (int)$item['medicine_id'];
                $quantity = (int)$item['quantity'];
                $unit_price = (float)$item['unit_price'];
                $line_total = $quantity * $unit_price;
                $supplier_cost = (float)$item['supplier_cost'];

                $deduct_stmt = $conn->prepare("UPDATE supplier_inventory SET quantity = GREATEST(quantity - ?, 0) WHERE supplier_id = ? AND medicine_id = ?");
                if (!$deduct_stmt) throw new Exception('Supplier inventory prepare failed: ' . $conn->error);
                $deduct_stmt->bind_param('iii', $quantity, $ord['supplier_id'], $medicine_id);
                $deduct_stmt->execute();
                $deduct_stmt->close();

                $add_stmt = $conn->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
                if (!$add_stmt) throw new Exception('Admin inventory prepare failed: ' . $conn->error);
                $add_stmt->bind_param('ii', $quantity, $medicine_id);
                if (!$add_stmt->execute()) throw new Exception('Admin inventory update failed: ' . $add_stmt->error);
                $add_stmt->close();

                if (!$has_existing_invoice) {
                    $sale_stmt = $conn->prepare(" 
                    INSERT INTO supplier_sales
                        (order_id, supplier_id, invoice_id, medicine_id, quantity, unit_price, selling_price, line_total, total_amount)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    if (!$sale_stmt) throw new Exception('Supplier sale prepare failed: ' . $conn->error);
                    $sale_stmt->bind_param('iiiiidddd', $order_id, $ord['supplier_id'], $invoice_id, $medicine_id, $quantity, $supplier_cost, $unit_price, $line_total, $line_total);
                    if (!$sale_stmt->execute()) throw new Exception('Supplier sale insert failed: ' . $sale_stmt->error);
                    $sale_stmt->close();
                }

                $supplier_user_id = (int)($ord['supplier_user_id'] ?? 0);
                if ($supplier_user_id <= 0) {
                    $supplier_user_id = $user_id;
                }
                $supplier_notes = "Order #{$order_id} delivered - Invoice: {$invoice_number}";
                $supplier_txn_stmt = $conn->prepare("
                    INSERT INTO supplier_transactions
                        (user_id, supplier_id, medicine_id, action, transaction_type, quantity, total_cost, line_total, notes, timestamp, order_id, invoice_number)
                    VALUES (?, ?, ?, 'remove', 'sale', ?, ?, ?, ?, NOW(), ?, ?)
                ");
                if (!$supplier_txn_stmt) throw new Exception('Supplier transaction prepare failed: ' . $conn->error);
                $supplier_txn_stmt->bind_param('iiiiddsis', $supplier_user_id, $ord['supplier_id'], $medicine_id, $quantity, $line_total, $line_total, $supplier_notes, $order_id, $invoice_number);
                if (!$supplier_txn_stmt->execute()) throw new Exception('Supplier transaction insert failed: ' . $supplier_txn_stmt->error);
                $supplier_txn_stmt->close();

                $txn_notes = "Order #{$order_id} received - Invoice: {$invoice_number} - Payment: {$ord['payment_method']}";
                $txn_stmt = $conn->prepare("
                    INSERT INTO transactions
                        (medicine_id, action, quantity, user_id, notes, reason, total_cost, timestamp)
                    VALUES (?, 'add', ?, ?, ?, 'order_delivery', ?, NOW())
                ");
                if (!$txn_stmt) throw new Exception('Admin transaction prepare failed: ' . $conn->error);
                $txn_stmt->bind_param('iiisd', $medicine_id, $quantity, $user_id, $txn_notes, $line_total);
                if (!$txn_stmt->execute()) throw new Exception('Admin transaction insert failed: ' . $txn_stmt->error);
                $txn_stmt->close();
            }

            $upd_stmt = $conn->prepare("
                UPDATE orders
                SET status = 'fulfilled',
                    delivery_status = 'delivered',
                    delivery_updated_at = NOW(),
                    actual_delivery = CURDATE(),
                    delivered_at = NOW(),
                    fulfilled_date = NOW(),
                    paid_date = NOW(),
                    total_amount = ?
                WHERE id = ? AND status = 'accepted'
            ");
            if (!$upd_stmt) throw new Exception('Order final update prepare failed: ' . $conn->error);
            $upd_stmt->bind_param('di', $order_total, $order_id);
            $upd_stmt->execute();
            if ($upd_stmt->affected_rows === 0) throw new Exception('Order final update failed');
            $upd_stmt->close();

            $conn->commit();
            if (function_exists('logUserActivity')) {
                logUserActivity($user_id, 'mark_order_delivered', ['order_id' => $order_id]);
            }
            jsonResponse([
                'success' => true,
                'message' => "Order #{$order_id} confirmed received. Inventory and transactions were recorded.",
                'order_id' => $order_id,
                'invoice_number' => $invoice_number
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            jsonError($e->getMessage(), 400);
        }
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Exception $e) {
    error_log("Orders API error: " . $e->getMessage());
    // Anything caught out here is a genuine unexpected failure (bad SQL,
    // missing table/column, etc) rather than a validation error, so 500
    // is correct — the message is safe to show since it's already
    // logged, and the frontend now reads the body either way.
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
} finally {
    if ($conn) $conn->close();
}
?>