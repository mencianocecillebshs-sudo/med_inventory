<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/supplier_auth.php';

function sendDashboardResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendDashboardResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!$conn || $conn->connect_error) {
    sendDashboardResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

try {
    $supplierProfile = requireSupplierSession($conn);
    $supplierId = (int)$supplierProfile['id'];

    $stats = [
        'total_medicines' => 0,
        'total_units' => 0,
        'total_revenue' => 0.0,
        'lowStockItems' => [],
        'zeroStockItems' => [],
        'topMedicines' => [],
    ];

    $stmt = $conn->prepare("
        SELECT
            COUNT(DISTINCT medicine_id) AS total_medicines,
            COALESCE(SUM(quantity), 0) AS total_units
        FROM supplier_inventory
        WHERE supplier_id = ? AND quantity > 0
    ");
    if (!$stmt) {
        throw new Exception('Inventory totals prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $supplierId);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stats['total_medicines'] = (int)($totals['total_medicines'] ?? 0);
    $stats['total_units'] = (int)($totals['total_units'] ?? 0);

    $lowThreshold = function_exists('getLowStockThreshold') ? getLowStockThreshold($conn) : 10;
    $widgetLimit = function_exists('getRecordsPerPage') ? getRecordsPerPage($conn) : 25;

    $stmt = $conn->prepare("
        SELECT CONCAT(m.name, ' (', si.quantity, ')') AS item
        FROM supplier_inventory si
        INNER JOIN medicines m ON si.medicine_id = m.id
        WHERE si.supplier_id = ? AND si.quantity > 0 AND si.quantity <= ?
        ORDER BY si.quantity ASC, m.name ASC
        LIMIT ?
    ");
    if (!$stmt) {
        throw new Exception('Low-stock prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iii', $supplierId, $lowThreshold, $widgetLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['lowStockItems'][] = $row['item'];
    }
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT DISTINCT m.name AS item
        FROM supplier_medicines sm
        INNER JOIN medicines m ON sm.medicine_id = m.id
        LEFT JOIN supplier_inventory si
            ON si.supplier_id = sm.supplier_id
           AND si.medicine_id = sm.medicine_id
        WHERE sm.supplier_id = ? AND COALESCE(si.quantity, 0) <= 0
        ORDER BY m.name ASC
        LIMIT ?
    ");
    if (!$stmt) {
        throw new Exception('Zero-stock prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('ii', $supplierId, $widgetLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['zeroStockItems'][] = $row['item'] . ' (0)';
    }
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(line_total), 0) AS total_revenue
        FROM supplier_sales
        WHERE supplier_id = ?
    ");
    if (!$stmt) {
        throw new Exception('Revenue prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $supplierId);
    $stmt->execute();
    $revenue = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $stats['total_revenue'] = (float)($revenue['total_revenue'] ?? 0);

    $stmt = $conn->prepare("
        SELECT m.name AS medicine_name, si.quantity
        FROM supplier_inventory si
        INNER JOIN medicines m ON si.medicine_id = m.id
        WHERE si.supplier_id = ? AND si.quantity > 0
        ORDER BY si.quantity DESC, m.name ASC
        LIMIT ?
    ");
    if (!$stmt) {
        throw new Exception('Top-stock prepare failed: ' . $conn->error);
    }
    $topLimit = min(10, $widgetLimit);
    $stmt->bind_param('ii', $supplierId, $topLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stats['topMedicines'][] = [
            'medicine_name' => $row['medicine_name'],
            'quantity' => (int)$row['quantity'],
        ];
    }
    $stmt->close();

    sendDashboardResponse([
        'success' => true,
        'supplier_id' => $supplierId,
        'data' => $stats,
    ]);
} catch (Exception $e) {
    error_log('Supplier dashboard stats error: ' . $e->getMessage());
    sendDashboardResponse(['success' => false, 'message' => 'Failed to load dashboard stats'], 500);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
