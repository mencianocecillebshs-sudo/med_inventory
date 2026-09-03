<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/db.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit();
}

try {
    // 1. Current month sales with next-month projection comparison
    $monthSales = 0;
    $monthSalesResult = $conn->query("
        SELECT SUM(total_amount)
        FROM sales_invoices
        WHERE order_id IS NULL
          AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
          AND created_at < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)
    ");
    if ($monthSalesResult) {
        $monthSales = (float)($monthSalesResult->fetch_row()[0] ?? 0);
    }

    $dayOfMonth = max(1, (int)date('j'));
    $nextMonthDays = (int)date('t', strtotime('first day of next month'));
    $nextMonthProjectedSales = ($monthSales / $dayOfMonth) * $nextMonthDays;

    $salesChangePercent = 0;
    if ($nextMonthProjectedSales > 0) {
        $salesChangePercent = (($monthSales - $nextMonthProjectedSales) / $nextMonthProjectedSales) * 100;
    } elseif ($monthSales > 0) {
        $salesChangePercent = 100;
    }

    // 3. Inventory Cost Value
    $inventoryCostValue = 0;
    $invResult = $conn->query("SELECT SUM(quantity * (selling_price / (1 + COALESCE(markup_percentage, 0) / 100))) FROM medicines");
    if ($invResult) {
        $inventoryCostValue = (float)($invResult->fetch_row()[0] ?? 0);
    }

    // 4. Low stock count uses the system-wide Settings threshold.
    $threshold = getLowStockThreshold($conn);
    $lowStmt = $conn->prepare("SELECT COUNT(*) FROM medicines WHERE quantity <= ?");
    $lowStockCount = 0;
    if ($lowStmt) {
        $lowStmt->bind_param('i', $threshold);
        $lowStmt->execute();
        $lowStockCount = (int)($lowStmt->get_result()->fetch_row()[0] ?? 0);
        $lowStmt->close();
    }

    // 5. Expiring soon uses the configured expiry-alert window.
    $expiryDays = getExpiryAlertDays($conn);
    $exp30Result = $conn->query("SELECT COUNT(*) FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expiryDays DAY) AND expiry_date >= CURDATE()");
    $expiring30Count = $exp30Result ? (int)$exp30Result->fetch_row()[0] : 0;

    // Expiring suppliers
    $supExpResult = $conn->query("SELECT suppliers FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL $expiryDays DAY) AND expiry_date >= CURDATE()");
    $expiringSuppliers = [];
    if ($supExpResult) {
        while ($row = $supExpResult->fetch_assoc()) {
            if (!empty($row['suppliers'])) {
                $sups = array_map('trim', explode(',', $row['suppliers']));
                foreach ($sups as $s) {
                    if ($s !== '') $expiringSuppliers[$s] = true;
                }
            }
        }
    }
    $expiringSuppliersCount = count($expiringSuppliers);

    // 6. Pending orders count
    $pendingOrdersCount = 0;
    $pendingOrdersResult = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending' OR status = 'ordered'");
    if ($pendingOrdersResult) {
        $pendingOrdersCount = (int)$pendingOrdersResult->fetch_row()[0];
    }

    $awaitingApprovalCount = 0;
    $awaitingApprovalResult = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    if ($awaitingApprovalResult) {
        $awaitingApprovalCount = (int)$awaitingApprovalResult->fetch_row()[0];
    }

    // 7. Supplier Status
    $supplierStatus = [];
    $supSql = "
        SELECT 
            s.id,
            s.company,
            s.name,
            o.status AS order_status,
            o.expected_delivery,
            o.actual_delivery,
            DATEDIFF(CURDATE(), o.expected_delivery) as days_late
        FROM suppliers s
        LEFT JOIN (
            SELECT o1.*
            FROM orders o1
            INNER JOIN (
                SELECT supplier_id, MAX(id) as max_id
                FROM orders
                GROUP BY supplier_id
            ) o2 ON o1.id = o2.max_id
        ) o ON s.id = o.supplier_id
        ORDER BY s.bought_quantity DESC
        LIMIT 5
    ";
    $supRes = $conn->query($supSql);
    if ($supRes) {
        while ($row = $supRes->fetch_assoc()) {
            $statusLabel = 'On time';
            $statusClass = 'ok';
            
            if ($row['order_status'] === 'pending' || $row['order_status'] === 'ordered') {
                if ($row['days_late'] > 0) {
                    $statusLabel = $row['days_late'] . ' days late';
                    $statusClass = 'late';
                }
            } elseif ($row['order_status'] === 'delivered' || $row['order_status'] === 'fulfilled') {
                if ($row['actual_delivery'] && $row['expected_delivery'] && $row['actual_delivery'] > $row['expected_delivery']) {
                    $diff = (strtotime($row['actual_delivery']) - strtotime($row['expected_delivery'])) / 86400;
                    if ($diff > 0) {
                        $statusLabel = round($diff) . ' days late';
                        $statusClass = 'late';
                    }
                }
            }
            
            $supplierStatus[] = [
                'company' => $row['company'],
                'status' => $statusLabel,
                'class' => $statusClass
            ];
        }
    }

    // 8. Recent Activity
    $recentActivity = [];
    $actSql = "
        SELECT 
            t.id,
            t.action,
            t.quantity,
            t.timestamp,
            m.name AS medicine_name,
            u.username AS user_name,
            t.reason,
            t.notes
        FROM transactions t
        LEFT JOIN medicines m ON t.medicine_id = m.id
        LEFT JOIN users u ON COALESCE(t.user_id, t.pharmacist_id) = u.id
        ORDER BY t.timestamp DESC, t.id DESC
        LIMIT 5
    ";
    $actRes = $conn->query($actSql);
    if ($actRes) {
        while ($row = $actRes->fetch_assoc()) {
            $recentActivity[] = [
                'id' => (int)$row['id'],
                'action' => $row['action'],
                'quantity' => (int)$row['quantity'],
                'timestamp' => $row['timestamp'],
                'medicine_name' => $row['medicine_name'],
                'user_name' => $row['user_name'],
                'reason' => $row['reason'],
                'notes' => $row['notes']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'kpis' => [
            'month_sales' => $monthSales,
            'today_sales' => $monthSales,
            'next_month_projected_sales' => $nextMonthProjectedSales,
            'sales_change_percent' => $salesChangePercent,
            'inventory_value' => $inventoryCostValue,
            'low_stock_count' => $lowStockCount,
            'low_stock_threshold' => $threshold,
            'expiring_count_30' => $expiring30Count,
            'expiry_alert_days' => $expiryDays,
            'expiring_suppliers_count' => $expiringSuppliersCount,
            'pending_orders_count' => $pendingOrdersCount,
            'pending_orders_awaiting_approval' => $awaitingApprovalCount
        ],
        'supplier_status' => $supplierStatus,
        'recent_activity' => $recentActivity
    ]);

} catch (Exception $e) {
    error_log("Dashboard metrics API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load dashboard metrics: ' . $e->getMessage()
    ]);
}

if ($conn) {
    $conn->close();
}
?>
