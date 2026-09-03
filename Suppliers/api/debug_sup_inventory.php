<?php
// Temporary debug endpoint - not for production use
header('Content-Type: application/json; charset=utf-8');
session_start();

$out = ['session' => $_SESSION ?? null, 'errors' => []];

// Attempt DB connect using same resolution logic as other APIs
$dbPaths = [
    __DIR__ . '/../../Config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../config/db.php'
];

$dbConnected = false;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        if (isset($conn) && $conn && !$conn->connect_error) {
            $dbConnected = true;
            $out['db_path'] = $path;
            break;
        }
    }
}

if (!$dbConnected) {
    $out['errors'][] = 'Unable to connect to DB using expected paths.';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Show current logged-in user id and role
$out['session_user_id'] = $_SESSION['user_id'] ?? null;
$out['session_role'] = $_SESSION['role'] ?? null;

// If no session user, stop here
if (!isset($_SESSION['user_id'])) {
    $out['errors'][] = 'No session user_id present.';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Try to find supplier profile
require_once __DIR__ . '/../includes/supplier_auth.php';
try {
    $supplier = findSupplierForUser($conn, (int)$_SESSION['user_id'], (string)($_SESSION['username'] ?? ''), (string)($_SESSION['name'] ?? ''));
    $out['supplier_lookup'] = $supplier ?: null;
} catch (Exception $e) {
    $out['errors'][] = 'supplier lookup failed: ' . $e->getMessage();
}

if ($supplier && isset($supplier['id'])) {
    $sid = (int)$supplier['id'];
    // Count supplier_medicines
    $stmt = $conn->prepare('SELECT COUNT(*) as c FROM supplier_medicines WHERE supplier_id = ?');
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $out['supplier_medicines_count'] = (int)($c['c'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare('SELECT COUNT(*) as c FROM supplier_inventory WHERE supplier_id = ?');
    $stmt->bind_param('i', $sid);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $out['supplier_inventory_count'] = (int)($c['c'] ?? 0);
    $stmt->close();

    // Show a few sample joined rows
    $stmt = $conn->prepare('SELECT m.id, m.name, COALESCE(si.quantity,0) as qty, COALESCE(sm.unit_price,0) as price FROM medicines m LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND sm.supplier_id = ? LEFT JOIN supplier_inventory si ON m.id = si.medicine_id AND si.supplier_id = ? WHERE m.id IN (SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ? UNION SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ?) LIMIT 10');
    $stmt->bind_param('iiii', $sid, $sid, $sid, $sid);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $out['sample_medicines'] = $rows;
    $stmt->close();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
