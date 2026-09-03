<?php
// Admin/api/sales.php
// Full updated API with invoice_id support for walk-in and purchase sales

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();

header('Content-Type: application/json');

// Helper to send JSON and terminate cleanly
function send_json_and_exit($resp, $status_code = 200) {
    if (!headers_sent()) {
        http_response_code($status_code);
        header('Content-Type: application/json');
    }
    if (ob_get_length() !== false) {
        @ob_end_clean();
    }
    echo json_encode($resp);
    exit();
}

// Shutdown handler to catch fatal errors and always return valid JSON
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $debugDir = __DIR__ . '/../../tmp';
        if (!file_exists($debugDir)) @mkdir($debugDir, 0777, true);
        $msg = "[".date('c')."] FATAL: " . ($err['message'] ?? '') . " in " . ($err['file'] ?? '') . " on line " . ($err['line'] ?? '') . "\n";
        @file_put_contents($debugDir . '/sales_fatal.log', $msg, FILE_APPEND | LOCK_EX);
        // Capture any buffered stray output for debugging
        if (ob_get_length() !== false) {
            $buf = ob_get_contents();
            @ob_end_clean();
            if (trim($buf) !== '') @file_put_contents($debugDir . '/sales_stray_output.log', "[".date('c')."]\n".$buf."\n", FILE_APPEND | LOCK_EX);
        }
        if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'errors' => ['Unexpected server error occurred.']]);
        exit();
    }
});

if (!isset($_SESSION['user_id'])) {
    send_json_and_exit(['success' => false, 'errors' => ['Unauthorized']], 401);
}

require_once '../config/db.php';
if ($conn->connect_error) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['Database connection failed: ' . $conn->connect_error]]);
    exit();
}
$conn->set_charset("utf8mb4");

foreach ([__DIR__ . '/../includes/inventory_helpers.php', __DIR__ . '/includes/inventory_helpers.php'] as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
// Auto-reorder helper
foreach ([__DIR__ . '/../includes/auto_reorder.php', __DIR__ . '/includes/auto_reorder.php', __DIR__ . '/../../includes/auto_reorder.php'] as $p) {
    if (file_exists($p)) { require_once $p; break; }
}

$response = ['success' => false, 'errors' => [], 'data' => null];

try {
    // Ensure debug folder exists for temporary logging
    $debugDir = __DIR__ . '/../../tmp';
    if (!file_exists($debugDir)) {
        @mkdir($debugDir, 0777, true);
    }
    // Log incoming request for debugging (raw body and parsed _POST)
    $rawBody = file_get_contents('php://input');
    $postSnapshot = json_encode($_POST);
    @file_put_contents($debugDir . '/sales_request.log', "[".date('c')."] METHOD: {$_SERVER['REQUEST_METHOD']} RAW_BODY:\n" . $rawBody . "\n_POST:\n" . $postSnapshot . "\n\n", FILE_APPEND | LOCK_EX);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create_sale') {
            $purchase_id = !empty($_POST['purchase_id']) ? intval($_POST['purchase_id']) : null;
            $medicines       = $_POST['medicines'] ?? [];
            $payment_method  = $_POST['payment_method'] ?? 'cash';
            $amount_paid     = floatval($_POST['amount_paid'] ?? 0);
            $discount        = floatval($_POST['discount'] ?? 0);
            $tax             = floatval($_POST['tax'] ?? 0);
            $cashier_id      = $_SESSION['user_id'];

            if (empty($medicines) || !is_array($medicines)) {
                $response['errors'][] = 'At least one medicine is required.';
            }
            if ($amount_paid < 0) {
                $response['errors'][] = 'Amount paid cannot be negative.';
            }
            if ($discount < 0 || $discount > 100) {
                $response['errors'][] = 'Discount must be between 0 and 100%.';
            }
            if ($tax < 0) {
                $response['errors'][] = 'Tax cannot be negative.';
            }

            if (empty($response['errors'])) {
                $conn->begin_transaction();
                try {
                    $subtotal = 0;
                    $locked_purchase = null;

                    // ---- If this sale is closing out a pending purchase, lock it now and make
                    // sure it's still pending. This is the guard that was missing: previously a
                    // purchase could be filled via the Purchases page's "Fill" button and then
                    // *also* completed here (or vice-versa), deducting stock twice for the same
                    // purchase. ----
                    if ($purchase_id) {
                        $locked_purchase = lockPendingPurchaseOrFail($conn, $purchase_id);

                        // ---- The sale must match what the purchase actually asked for — one
                        // line item, same medicine, same quantity. Otherwise nothing stops a
                        // purchase for e.g. 10 units of Paracetamol being "closed" by a sale of
                        // something else entirely. ----
                        if (count($medicines) !== 1
                            || (int)$medicines[0]['medicine_id'] !== (int)$locked_purchase['medicine_id']
                            || (int)$medicines[0]['quantity'] !== (int)$locked_purchase['quantity']) {
                            throw new Exception(
                                "This sale doesn't match purchase #{$purchase_id} ({$locked_purchase['purchase_number']}): "
                                . "expected {$locked_purchase['quantity']} x {$locked_purchase['medicine_name']}."
                            );
                        }
                    }

                    // ---- Validate medicines & calculate subtotal ----
                    foreach ($medicines as $med) {
                        $medicine_id   = intval($med['medicine_id']);
                        $quantity      = intval($med['quantity']);
                        $selling_price = floatval($med['selling_price']);

                        if ($medicine_id <= 0 || $quantity <= 0 || $selling_price < 0) {
                            throw new Exception('Invalid medicine data.');
                        }

                        $stmt = $conn->prepare("SELECT quantity, name FROM medicines WHERE id = ?");
                        $stmt->bind_param("i", $medicine_id);
                        $stmt->execute();
                        $result   = $stmt->get_result();
                        $medicine = $result->fetch_assoc();
                        $stmt->close();

                        if (!$medicine) {
                            throw new Exception('Medicine not found.');
                        }
                        if ($medicine['quantity'] < $quantity) {
                            throw new Exception("Insufficient stock for {$medicine['name']}. Available: {$medicine['quantity']}");
                        }

                        $subtotal += $quantity * $selling_price;
                    }

                    // ---- Totals calculation ----
                    $discount_amount       = ($subtotal * $discount) / 100;
                    $subtotal_after_discount = $subtotal - $discount_amount;
                    $tax_amount            = ($subtotal_after_discount * $tax) / 100;
                    $total_amount          = $subtotal_after_discount + $tax_amount;
                    $change_given          = $amount_paid - $total_amount;

                    if ($change_given < 0) {
                        throw new Exception('Amount paid is insufficient. Total: ₱' . number_format($total_amount, 2));
                    }

                    // ---- Generate invoice number ----
                    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

                    // ---- Create invoice record ----
                    $stmt = $conn->prepare("INSERT INTO sales_invoices 
                        (invoice_number, purchase_id, subtotal, discount, tax, total_amount, amount_paid, change_given, payment_method, cashier_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param(
                        "siddddddsi",
                        $invoice_number,
                        $purchase_id,
                        $subtotal,
                        $discount,
                        $tax,
                        $total_amount,          // <-- FIXED: was "total unfortunately_amount"
                        $amount_paid,
                        $change_given,
                        $payment_method,
                        $cashier_id
                    );
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to create invoice: ' . $stmt->error);
                    }
                    $invoice_id = $conn->insert_id;
                    $stmt->close();

                    // ---- Process each medicine (sale rows) ----
                    foreach ($medicines as $med) {
                        $medicine_id   = intval($med['medicine_id']);
                        $quantity      = intval($med['quantity']);
                        $selling_price = floatval($med['selling_price']);

                        // Get the latest actual acquisition cost. Orders store the
                        // supplier selling price, so they must not be used as cost.
                        $stmt = $conn->prepare("SELECT COALESCE(
                            (SELECT unit_price FROM supplier_sales WHERE medicine_id = ? ORDER BY created_at DESC, id DESC LIMIT 1),
                            (SELECT total_cost / NULLIF(quantity, 0) FROM purchases WHERE medicine_id = ? AND status = 'filled' AND quantity > 0 ORDER BY purchase_date DESC, id DESC LIMIT 1),
                            0
                        ) AS cost_price");
                        $stmt->bind_param("ii", $medicine_id, $medicine_id);
                        $stmt->execute();
                        $result   = $stmt->get_result();
                        $order    = $result->fetch_assoc();
                        $cost_price = $order ? floatval($order['cost_price']) : 0;
                        $stmt->close();

                        $profit = ($selling_price - $cost_price) * $quantity;

                        // Insert sale row with invoice_id
                        $stmt = $conn->prepare("INSERT INTO sales 
                            (invoice_id, purchase_id, medicine_id, quantity, cost_price, selling_price, profit, sale_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param(
                            "iiiiddd",
                            $invoice_id,
                            $purchase_id,
                            $medicine_id,
                            $quantity,
                            $cost_price,
                            $selling_price,
                            $profit
                        );
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to create sale record: ' . $stmt->error);
                        }
                        $stmt->close();

                        // Reduce stock
                        $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
                        $stmt->bind_param("ii", $quantity, $medicine_id);
                        $stmt->execute();
                        $stmt->close();

                        // Trigger auto-reorder if configured, and log the outcome so we can verify
                        if (function_exists('triggerAutoReorder')) {
                            try {
                                $arResult = triggerAutoReorder($conn, $medicine_id, $cashier_id, 200);
                                $arLog = __DIR__ . '/../../tmp/auto_reorder.log';
                                @file_put_contents($arLog, "[".date('c')."] Admin sale triggered auto-reorder check for medicine_id={$medicine_id}, cashier_id={$cashier_id}, result=" . var_export($arResult, true) . "\n", FILE_APPEND | LOCK_EX);
                            } catch (Throwable $t) {
                                @file_put_contents(__DIR__ . '/../../tmp/auto_reorder.log', "[".date('c')."] Admin sale auto-reorder exception for medicine_id={$medicine_id}: " . $t->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                            }
                        }

                        // Log transaction
                        $line_total = $quantity * $selling_price;
                        $stmt = $conn->prepare("INSERT INTO transactions 
                            (medicine_id, user_id, pharmacist_id, purchase_id, reason, total_cost, action, quantity, timestamp) 
                            VALUES (?, ?, ?, ?, 'Sale transaction', ?, 'remove', ?, NOW())");
                        $stmt->bind_param(
                            "iiiidi",
                            $medicine_id,
                            $cashier_id,
                            $cashier_id,
                            $purchase_id,
                            $line_total,
                            $quantity
                        );
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Shared with purchases.php so a sale that drops stock low
                    // raises the same alert a "Fill" on the Purchases page would.
                    foreach ($medicines as $med) {
                        checkLowStockAndNotify($conn, (int)$med['medicine_id'], $cashier_id);
                    }

                    // ---- Update purchase status if linked ----
                    if ($purchase_id) {
                        $stmt = $conn->prepare("UPDATE purchases 
                            SET status = 'filled', payment_status = 'paid', payment_method = ?, amount_paid = ?, change_given = ?, filled_by_user_id = ? 
                            WHERE id = ?");
                        $stmt->bind_param("sddii", $payment_method, $amount_paid, $change_given, $cashier_id, $purchase_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                    $response['success'] = true;
                    $response['message']  = 'Sale completed successfully!';
                    $response['data']     = [
                        'invoice_number' => $invoice_number,
                        'total_amount'   => $total_amount,
                        'change_given'   => $change_given
                    ];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['errors'][] = $e->getMessage();
                }
            }
        }

        // -----------------------------------------------------------------
        // VOID SALE
        // -----------------------------------------------------------------
        elseif ($action === 'void_sale') {
            $invoice_id = intval($_POST['invoice_id']);
            if ($invoice_id <= 0) {
                $response['errors'][] = 'Invalid invoice ID.';
            } else {
                $conn->begin_transaction();
                try {
                    // Get invoice
                    $stmt = $conn->prepare("SELECT * FROM sales_invoices WHERE id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    $stmt->execute();
                    $result  = $stmt->get_result();
                    $invoice = $result->fetch_assoc();
                    $stmt->close();

                    if (!$invoice) throw new Exception('Invoice not found.');

                    // Get sale rows by invoice_id
                    $stmt = $conn->prepare("SELECT s.*, m.name FROM sales s JOIN medicines m ON s.medicine_id = m.id WHERE s.invoice_id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $sales  = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    // Restore stock
                    foreach ($sales as $sale) {
                        $stmt = $conn->prepare("UPDATE medicines SET quantity = quantity + ? WHERE id = ?");
                        $stmt->bind_param("ii", $sale['quantity'], $sale['medicine_id']);
                        $stmt->execute();
                        $stmt->close();

                        $total = $sale['quantity'] * $sale['selling_price'];
                        $stmt = $conn->prepare("INSERT INTO transactions 
                            (medicine_id, user_id, reason, total_cost, action, quantity, timestamp) 
                            VALUES (?, ?, 'Sale voided', ?, 'add', ?, NOW())");
                        $stmt->bind_param("iidi", $sale['medicine_id'], $_SESSION['user_id'], $total, $sale['quantity']);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Delete sale rows
                    $stmt = $conn->prepare("DELETE FROM sales WHERE invoice_id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    $stmt->execute();
                    $stmt->close();

                    // Delete invoice
                    $stmt = $conn->prepare("DELETE FROM sales_invoices WHERE id = ?");
                    $stmt->bind_param("i", $invoice_id);
                    $stmt->execute();
                    $stmt->close();

                    // Revert purchase if any
                    if ($invoice['purchase_id']) {
                        $stmt = $conn->prepare("UPDATE purchases SET status = 'pending', payment_status = 'pending' WHERE id = ?");
                        $stmt->bind_param("i", $invoice['purchase_id']);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                    $response['success'] = true;
                    $response['message']  = 'Sale voided successfully!';
                } catch (Exception $e) {
                    $conn->rollback();
                    $response['errors'][] = $e->getMessage();
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // GET REQUESTS
    // -----------------------------------------------------------------
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            $action = $_GET['action'];

            // ----- Search medicines -----
            if ($action === 'get_medicines') {
                $search = $_GET['search'] ?? '';
                if ($search) {
                    $stmt = $conn->prepare("SELECT id, name, quantity,
                            COALESCE(NULLIF(selling_price, 0),
                                ROUND((SELECT selling_price FROM supplier_sales WHERE medicine_id = medicines.id ORDER BY created_at DESC, id DESC LIMIT 1) * (1 + COALESCE(NULLIF(markup_percentage, 0), 20) / 100), 2),
                                ROUND((SELECT unit_price FROM orders WHERE medicine_id = medicines.id AND status IN ('fulfilled', 'delivered') ORDER BY COALESCE(actual_delivery, order_date) DESC LIMIT 1) * (1 + COALESCE(NULLIF(markup_percentage, 0), 20) / 100), 2),
                                ROUND((SELECT total_cost / NULLIF(quantity, 0) FROM purchases WHERE medicine_id = medicines.id AND status = 'filled' AND quantity > 0 ORDER BY purchase_date DESC, id DESC LIMIT 1) * (1 + COALESCE(NULLIF(markup_percentage, 0), 20) / 100), 2), 0) AS selling_price,
                            COALESCE((SELECT unit_price FROM supplier_sales WHERE medicine_id = medicines.id ORDER BY created_at DESC, id DESC LIMIT 1),
                                     (SELECT total_cost / NULLIF(quantity, 0) FROM purchases WHERE medicine_id = medicines.id AND status = 'filled' AND quantity > 0 ORDER BY purchase_date DESC, id DESC LIMIT 1), 0) AS buying_price
                        FROM medicines WHERE (name LIKE ? OR barcode LIKE ?) AND quantity > 0 ORDER BY name ASC LIMIT 50");
                    $term = "%$search%";
                    $stmt->bind_param("ss", $term, $term);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query("SELECT id, name, quantity,
                            COALESCE(NULLIF(selling_price, 0),
                                ROUND((SELECT selling_price FROM supplier_sales WHERE medicine_id = medicines.id ORDER BY created_at DESC, id DESC LIMIT 1) * (1 + COALESCE(NULLIF(markup_percentage, 0), 20) / 100), 2),
                                ROUND((SELECT unit_price FROM orders WHERE medicine_id = medicines.id AND status IN ('fulfilled', 'delivered') ORDER BY COALESCE(actual_delivery, order_date) DESC LIMIT 1) * (1 + COALESCE(NULLIF(markup_percentage, 0), 20) / 100), 2),
                                ROUND((SELECT total_cost / NULLIF(quantity, 0) FROM purchases WHERE medicine_id = medicines.id AND status = 'filled' AND quantity > 0 ORDER BY purchase_date DESC, id DESC LIMIT 1) * (1 + COALESCE(NULLIF(markup_percentage, 0), 20) / 100), 2), 0) AS selling_price,
                            COALESCE((SELECT unit_price FROM supplier_sales WHERE medicine_id = medicines.id ORDER BY created_at DESC, id DESC LIMIT 1), (SELECT total_cost / NULLIF(quantity, 0) FROM purchases WHERE medicine_id = medicines.id AND status = 'filled' AND quantity > 0 ORDER BY purchase_date DESC, id DESC LIMIT 1), 0) AS buying_price
                        FROM medicines WHERE quantity > 0 ORDER BY name ASC LIMIT 50");
                }
                $medicines = [];
                while ($row = $result->fetch_assoc()) $medicines[] = $row;
                $response['success'] = true;
                $response['data']    = $medicines;
                if (isset($stmt)) $stmt->close();
            }

            // ----- View single invoice -----
            elseif ($action === 'get_invoice') {
                $invoice_id = intval($_GET['invoice_id']);
                $stmt = $conn->prepare("SELECT si.*, u.username as cashier_name, p.purchase_number 
                                        FROM sales_invoices si 
                                        LEFT JOIN users u ON si.cashier_id = u.id 
                                        LEFT JOIN purchases p ON si.purchase_id = p.id 
                                        WHERE si.id = ?");
                $stmt->bind_param("i", $invoice_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $invoice = $result->fetch_assoc();

                    $stmt2 = $conn->prepare("SELECT s.*, m.name as medicine_name 
                                             FROM sales s 
                                             JOIN medicines m ON s.medicine_id = m.id 
                                             WHERE s.invoice_id = ?");
                    $stmt2->bind_param("i", $invoice_id);
                    $stmt2->execute();
                    $items_res = $stmt2->get_result();
                    $items = [];
                    while ($item = $items_res->fetch_assoc()) $items[] = $item;
                    $stmt2->close();

                    $invoice['items'] = $items;
                    $response['success'] = true;
                    $response['data']    = $invoice;
                } else {
                    $response['errors'][] = 'Invoice not found.';
                }
                $stmt->close();
            }

            // ----- Pending purchases -----
            elseif ($action === 'get_purchases') {
                // medicine_id/name/quantity are included so the Sales page can pre-fill and
                // lock the matching line item instead of leaving the cashier to retype it —
                // that's what create_sale validates against, so this keeps the form from
                // ever submitting something that wouldn't match.
                $result = $conn->query("SELECT p.id, p.purchase_number, p.purchase_date,
                                                p.medicine_id, p.quantity, m.name AS medicine_name,
                                                m.selling_price,
                                                p.total_cost / NULLIF(p.quantity, 0) AS buying_price
                                        FROM purchases p
                                        JOIN medicines m ON p.medicine_id = m.id
                                        WHERE p.status = 'pending' 
                                        ORDER BY p.purchase_date DESC");
                $purchases = [];
                while ($row = $result->fetch_assoc()) $purchases[] = $row;
                $response['success'] = true;
                $response['data']    = $purchases;
            }
        } else {
            // Optional month filter (YYYY-MM) — scopes the invoice list AND every
            // summary figure (revenue, profit, restocking cost) to that month so
            // "Net Profit" stays internally consistent when filtered.
            $month = isset($_GET['month']) ? trim($_GET['month']) : '';
            $monthValid = (bool)preg_match('/^\d{4}-\d{2}$/', $month);
            $start = isset($_GET['start']) ? trim($_GET['start']) : '';
            $end = isset($_GET['end']) ? trim($_GET['end']) : '';
            $rangeValid = (bool)(preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && $start <= $end);
            $dateClauseInvoices = $rangeValid
                ? " AND DATE(si.created_at) BETWEEN ? AND ?"
                : ($monthValid ? " AND DATE_FORMAT(si.created_at, '%Y-%m') = ?" : '');

            // ----- List all invoices (with item count) -----
            // order_id IS NULL excludes supplier-restocking invoices created by orders.php's
            // "Confirm Received" flow — those represent money paid OUT to a supplier, not
            // revenue from a customer, and were previously showing up mixed into this list.
            $sql = "SELECT si.*, u.username as cashier_name, p.purchase_number,
                           (SELECT COUNT(*) FROM sales s WHERE s.invoice_id = si.id) as item_count,
                           (SELECT COALESCE(SUM(s.profit), 0) FROM sales s WHERE s.invoice_id = si.id) as invoice_profit
                    FROM sales_invoices si 
                    LEFT JOIN users u ON si.cashier_id = u.id 
                    LEFT JOIN purchases p ON si.purchase_id = p.id
                    WHERE si.order_id IS NULL $dateClauseInvoices
                    ORDER BY si.created_at DESC";
            $stmt = $conn->prepare($sql);
            if ($rangeValid) {
                $stmt->bind_param('ss', $start, $end);
            } elseif ($monthValid) {
                $stmt->bind_param('s', $month);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $invoices = [];
            while ($row = $result->fetch_assoc()) $invoices[] = $row;
            $stmt->close();

            $summarySql = "
                SELECT COALESCE(SUM(si.total_amount), 0) AS total_revenue, COUNT(*) AS sale_count
                FROM sales_invoices si
                WHERE si.order_id IS NULL $dateClauseInvoices
            ";
            $summaryStmt = $conn->prepare($summarySql);
            if ($rangeValid) {
                $summaryStmt->bind_param('ss', $start, $end);
            } elseif ($monthValid) {
                $summaryStmt->bind_param('s', $month);
            }
            $summaryStmt->execute();
            $summaryRow = $summaryStmt->get_result()->fetch_assoc();
            $summaryStmt->close();

            $dateClauseProfit = $rangeValid
                ? " AND DATE(si.created_at) BETWEEN ? AND ?"
                : ($monthValid ? " AND DATE_FORMAT(si.created_at, '%Y-%m') = ?" : '');
            $profitSql = "
                SELECT COALESCE(SUM(s.profit), 0) AS total_profit
                FROM sales s
                JOIN sales_invoices si ON s.invoice_id = si.id
                WHERE si.order_id IS NULL $dateClauseProfit
            ";
            $profitStmt = $conn->prepare($profitSql);
            if ($rangeValid) {
                $profitStmt->bind_param('ss', $start, $end);
            } elseif ($monthValid) {
                $profitStmt->bind_param('s', $month);
            }
            $profitStmt->execute();
            $profitRow = $profitStmt->get_result()->fetch_assoc();
            $profitStmt->close();

            // Restocking purchases remain inventory until the stock is sold. Sale rows
            // already include their cost_price when calculating profit.
            $dateClauseRestock = $rangeValid
                ? " AND DATE(si.created_at) BETWEEN ? AND ?"
                : ($monthValid ? " AND DATE_FORMAT(si.created_at, '%Y-%m') = ?" : '');
            $restockSql = "
                SELECT COALESCE(SUM(si.total_amount), 0) AS total_restocking_cost
                FROM sales_invoices si
                WHERE si.order_id IS NOT NULL $dateClauseRestock
            ";
            $restockStmt = $conn->prepare($restockSql);
            if ($rangeValid) {
                $restockStmt->bind_param('ss', $start, $end);
            } elseif ($monthValid) {
                $restockStmt->bind_param('s', $month);
            }
            $restockStmt->execute();
            $restockRow = $restockStmt->get_result()->fetch_assoc();
            $restockStmt->close();

            $totalProfit = (float)($profitRow['total_profit'] ?? 0);
            $totalRestockingCost = (float)($restockRow['total_restocking_cost'] ?? 0);

            $response['success'] = true;
            $response['data']    = $invoices;
            $response['month']   = $monthValid ? $month : null;
            $response['range']   = $rangeValid ? ['start' => $start, 'end' => $end] : null;
            $response['summary'] = [
                'total_revenue'         => (float)($summaryRow['total_revenue'] ?? 0),
                'sale_count'            => (int)($summaryRow['sale_count'] ?? 0),
                'total_profit'          => $totalProfit,
                'total_restocking_cost' => $totalRestockingCost,
                'net_profit'            => $totalProfit,
            ];
        }
    }
} catch (Exception $e) {
    $response['errors'][] = 'System error: ' . $e->getMessage();
}

$conn->close();

// Capture any stray output that was buffered and log it for debugging
$buffer = '';
if (ob_get_length() !== false) {
    $buffer = ob_get_contents();
}
// Always write response JSON to debug log for inspection
$respLog = __DIR__ . '/../../tmp/sales_response.log';
@file_put_contents($respLog, "[".date('c')."] RESPONSE_JSON:\n" . json_encode($response) . "\n", FILE_APPEND | LOCK_EX);

if (!empty($buffer) && trim($buffer) !== '') {
    $logFile = __DIR__ . '/../../tmp/sales_debug.log';
    @file_put_contents($logFile, "[".date('c')."] STRAY OUTPUT:\n". $buffer . "\nRESPONSE JSON:\n" . json_encode($response) . "\n\n", FILE_APPEND | LOCK_EX);
}

// Decide status code: if there are errors and not marked success, return 500 so client knows it failed
$statusCode = (!empty($response['errors']) && empty($response['success'])) ? 500 : 200;
send_json_and_exit($response, $statusCode);
?>
