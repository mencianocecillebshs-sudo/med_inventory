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
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
    $limit = max(1, min(30, $limit)); // Cap between 1 and 30
    
    // Get this supplier's actual sales data for the chart (last 30 days).
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as sale_date,
            SUM(quantity) as quantity
        FROM supplier_sales
        WHERE supplier_id = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY sale_date DESC
        LIMIT ?
    ");
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param('ii', $supplierId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'prescription_date' => $row['sale_date'],
            'date' => $row['sale_date'],
            'quantity' => intval($row['quantity'])
        ];
    }
    
    $stmt->close();
    
    // Reverse to show chronologically (oldest to newest)
    $data = array_reverse($data);
    
    // Return data
    echo json_encode($data);
    
} catch (Exception $e) {
    error_log("Chart data API error: " . $e->getMessage());
    
    // Return an empty live result on error; never fabricate analytics values.
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 7;
    echo json_encode([]);
}

if ($conn) {
    $conn->close();
}
?>