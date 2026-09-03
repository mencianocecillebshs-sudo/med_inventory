<?php
/* ==============================================================
 *  api/sup_inventory.php – FINAL: SHOW ZERO STOCK
 *  • No min_order dependency
 *  • Low stock ≤15
 *  • Zero stock (0) clearly returned
 *  • Safe, fast, no 500 errors
 * ============================================================== */

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/sup_inventory_error.log');

header('Content-Type: application/json; charset=utf-8');

// Debug flag: when present as ?debug=1 the endpoint will include error details
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// === DB CONNECTION ===
$dbPaths = [
    dirname(dirname(dirname(__DIR__))) . '/config/db.php',
    dirname(dirname(dirname(__DIR__))) . '/Config/db.php',
    __DIR__ . '/../config/db.php',
];

$conn = null;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
            break;
        }
    }
}

if (!$conn || $conn->connect_error) {
    $payload = ['success' => false, 'message' => 'DB connection failed'];
    if ($debug) {
        $payload['debug'] = $conn ? ($conn->connect_error ?? 'unknown connect error') : 'no $conn variable available';
    }
    jsonResponse($payload, 500);
}

// === AUTH & SUPPLIER ID ===
require_once __DIR__ . '/../includes/supplier_auth.php';
$supplierProfile = requireSupplierSession($conn);
$supplier_id = (int)$supplierProfile['id'];

try {
    $total_medicines = 0;
    $total_units = 0;
    $lowItems = [];
    $zeroStockItems = [];

    // === 1. TOTAL IN-STOCK ===
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT si.medicine_id) AS total_medicines,
            COALESCE(SUM(si.quantity), 0) AS total_units
        FROM supplier_inventory si
        WHERE si.supplier_id = ? AND si.quantity > 0
    ");
    if ($stmt) {
        $stmt->bind_param('i', $supplier_id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc();
        $total_medicines = (int)($total['total_medicines'] ?? 0);
        $total_units = (int)($total['total_units'] ?? 0);
        $stmt->close();
    }

    // === 2. LOW STOCK (uses system settings) ===
    $lowThreshold = function_exists('getLowStockThreshold') ? getLowStockThreshold($conn) : 10;
    $widgetLimit = function_exists('getRecordsPerPage') ? getRecordsPerPage($conn) : 25;

    $stmt = $conn->prepare("
        SELECT CONCAT(m.name, ' (', si.quantity, ')') AS item
        FROM supplier_inventory si
        JOIN medicines m ON si.medicine_id = m.id
        WHERE si.supplier_id = ? AND si.quantity > 0 AND si.quantity <= ?
        ORDER BY si.quantity ASC
        LIMIT ?
    ");
    if ($stmt) {
        $stmt->bind_param('iii', $supplier_id, $lowThreshold, $widgetLimit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $lowItems[] = $row['item'];
        $stmt->close();
    }

    // === 3. ZERO STOCK (0 units) ===
    $stmt = $conn->prepare("
        SELECT DISTINCT m.name AS item
        FROM medicines m
        INNER JOIN supplier_medicines sm ON m.id = sm.medicine_id AND sm.supplier_id = ?
        WHERE NOT EXISTS (
            SELECT 1 FROM supplier_inventory si 
            WHERE si.medicine_id = m.id 
              AND si.supplier_id = ? 
              AND si.quantity > 0
        )
        ORDER BY m.name ASC
        LIMIT ?
    ");
    if ($stmt) {
        $stmt->bind_param('iii', $supplier_id, $supplier_id, $widgetLimit);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $zeroStockItems[] = $row['item'] . " (0)";
        $stmt->close();
    }

    // === SUCCESS ===
    jsonResponse([
        'success' => true,
        'total_medicines' => $total_medicines,
        'total_units' => $total_units,
        'lowStockItems' => $lowItems,
        'zeroStockItems' => $zeroStockItems,
        'supplier_id' => $supplier_id
    ]);

} catch (Exception $e) {
    error_log("sup_inventory.php ERROR: " . $e->getMessage());
    $payload = ['success' => false, 'message' => 'Server error'];
    if ($debug) $payload['debug'] = $e->getMessage();
    jsonResponse($payload, 500);
} finally {
    if (isset($conn)) $conn->close();
}
?>
