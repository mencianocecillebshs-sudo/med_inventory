<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (($_SESSION['role'] ?? '') !== 'supplier') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Supplier access only']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || ($_GET['current_user'] ?? '') !== 'true') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User management is not available in the supplier workspace']);
    exit();
}

try {
    $db = new PDO('mysql:host=localhost;dbname=med_inventory;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare('
        SELECT u.id, u.username, u.name, u.role, s.id AS supplier_id, s.company
        FROM users u
        LEFT JOIN suppliers s ON s.user_id = u.id
        WHERE u.id = ? AND u.role = "supplier"
        LIMIT 1
    ');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Supplier user not found']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'name' => $user['name'] ?: $user['username'],
        'role' => $user['role'],
        'supplier_id' => isset($user['supplier_id']) ? (int)$user['supplier_id'] : null,
        'company' => $user['company'] ?? ''
    ]);
} catch (PDOException $e) {
    error_log('Supplier user API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
