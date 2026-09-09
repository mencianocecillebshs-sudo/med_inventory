<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    $alerts = [];
    $alertsCreated = 0;

    $supplierId = 0;
    $supplierIdStmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ? LIMIT 1");
    if ($supplierIdStmt) {
        $supplierIdStmt->bind_param('i', $userId);
        $supplierIdStmt->execute();
        $supplierRow = $supplierIdStmt->get_result()->fetch_assoc();
        $supplierIdStmt->close();
        $supplierId = (int)($supplierRow['id'] ?? 0);
    }

    // Show the supplier's own low and zero stock items in the dashboard alerts.
    if ($supplierId > 0) {
        $lowThreshold = getLowStockThreshold($conn);
        $lowStockStmt = $conn->prepare(
            "SELECT m.name, si.quantity
             FROM supplier_inventory si
             INNER JOIN medicines m ON m.id = si.medicine_id
             WHERE si.supplier_id = ? AND si.quantity > 0 AND si.quantity <= ?
             ORDER BY si.quantity ASC, m.name ASC
             LIMIT 10"
        );
        if ($lowStockStmt) {
            $lowStockStmt->bind_param('ii', $supplierId, $lowThreshold);
            $lowStockStmt->execute();
            $lowStockResult = $lowStockStmt->get_result();
            while ($row = $lowStockResult->fetch_assoc()) {
                $alerts[] = [
                    'type' => 'supplier_low_stock',
                    'message' => "Low supplier stock: {$row['name']} has {$row['quantity']} units left",
                    'timestamp' => date('Y-m-d H:i:s'),
                    'medicine' => $row['name'],
                    'quantity' => (int)$row['quantity']
                ];
            }
            $lowStockStmt->close();
        }

        $zeroStockStmt = $conn->prepare(
            "SELECT DISTINCT m.name
             FROM supplier_medicines sm
             INNER JOIN medicines m ON m.id = sm.medicine_id
             LEFT JOIN supplier_inventory si
                    ON si.supplier_id = sm.supplier_id AND si.medicine_id = sm.medicine_id
             WHERE sm.supplier_id = ? AND COALESCE(si.quantity, 0) <= 0
             ORDER BY m.name ASC
             LIMIT 10"
        );
        if ($zeroStockStmt) {
            $zeroStockStmt->bind_param('i', $supplierId);
            $zeroStockStmt->execute();
            $zeroStockResult = $zeroStockStmt->get_result();
            while ($row = $zeroStockResult->fetch_assoc()) {
                $alerts[] = [
                    'type' => 'supplier_out_of_stock',
                    'message' => "Out of supplier stock: {$row['name']} needs resupply",
                    'timestamp' => date('Y-m-d H:i:s'),
                    'medicine' => $row['name'],
                    'quantity' => 0
                ];
            }
            $zeroStockStmt->close();
        }
    }

    // Show active auto-reorder requests assigned to this supplier.
    $supplierStmt = $conn->prepare(
        "SELECT o.id, m.name AS medicine_name, oi.quantity, o.status, o.order_date
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         INNER JOIN medicines m ON m.id = oi.medicine_id
         INNER JOIN suppliers s ON s.id = o.supplier_id
         WHERE s.user_id = ?
           AND o.status IN ('pending', 'ordered', 'accepted')
           AND o.notes LIKE 'Auto-reorder triggered:%'
         ORDER BY o.order_date DESC, o.id DESC
         LIMIT 10"
    );
    if ($supplierStmt) {
        $supplierStmt->bind_param('i', $userId);
        $supplierStmt->execute();
        $supplierResult = $supplierStmt->get_result();
        while ($row = $supplierResult->fetch_assoc()) {
            $alerts[] = [
                'type' => 'resupply_needed',
                'message' => "Resupply needed: {$row['medicine_name']} - {$row['quantity']} units (Order #{$row['id']})",
                'timestamp' => $row['order_date'],
                'medicine' => $row['medicine_name'],
                'quantity' => (int)$row['quantity'],
                'order_id' => (int)$row['id'],
                'status' => $row['status']
            ];
        }
        $supplierStmt->close();
    }
    
    // Check if we should send notifications based on frequency
    if (!shouldSendNotifications($conn)) {
        echo json_encode([
            'success' => true,
            'message' => 'Notifications skipped due to frequency settings',
            'alerts_created' => 0,
            'alerts' => $alerts
        ]);
        exit();
    }
    
    // 1. Check for LOW STOCK alerts
    if (isNotificationEnabled($conn, 'low_stock')) {
        $lowThreshold = getLowStockThreshold($conn);
        $criticalThreshold = getCriticalStockThreshold($conn);
        
        // Critical stock items
        $stmt = $conn->prepare("
            SELECT id, name, quantity 
            FROM medicines 
            WHERE quantity <= ?
        ");
        $stmt->bind_param("i", $criticalThreshold);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $message = "CRITICAL STOCK: {$row['name']} has only {$row['quantity']} units left";
            if (createNotification($conn, $message, 'shortage')) {
                $alertsCreated++;
                $alerts[] = ['type' => 'critical_stock', 'medicine' => $row['name'], 'quantity' => $row['quantity']];
            }
        }
        $stmt->close();
        
        // Low stock items (not critical)
        $stmt = $conn->prepare("
            SELECT id, name, quantity 
            FROM medicines 
            WHERE quantity <= ? AND quantity > ?
        ");
        $stmt->bind_param("ii", $lowThreshold, $criticalThreshold);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $message = "Low stock warning: {$row['name']} has {$row['quantity']} units left";
            if (createNotification($conn, $message, 'shortage')) {
                $alertsCreated++;
                $alerts[] = ['type' => 'low_stock', 'medicine' => $row['name'], 'quantity' => $row['quantity']];
            }
        }
        $stmt->close();
    }
    
    // 2. Check for EXPIRY alerts
    if (isNotificationEnabled($conn, 'expiry')) {
        $alertDays = getExpiryAlertDays($conn);
        $alertDate = date('Y-m-d', strtotime("+$alertDays days"));
        
        // Expired items
        $stmt = $conn->prepare("
            SELECT id, name, expiry_date 
            FROM medicines 
            WHERE expiry_date < CURDATE()
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $formattedDate = formatUserDate($conn, $row['expiry_date']);
            $message = "EXPIRED: {$row['name']} expired on {$formattedDate}";
            if (createNotification($conn, $message, 'expiry')) {
                $alertsCreated++;
                $alerts[] = ['type' => 'expired', 'medicine' => $row['name'], 'expiry_date' => $row['expiry_date']];
            }
        }
        $stmt->close();
        
        // Expiring soon items
        $stmt = $conn->prepare("
            SELECT id, name, expiry_date 
            FROM medicines 
            WHERE expiry_date <= ? AND expiry_date >= CURDATE()
        ");
        $stmt->bind_param("s", $alertDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $formattedDate = formatUserDate($conn, $row['expiry_date']);
            $daysUntil = ceil((strtotime($row['expiry_date']) - time()) / 86400);
            $message = "Expiring soon: {$row['name']} expires on {$formattedDate} ({$daysUntil} days)";
            if (createNotification($conn, $message, 'expiry')) {
                $alertsCreated++;
                $alerts[] = ['type' => 'expiring_soon', 'medicine' => $row['name'], 'expiry_date' => $row['expiry_date'], 'days_until' => $daysUntil];
            }
        }
        $stmt->close();
    }
    
    // 3. Generate auto-reorder suggestions
    if (isAutoReorderEnabled($conn)) {
        $suggestions = generateReorderSuggestions($conn);
        
        if (!empty($suggestions)) {
            $suggestionCount = count($suggestions);
            $message = "Auto-reorder system: {$suggestionCount} medicine(s) need reordering";
            createNotification($conn, $message, '');
            $alertsCreated++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'alerts_created' => $alertsCreated,
        'alerts' => $alerts,
        'settings' => [
            'low_stock_threshold' => getLowStockThreshold($conn),
            'critical_stock_threshold' => getCriticalStockThreshold($conn),
            'expiry_alert_days' => getExpiryAlertDays($conn),
            'notification_frequency' => getNotificationFrequency($conn)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Check alerts error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to check alerts', 'success' => false]);
}

if ($conn) {
    $conn->close();
}
?>