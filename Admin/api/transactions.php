<?php
// api/transactions.php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
    exit();
}
require_once '../config/db.php';

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    ob_clean();
    echo json_encode($data);
    ob_end_flush();
    exit;
}

if (!isset($_SESSION['user_id'])) {
    sendResponse(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        handleGet($conn);
    } else {
        sendResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Transaction API Error: " . $e->getMessage());
    sendResponse(['error' => 'Internal server error: ' . $e->getMessage()], 500);
}

function handleGet($conn) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($id > 0) {
        // Single transaction — LEFT JOIN users so NULL user_id doesn't break it
        $query = "SELECT t.*,
                        COALESCE(m.name, 'Deleted Item') AS medicine_name,
                        COALESCE(u.username, 'Unknown')      AS username,
                        up.username                          AS pharmacist_name,
                        p.purchase_number
                  FROM transactions t
                  LEFT JOIN medicines m  ON t.medicine_id    = m.id
                  LEFT JOIN users u      ON t.user_id        = u.id
                  LEFT JOIN users up     ON t.pharmacist_id  = up.id
                  LEFT JOIN purchases p ON t.purchase_id = p.id
                  WHERE t.id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) sendResponse(['error' => 'DB prepare error: ' . $conn->error], 500);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) sendResponse($row);
        sendResponse(['error' => 'Transaction not found'], 404);
    }

    // ── List ──────────────────────────────────────────────────────────────
    $page     = isset($_GET['page'])     ? max(1, (int)$_GET['page'])     : 1;
    $per_page = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
    $offset   = ($page - 1) * $per_page;

    $search            = isset($_GET['search'])              ? trim($_GET['search'])              : '';

    $where = ['1=1'];
    $params = [];
    $types = '';
    if ($search !== '') {
        $where[] = "(t.notes LIKE ? OR t.reason LIKE ? OR m.name LIKE ? OR u.username LIKE ? OR up.username LIKE ? OR p.purchase_number LIKE ?)";
        $like = "%$search%";
        for ($i = 0; $i < 6; $i++) { $params[] = $like; $types .= 's'; }
    }

    $where_sql = implode(' AND ', $where);

    // All JOINs are LEFT so NULL foreign keys don't drop rows
    $base_joins = "FROM transactions t
                   LEFT JOIN medicines m     ON t.medicine_id      = m.id
                   LEFT JOIN users u         ON t.user_id          = u.id
                   LEFT JOIN users up        ON t.pharmacist_id    = up.id
                   LEFT JOIN purchases p ON t.purchase_id  = p.id";

    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total $base_joins WHERE $where_sql");
    if (!$count_stmt) sendResponse(['error' => 'Count query failed: ' . $conn->error], 500);
    if ($params) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = (int)$count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    $list_query = "SELECT t.*,
                          COALESCE(m.name, 'Deleted Item') AS medicine_name,
                          COALESCE(u.username, 'Unknown')      AS username,
                          up.username                          AS pharmacist_name,
                          p.purchase_number
                   $base_joins
                   WHERE $where_sql
                   ORDER BY t.timestamp DESC, t.id DESC
                   LIMIT ? OFFSET ?";

    $list_stmt = $conn->prepare($list_query);
    if (!$list_stmt) sendResponse(['error' => 'List query failed: ' . $conn->error], 500);
    $listParams = $params;
    $listTypes = $types . 'ii';
    $listParams[] = $per_page;
    $listParams[] = $offset;
    $list_stmt->bind_param($listTypes, ...$listParams);
    $list_stmt->execute();
    $result = $list_stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $list_stmt->close();

    sendResponse([
        'data'     => $transactions,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'has_more' => $offset + $per_page < $total
    ]);
}

ob_end_flush();
