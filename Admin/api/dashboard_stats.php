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

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Check if auto-reorder is enabled
        if (!isAutoReorderEnabled($conn)) {
            echo json_encode([
                'success' => true,
                'enabled' => false,
                'message' => 'Auto-reorder system is disabled',
                'suggestions' => []
            ]);
            exit();
        }
        
        // Generate reorder suggestions
        $suggestions = generateReorderSuggestions($conn);
        
        echo json_encode([
            'success' => true,
            'enabled' => true,
            'count' => count($suggestions),
            'suggestions' => $suggestions,
            'settings' => [
                'low_stock_threshold' => getLowStockThreshold($conn),
                'critical_stock_threshold' => getCriticalStockThreshold($conn)
            ]
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed', 'success' => false]);
    }
    
} catch (Exception $e) {
    error_log("Reorder suggestions error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate suggestions', 'success' => false]);
}

if ($conn) {
    $conn->close();
}
?>