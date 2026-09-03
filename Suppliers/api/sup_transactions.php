<?php
// api/sup_transactions.php - View supplier sales transactions from supplier_sales and sales_invoices tables
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

session_start();

// Function to send JSON response and exit
function sendResponse($data, $statusCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

// Database connection
$dbPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/config/db.php'
];

$conn = null;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!$conn) {
    // Fallback connection
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    if ($conn->connect_error) {
        error_log("sup_transactions.php: Database connection failed");
        sendResponse(['success' => false, 'error' => 'Database connection failed'], 500);
    }
}

$conn->set_charset("utf8mb4");

require_once __DIR__ . '/../includes/supplier_auth.php';
$supplierProfile = requireSupplierSession($conn);
$supplier_id = (int)$supplierProfile['id'];

$method = $_SERVER['REQUEST_METHOD'];

error_log("sup_transactions.php: Request method={$method}");

try {
    if ($method === 'GET') {
        handleGet($conn, $supplier_id);
    } else {
        sendResponse(['success' => false, 'error' => 'Method not allowed. This is view-only.'], 405);
    }
} catch (Exception $e) {
    error_log("Supplier Transaction API Error: " . $e->getMessage());
    sendResponse(['success' => false, 'error' => 'Internal server error: ' . $e->getMessage()], 500);
}

function handleGet($conn, $supplier_id) {
    require_once __DIR__ . '/pagination_helper.php';
    $pagination = supGetPaginationParams($conn);
    $page = $pagination['page'];
    $limit = $pagination['limit'];
    $offset = $pagination['offset'];

    $countSql = "SELECT COUNT(*) AS total FROM supplier_sales ss WHERE ss.supplier_id = ?";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        sendResponse(['success' => false, 'error' => 'Database prepare error: ' . $conn->error], 500);
    }
    $countStmt->bind_param('i', $supplier_id);
    $countStmt->execute();
    $totalItems = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $summarySql = "
        SELECT
            COALESCE(SUM(ss.line_total), 0) AS total_revenue,
            COALESCE(SUM(ss.unit_price * ss.quantity), 0) AS total_cost,
            COALESCE(SUM(ss.line_total - (ss.unit_price * ss.quantity)), 0) AS total_profit
        FROM supplier_sales ss
        WHERE ss.supplier_id = ?
    ";
    $summaryStmt = $conn->prepare($summarySql);
    if (!$summaryStmt) {
        sendResponse(['success' => false, 'error' => 'Database prepare error: ' . $conn->error], 500);
    }
    $summaryStmt->bind_param('i', $supplier_id);
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->get_result()->fetch_assoc() ?: ['total_revenue' => 0, 'total_profit' => 0];
    $summaryStmt->close();

    $query = "SELECT 
                ss.id,
                ss.medicine_id,
                COALESCE(m.name, 'Deleted Medicine') AS medicine_name,
                'sale' as action,
                ss.quantity,
                ss.line_total as total_cost,
                si.invoice_number,
                si.created_at as timestamp,
                ss.order_id,
                u.username as cashier_name
              FROM supplier_sales ss
              INNER JOIN sales_invoices si ON ss.invoice_id = si.id
              LEFT JOIN medicines m ON ss.medicine_id = m.id 
              LEFT JOIN users u ON si.cashier_id = u.id
              WHERE ss.supplier_id = ?
              ORDER BY si.created_at DESC, ss.id DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("sup_transactions.php: Prepare failed - " . $conn->error);
        sendResponse(['success' => false, 'error' => 'Database prepare error: ' . $conn->error], 500);
    }
    $stmt->bind_param('iii', $supplier_id, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        error_log("sup_transactions.php: Query failed - " . $conn->error);
        sendResponse(['success' => false, 'error' => 'Database query error: ' . $conn->error], 500);
    }
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure numeric types
        $row['id'] = (int)$row['id'];
        $row['medicine_id'] = (int)$row['medicine_id'];
        $row['quantity'] = (int)$row['quantity'];
        $row['total_cost'] = (float)$row['total_cost'];
        if ($row['order_id']) {
            $row['order_id'] = (int)$row['order_id'];
        }
        // Add notes for frontend logic
        $row['notes'] = ($row['order_id'] > 0) ? 'Fulfilled' : 'Walk-in sale';
        $transactions[] = $row;
    }
    
    error_log("sup_transactions.php: Found " . count($transactions) . " supplier sales transactions");
    
    sendResponse([
        'success' => true,
        'data' => $transactions,
        'summary' => [
            'total_revenue' => (float)($summaryRow['total_revenue'] ?? 0),
            'total_cost' => (float)($summaryRow['total_cost'] ?? 0),
            'total_profit' => (float)($summaryRow['total_profit'] ?? 0),
            'profit_margin' => (float)($summaryRow['total_revenue'] ?? 0) > 0
                ? round(((float)($summaryRow['total_profit'] ?? 0) / (float)$summaryRow['total_revenue']) * 100, 2)
                : 0,
        ],
        'total' => count($transactions),
        'pagination' => supBuildPaginationMeta($page, $limit, $totalItems),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

ob_end_flush();
?>
