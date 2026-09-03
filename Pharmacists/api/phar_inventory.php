<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/db.php';
require_once '../config/settings_helper.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'total' => 0,
        'lowStockCount' => 0,
        'lowStockItems' => [],
        'expiringItems' => []
    ]);
    exit();
}

try {
    // Get total medicines
    $totalResult = $conn->query("SELECT COUNT(*) FROM medicines");
    if (!$totalResult) {
        throw new Exception("Failed to count medicines");
    }
    $total = (int)$totalResult->fetch_row()[0];

    // Get low stock threshold directly from settings for the logged-in user
    $userId = $_SESSION['user_id'];
    $thresholdStmt = $conn->prepare(
        "SELECT value FROM settings WHERE setting_key = 'low_stock_threshold' AND user_id = ? LIMIT 1"
    );
    if (!$thresholdStmt) {
        throw new Exception("Failed to prepare threshold query");
    }
    $thresholdStmt->bind_param('i', $userId);
    $thresholdStmt->execute();
    $thresholdResult = $thresholdStmt->get_result();
    $thresholdRow = $thresholdResult->fetch_assoc();
    $thresholdStmt->close();

    // Use user-specific threshold, fallback to 10 if not set or invalid
    $threshold = ($thresholdRow && is_numeric($thresholdRow['value']) && (int)$thresholdRow['value'] > 0)
        ? (int)$thresholdRow['value']
        : 10;

    // Get low stock medicines
    $lowResult = $conn->query(
        "SELECT name, quantity, reorder_point
         FROM medicines
         WHERE quantity <= COALESCE(NULLIF(reorder_point, 0), $threshold)
         ORDER BY quantity ASC"
    );
    if (!$lowResult) {
        throw new Exception("Failed to query low stock medicines");
    }

    $lowStockItems = [];
    while ($row = $lowResult->fetch_assoc()) {
        $lowStockItems[] = [
            'name'          => $row['name'],
            'quantity'      => (int)$row['quantity'],
            'reorder_point' => (int)$row['reorder_point'],
        ];
    }

    $lowStockCount = count($lowStockItems);

    // -------------------------------------------------------------------
    // Get expiring medicines — within the next 180 days, ordered soonest first.
    // Labels:
    //   expired  = expiry_date < today
    //   critical = expires within 30 days
    //   warning  = expires within 31–90 days
    //   soon     = expires within 91–180 days
    // -------------------------------------------------------------------
    $expiringResult = $conn->query(
        "SELECT name, quantity, expiry_date,
                DATEDIFF(expiry_date, CURDATE()) AS days_until_expiry
         FROM medicines
         WHERE expiry_date IS NOT NULL
           AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 180 DAY)
         ORDER BY expiry_date ASC"
    );
    if (!$expiringResult) {
        throw new Exception("Failed to query expiring medicines");
    }

    $expiringItems = [];
    while ($row = $expiringResult->fetch_assoc()) {
        $days = (int)$row['days_until_expiry'];
        if ($days < 0) {
            $status = 'expired';
        } elseif ($days <= 30) {
            $status = 'critical';
        } elseif ($days <= 90) {
            $status = 'warning';
        } else {
            $status = 'soon';
        }
        $expiringItems[] = [
            'name'        => $row['name'],
            'quantity'    => (int)$row['quantity'],
            'expiry_date' => $row['expiry_date'],
            'days'        => $days,
            'status'      => $status,
        ];
    }

    // Return successful response
    echo json_encode([
        'success'       => true,
        'total'         => $total,
        'lowStockCount' => $lowStockCount,
        'lowStockItems' => $lowStockItems,
        'expiringItems' => $expiringItems,
        'threshold'     => $threshold
    ]);

} catch (Exception $e) {
    error_log("Inventory API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load inventory: ' . $e->getMessage(),
        'total' => 0,
        'lowStockCount' => 0,
        'lowStockItems' => [],
        'expiringItems' => []
    ]);
}

if ($conn) {
    $conn->close();
}
?>