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
    
    // Check if we should send notifications based on frequency
    if (!shouldSendNotifications($conn)) {
        echo json_encode([
            'success' => true,
            'message' => 'Notifications skipped due to frequency settings',
            'alerts_created' => 0
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