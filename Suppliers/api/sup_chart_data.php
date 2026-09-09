<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/db.php';
require_once '../includes/supplier_auth.php';

// Check authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supplier') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit();
}

$supplierProfile = requireSupplierSession($conn);
$supplierId = (int)$supplierProfile['id'];

// Check database connection
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit();
}

try {
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');

    // Get this supplier's actual sales totals for each day this month.
    $stmt = $conn->prepare("
        SELECT 
        DATE(created_at) AS sale_date,
        COALESCE(SUM(line_total), 0) AS sales_total
        FROM supplier_sales
        WHERE supplier_id = ?
      AND created_at >= ?
      AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
    ORDER BY sale_date ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param('iss', $supplierId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $salesByDate = [];
    while ($row = $result->fetch_assoc()) {
        $salesByDate[$row['sale_date']] = (float)$row['sales_total'];
    }
    $stmt->close();

    $labels = [];
    $values = [];
    $currentDate = new DateTime($startDate);
    $lastDate = new DateTime($endDate);
    while ($currentDate <= $lastDate) {
        $date = $currentDate->format('Y-m-d');
        $labels[] = $currentDate->format('M j');
        $values[] = $salesByDate[$date] ?? 0;
        $currentDate->modify('+1 day');
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Sales',
                'data' => $values
            ]]
        ],
        'period' => [
            'start' => $startDate,
            'end' => $endDate
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Chart data API error: " . $e->getMessage());
    
    // Return an empty live result on error; never fabricate analytics values.
    echo json_encode([]);
}

if ($conn) {
    $conn->close();
}
?>