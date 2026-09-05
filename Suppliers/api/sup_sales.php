<?php
// Ensure no output or whitespace before this point
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'med_inventory');
if ($conn->connect_error) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database connection failed']]);
    exit();
}
$conn->set_charset("utf8mb4");

require_once __DIR__ . '/../includes/supplier_auth.php';
$supplierProfile = requireSupplierSession($conn);
$supplier_id = (int)$supplierProfile['id'];

$response = ['success' => false, 'errors' => [], 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $cashier_id = $_SESSION['user_id'];

        if ($action === 'create_sale') {
            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            $medicines = isset($_POST['medicines']) ? json_decode($_POST['medicines'], true) : [];
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $amount_paid = floatval($_POST['amount_paid'] ?? 0);
            $discount = floatval($_POST['discount'] ?? 0);
            $tax = floatval($_POST['tax'] ?? 0);

            if (empty($medicines) || !is_array($medicines)) {
                $response['errors'][] = 'At least one medicine is required.';
            }
            if ($amount_paid < 0) {
                $response['errors'][] = 'Amount paid cannot be negative.';
            }
            if ($discount < 0 || $discount > 100) {
                $response['errors'][] = 'Discount must be between 0 and 100%.';
            }

            if (empty($response['errors'])) {
                $conn->begin_transaction();
                try {
                    $subtotal = 0;
                    $validated_medicines = [];

                    // If order_id is provided, verify it's an accepted order for this supplier and auto-populate if needed
                    if ($order_id > 0) {
                        $order_check = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND supplier_id = ? AND status = 'accepted'");
                        $order_check->bind_param("ii", $order_id, $supplier_id);
                        $order_check->execute();
                        $order_result = $order_check->get_result();
                        
                        if ($order_result->num_rows === 0) {
                            throw new Exception('Invalid or unaccepted order.');
                        }
                        $order_check->close();

                        // If no medicines provided, auto-fetch from order_items
                        if (empty($medicines)) {
                            $order_items_stmt = $conn->prepare("
                                SELECT oi.medicine_id, oi.quantity, oi.unit_price
                                FROM order_items oi
                                WHERE oi.order_id = ?
                            ");
                            $order_items_stmt->bind_param("i", $order_id);
                            $order_items_stmt->execute();
                            $items_result = $order_items_stmt->get_result();
                            while ($item = $items_result->fetch_assoc()) {
                                $medicines[] = [
                                    'medicine_id' => (int)$item['medicine_id'],
                                    'quantity' => (int)$item['quantity'],
                                    'selling_price' => floatval($item['unit_price'])
                                ];
                            }
                            $order_items_stmt->close();
                            if (empty($medicines)) {
                                throw new Exception('No items found in the selected order.');
                            }
                        }
                    }

                    // Validate medicines and check SUPPLIER inventory
                    foreach ($medicines as $med) {
                        $medicine_id = intval($med['medicine_id']);
                        $quantity = intval($med['quantity']);
                        $selling_price = floatval($med['selling_price']);

                        if ($medicine_id <= 0 || $quantity <= 0 || $selling_price < 0) {
                            throw new Exception('Invalid medicine data.');
                        }

                        // Check SUPPLIER inventory
                        $stmt = $conn->prepare("
                            SELECT si.quantity,
                                COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS unit_price,
                                m.name,
                                m.selling_price as default_selling_price
                            FROM supplier_inventory si
                            INNER JOIN medicines m ON si.medicine_id = m.id
                            LEFT JOIN supplier_medicines sm ON si.medicine_id = sm.medicine_id AND si.supplier_id = sm.supplier_id
                            WHERE si.medicine_id = ? AND si.supplier_id = ?
                        ");
                        $stmt->bind_param("ii", $medicine_id, $supplier_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $medicine = $result->fetch_assoc();
                        $stmt->close();

                        if (!$medicine) {
                            throw new Exception("Medicine not found in your inventory.");
                        }

                        if ($medicine['quantity'] < $quantity) {
                            throw new Exception("Insufficient stock for {$medicine['name']}. Available: {$medicine['quantity']}");
                        }

                        $line_total = $quantity * $selling_price;
                        $subtotal += $line_total;
                        
                        $validated_medicines[] = [
                            'medicine_id' => $medicine_id,
                            'quantity' => $quantity,
                            'selling_price' => $selling_price,
                            'unit_price' => floatval($medicine['unit_price']),
                            'name' => $medicine['name'],
                            'line_total' => $line_total
                        ];
                    }

                    // Calculate totals
                    $discount_amount = ($subtotal * $discount) / 100;
                    $subtotal_after_discount = $subtotal - $discount_amount;
                    $tax_amount = ($subtotal_after_discount * $tax) / 100;
                    $total_amount = $subtotal_after_discount + $tax_amount;
                    $change_given = $amount_paid - $total_amount;

                    if ($change_given < 0) {
                        throw new Exception('Amount paid is insufficient. Total: ₱' . number_format($total_amount, 2));
                    }

                    // Generate invoice number
                    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                    // Get customer name if order exists (modified to fetch username)
                    $customer_name = 'Walk-in Customer';
                    if ($order_id > 0) {
                        $customer_stmt = $conn->prepare("SELECT u.username FROM orders o LEFT JOIN users u ON o.ordered_by = u.id WHERE o.id = ?");
                        $customer_stmt->bind_param("i", $order_id);
                        $customer_stmt->execute();
                        $customer_result = $customer_stmt->get_result();
                        if ($customer_row = $customer_result->fetch_assoc()) {
                            $customer_name = $customer_row['username'] ?: 'Walk-in Customer';
                        }
                        $customer_stmt->close();
                    }

                    // Insert invoice
                    $invoice_stmt = $conn->prepare("
                        INSERT INTO sales_invoices 
                        (invoice_number, order_id, customer_name, subtotal, discount, tax, total_amount, 
                        amount_paid, change_given, payment_method, cashier_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $invoice_stmt->bind_param("sisddddddsi", 
                        $invoice_number, $order_id, $customer_name, $subtotal, $discount, $tax, 
                        $total_amount, $amount_paid, $change_given, $payment_method, $cashier_id
                    );
                    $invoice_stmt->execute();
                    $invoice_id = $conn->insert_id;
                    $invoice_stmt->close();

                    // Insert supplier sales and update inventory
                    foreach ($validated_medicines as $med) {
                        $medicine_id = $med['medicine_id'];
                        $quantity = $med['quantity'];
                        $selling_price = $med['selling_price'];
                        $line_total = $med['line_total'];

                        // Insert into supplier_sales
                        $sales_stmt = $conn->prepare("
                            INSERT INTO supplier_sales
                            (order_id, supplier_id, invoice_id, medicine_id, quantity,
                             unit_price, selling_price, line_total, total_amount)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $sales_stmt->bind_param("iiiiidddd",
                            $order_id, $supplier_id, $invoice_id, $medicine_id, 
                            $quantity, $med['unit_price'], $selling_price, $line_total, $line_total
                        );
                        $sales_stmt->execute();
                        $sales_stmt->close();

                        // Decrease supplier inventory
                        $update_supplier_inventory = $conn->prepare("
                            UPDATE supplier_inventory SET quantity = quantity - ? 
                            WHERE supplier_id = ? AND medicine_id = ?
                        ");
                        $update_supplier_inventory->bind_param("iii", $quantity, $supplier_id, $medicine_id);
                        $update_supplier_inventory->execute();
                        $update_supplier_inventory->close();

                        // Update ADMIN inventory - add purchased stock
                        $update_admin_inventory = $conn->prepare("
                            UPDATE medicines SET quantity = quantity + ?
                            WHERE id = ?
                        ");
                        $update_admin_inventory->bind_param("ii", $quantity, $medicine_id);
                        $update_admin_inventory->execute();
                        $update_admin_inventory->close();
                    }

                    // An order-linked sale records delivery; Admin confirms receipt
                    // and finalizes the order after stock is physically received.
                    if ($order_id > 0) {
                        $update_order = $conn->prepare("UPDATE orders SET delivery_status = 'delivered', delivered_at = NOW(), delivery_updated_at = NOW() WHERE id = ? AND status = 'accepted'");
                        $update_order->bind_param("i", $order_id);
                        $update_order->execute();
                        $update_order->close();
                    }

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Sale created successfully' . ($order_id ? '. Order marked delivered and awaiting admin confirmation.' : '');
                    $response['invoice_id'] = $invoice_id;
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['errors'][] = $e->getMessage();
                }
            }
        } elseif ($action === 'void_sale') {
            $invoice_id = intval($_POST['invoice_id'] ?? 0);
            if ($invoice_id <= 0) {
                $response['errors'][] = 'Invalid invoice ID';
            } else {
                $conn->begin_transaction();
                try {
                    // Check if invoice belongs to this supplier
                    $check_stmt = $conn->prepare("
                        SELECT si.id FROM sales_invoices si 
                        INNER JOIN supplier_sales ss ON si.id = ss.invoice_id 
                        WHERE si.id = ? AND ss.supplier_id = ? AND si.status = 'completed'
                    ");
                    $check_stmt->bind_param("ii", $invoice_id, $supplier_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    if ($check_result->num_rows === 0) {
                        throw new Exception('Invoice not found or already voided');
                    }
                    $check_stmt->close();

                    // Get items to reverse
                    $items_stmt = $conn->prepare("
                        SELECT medicine_id, quantity FROM supplier_sales 
                        WHERE invoice_id = ? AND supplier_id = ?
                    ");
                    $items_stmt->bind_param("ii", $invoice_id, $supplier_id);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();

                    while ($item = $items_result->fetch_assoc()) {
                        $medicine_id = $item['medicine_id'];
                        $quantity = $item['quantity'];

                        // Reverse supplier inventory (add back)
                        $rev_supplier = $conn->prepare("
                            UPDATE supplier_inventory SET quantity = quantity + ? 
                            WHERE supplier_id = ? AND medicine_id = ?
                        ");
                        $rev_supplier->bind_param("iii", $quantity, $supplier_id, $medicine_id);
                        $rev_supplier->execute();
                        $rev_supplier->close();

                        // Reverse admin inventory (subtract)
                        $rev_admin = $conn->prepare("
                            UPDATE medicines SET quantity = quantity - ? 
                            WHERE id = ?
                        ");
                        $rev_admin->bind_param("ii", $quantity, $medicine_id);
                        $rev_admin->execute();
                        $rev_admin->close();
                    }
                    $items_stmt->close();

                    // Update invoice status
                    $void_stmt = $conn->prepare("UPDATE sales_invoices SET status = 'voided' WHERE id = ?");
                    $void_stmt->bind_param("i", $invoice_id);
                    $void_stmt->execute();
                    $void_stmt->close();

                    $conn->commit();
                    $response['success'] = true;
                    $response['message'] = 'Sale voided successfully. Stocks reversed.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['errors'][] = $e->getMessage();
                }
            }
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] === 'get_medicines') {
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            
            // Get medicines from SUPPLIER inventory only
            if ($search) {
                $stmt = $conn->prepare("
                          SELECT m.id, m.name, si.quantity,
                              COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS unit_price,
                              ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                                  (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS selling_price
                    FROM medicines m
                    INNER JOIN supplier_inventory si ON m.id = si.medicine_id
                    LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND si.supplier_id = sm.supplier_id
                    WHERE si.supplier_id = ? AND si.quantity > 0 AND (m.name LIKE ? OR m.barcode LIKE ?)
                    ORDER BY m.name ASC LIMIT 50
                ");
                $searchTerm = "%{$search}%";
                $stmt->bind_param("iss", $supplier_id, $searchTerm, $searchTerm);
            } else {
                $stmt = $conn->prepare("
                          SELECT m.id, m.name, si.quantity,
                              COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS unit_price,
                              ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                                  (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS selling_price
                    FROM medicines m
                    INNER JOIN supplier_inventory si ON m.id = si.medicine_id
                    LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND si.supplier_id = sm.supplier_id
                    WHERE si.supplier_id = ? AND si.quantity > 0
                    ORDER BY m.name ASC LIMIT 50
                ");
                $stmt->bind_param("i", $supplier_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $medicines = [];
            while ($row = $result->fetch_assoc()) {
                $medicines[] = $row;
            }
            $response['success'] = true;
            $response['data'] = $medicines;
            $stmt->close();

        } elseif (isset($_GET['action']) && $_GET['action'] === 'get_accepted_orders') {
            // Get accepted orders for this supplier (modified to fetch username)
            $stmt = $conn->prepare("
                SELECT o.id, u.username as ordered_by, o.order_date, o.total_amount, o.expected_delivery
                FROM orders o
                LEFT JOIN users u ON o.ordered_by = u.id
                WHERE o.supplier_id = ? AND o.status = 'accepted'
                ORDER BY o.order_date DESC
            ");
            $stmt->bind_param("i", $supplier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $orders = [];
            while ($row = $result->fetch_assoc()) {
                $orders[] = $row;
            }
            $response['success'] = true;
            $response['data'] = $orders;
            $stmt->close();

        } elseif (isset($_GET['action']) && $_GET['action'] === 'get_order_details') {
            $order_id = intval($_GET['order_id']);
            
            // Get order items (modified to include order info and customer name)
            $stmt = $conn->prepare("
                SELECT oi.medicine_id, m.name as medicine_name, oi.quantity, oi.unit_price,
                       (oi.quantity * oi.unit_price) as line_total, si.quantity as available_stock,
                       o.order_date, o.expected_delivery, u.username as customer_name
                FROM order_items oi
                INNER JOIN medicines m ON oi.medicine_id = m.id
                INNER JOIN orders o ON oi.order_id = o.id
                LEFT JOIN users u ON o.ordered_by = u.id
                LEFT JOIN supplier_inventory si ON si.medicine_id = oi.medicine_id AND si.supplier_id = o.supplier_id
                WHERE oi.order_id = ? AND o.supplier_id = ? AND o.status = 'accepted'
            ");
            $stmt->bind_param("ii", $order_id, $supplier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $items = [];
            $total = 0;
            $order_info = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
                $total += floatval($row['line_total']);
                // Set order info from first row (same for all)
                if (empty($order_info)) {
                    $order_info = [
                        'order_date' => $row['order_date'],
                        'expected_delivery' => $row['expected_delivery'],
                        'customer_name' => $row['customer_name'] ?: 'Walk-in Customer'
                    ];
                }
            }
            
            $response['success'] = true;
            $response['data'] = [
                'items' => $items,
                'total' => $total,
                'order_date' => $order_info['order_date'] ?? null,
                'expected_delivery' => $order_info['expected_delivery'] ?? null,
                'customer_name' => $order_info['customer_name'] ?? 'Walk-in Customer'
            ];
            $stmt->close();

        } elseif (isset($_GET['action']) && $_GET['action'] === 'get_invoice') {
            $invoice_id = intval($_GET['invoice_id']);
            
            $stmt = $conn->prepare("
                SELECT si.*, u.username as cashier_name
                FROM sales_invoices si
                INNER JOIN supplier_sales ss ON si.id = ss.invoice_id
                LEFT JOIN users u ON si.cashier_id = u.id
                WHERE si.id = ? AND ss.supplier_id = ?
                GROUP BY si.id
            ");
            $stmt->bind_param("ii", $invoice_id, $supplier_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $invoice = $result->fetch_assoc();
                
                // Get supplier sales items
                $stmt2 = $conn->prepare("
                    SELECT ss.*, m.name as medicine_name 
                    FROM supplier_sales ss 
                    JOIN medicines m ON ss.medicine_id = m.id 
                    WHERE ss.invoice_id = ? AND ss.supplier_id = ?
                ");
                $stmt2->bind_param("ii", $invoice_id, $supplier_id);
                $stmt2->execute();
                $items_result = $stmt2->get_result();
                
                $items = [];
                while ($item = $items_result->fetch_assoc()) {
                    $items[] = $item;
                }
                $stmt2->close();
                
                $invoice['items'] = $items;
                $response['success'] = true;
                $response['data'] = $invoice;
            } else {
                $response['errors'][] = 'Invoice not found.';
            }
            $stmt->close();

        } else {
            require_once __DIR__ . '/pagination_helper.php';
            $pagination = supGetPaginationParams($conn);
            $page = $pagination['page'];
            $limit = $pagination['limit'];
            $offset = $pagination['offset'];
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $payment_filter = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
            $start_date = isset($_GET['start']) ? trim($_GET['start']) : '';
            $end_date = isset($_GET['end']) ? trim($_GET['end']) : '';

            $where = 'WHERE ss.supplier_id = ?';
            $params = [$supplier_id];
            $types = 'i';

            if ($search !== '') {
                $where .= ' AND (si.invoice_number LIKE ? OR si.customer_name LIKE ?)';
                $term = '%' . $search . '%';
                $params[] = $term;
                $params[] = $term;
                $types .= 'ss';
            }
            if ($payment_filter !== '') {
                $where .= ' AND si.payment_method = ?';
                $params[] = $payment_filter;
                $types .= 's';
            }
            if ($start_date !== '' && $end_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date) && $start_date <= $end_date) {
                $where .= ' AND DATE(si.created_at) BETWEEN ? AND ?';
                $params[] = $start_date;
                $params[] = $end_date;
                $types .= 'ss';
            }

            $countSql = "
                SELECT COUNT(DISTINCT si.id) AS total
                FROM sales_invoices si
                INNER JOIN supplier_sales ss ON si.id = ss.invoice_id
                $where
            ";
            $countStmt = $conn->prepare($countSql);
            if (!$countStmt) throw new Exception('Count prepare failed: ' . $conn->error);
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $totalItems = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
            $countStmt->close();

            $filteredRevenueSql = "
                SELECT 
                    COALESCE(SUM(ss.line_total), 0) AS filtered_revenue,
                    COALESCE(SUM(ss.line_total - (ss.unit_price * ss.quantity)), 0) AS filtered_profit
                FROM sales_invoices si
                INNER JOIN supplier_sales ss ON si.id = ss.invoice_id
                $where
            ";
            $filteredRevStmt = $conn->prepare($filteredRevenueSql);
            if (!$filteredRevStmt) throw new Exception('Filtered revenue prepare failed: ' . $conn->error);
            $filteredRevStmt->bind_param($types, ...$params);
            $filteredRevStmt->execute();
            $filteredSummary = $filteredRevStmt->get_result()->fetch_assoc() ?: [];
            $filteredRevenue = (float)($filteredSummary['filtered_revenue'] ?? 0);
            $filteredProfit = (float)($filteredSummary['filtered_profit'] ?? 0);
            $filteredRevStmt->close();

            $totalRevStmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(line_total), 0) AS total_revenue,
                    COALESCE(SUM(line_total - (unit_price * quantity)), 0) AS total_profit,
                    COUNT(DISTINCT invoice_id) AS sale_count
                FROM supplier_sales
                WHERE supplier_id = ?
            ");
            if (!$totalRevStmt) throw new Exception('Total revenue prepare failed: ' . $conn->error);
            $totalRevStmt->bind_param('i', $supplier_id);
            $totalRevStmt->execute();
            $totalRevRow = $totalRevStmt->get_result()->fetch_assoc() ?: [];
            $totalRevStmt->close();

            $sql = "
                SELECT 
                    si.id,
                    si.invoice_number,
                    si.order_id,
                    si.customer_name,
                    si.subtotal,
                    si.discount,
                    si.tax,
                    si.total_amount,
                    si.amount_paid,
                    si.change_given,
                    si.payment_method,
                    si.cashier_id,
                    si.status,
                    si.created_at,
                    u.username as cashier_name,
                    COUNT(ss.id) as item_count
                FROM sales_invoices si 
                LEFT JOIN users u ON si.cashier_id = u.id 
                INNER JOIN supplier_sales ss ON si.id = ss.invoice_id
                $where
                GROUP BY si.id, si.invoice_number, si.order_id, si.customer_name, si.subtotal, si.discount, si.tax, 
                         si.total_amount, si.amount_paid, si.change_given, si.payment_method, 
                         si.cashier_id, si.status, si.created_at, u.username
                ORDER BY si.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $listParams = $params;
            $listTypes = $types . 'ii';
            $listParams[] = $limit;
            $listParams[] = $offset;

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare query: ' . $conn->error);
            }

            $stmt->bind_param($listTypes, ...$listParams);
            $stmt->execute();
            $result = $stmt->get_result();

            $invoices = [];
            while ($row = $result->fetch_assoc()) {
                $invoices[] = $row;
            }
            $response['success'] = true;
            $response['data'] = $invoices;
            $response['pagination'] = supBuildPaginationMeta($page, $limit, $totalItems);
            $response['summary'] = [
                'total_revenue'    => (float)($totalRevRow['total_revenue'] ?? 0),
                'total_profit'     => (float)($totalRevRow['total_profit'] ?? 0),
                'filtered_revenue' => $filteredRevenue,
                'filtered_profit'  => $filteredProfit,
                'sale_count'       => (int)($totalRevRow['sale_count'] ?? 0),
                'filtered_count'   => $totalItems,
            ];
            $stmt->close();
        }
    }
} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    $response['errors'][] = 'System error: ' . $e->getMessage();
}

$conn->close();

ob_end_clean();
echo json_encode($response);
exit();
?>
