<?php
// Local-only debug helper to impersonate a supplier for troubleshooting.
// WARNING: Do not leave this in production. Accessible only from localhost.

// Allow: http://localhost/.../dev_impersonate_supplier.php?username=supplier1

if (php_sapi_name() === 'cli') {
    echo "Run via browser.";
    exit;
}

// Restrict to localhost
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

session_start();
header('Content-Type: application/json; charset=utf-8');

$username = trim($_GET['username'] ?? '');
if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'username required']);
    exit;
}

// Try to load DB
$dbPath1 = __DIR__ . '/../config/db.php';
$dbPath2 = __DIR__ . '/../../config/db.php';
if (file_exists($dbPath1)) require_once $dbPath1;
elseif (file_exists($dbPath2)) require_once $dbPath2;
else {
    echo json_encode(['success' => false, 'message' => 'DB config not found']);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$stmt = $conn->prepare('SELECT id, username, name, role FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed', 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Force session as this user
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['name'] = $user['name'] ?: $user['username'];
$_SESSION['role'] = strtolower(trim((string)$user['role']));

// Ensure supplier profile exists and set supplier_id
require_once __DIR__ . '/../includes/supplier_auth.php';
$supplier = findSupplierForUser($conn, (int)$_SESSION['user_id'], $_SESSION['username'], $_SESSION['name']);
if ($supplier && isset($supplier['id'])) {
    $_SESSION['supplier_id'] = (int)$supplier['id'];
}

echo json_encode(['success' => true, 'message' => 'Impersonation active', 'user' => $_SESSION]);
exit;

?>
