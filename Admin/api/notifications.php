<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $where = '';
    $params = [];
    $types = '';

    // Filter by read status (0 = unread/recent, 1 = read/old)
    if (isset($_GET['read'])) {
        $read = (int)$_GET['read'];
        $where .= " WHERE `read` = ?";
        $params[] = $read;
        $types .= 'i';
    }

    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search = '%' . $_GET['search'] . '%';
        $where .= ($where ? ' AND' : ' WHERE') . " (message LIKE ? OR type LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }

    if (isset($_GET['from_date']) && $_GET['from_date'] !== '') {
        $from_date = $_GET['from_date'] . ' 00:00:00';
        $where .= ($where ? ' AND' : ' WHERE') . " created_at >= ?";
        $params[] = $from_date;
        $types .= 's';
    }

    if (isset($_GET['to_date']) && $_GET['to_date'] !== '') {
        $to_date = $_GET['to_date'] . ' 23:59:59';
        $where .= ($where ? ' AND' : ' WHERE') . " created_at <= ?";
        $params[] = $to_date;
        $types .= 's';
    }

    // Get total count first
    $count_query = "SELECT COUNT(*) as total FROM notifications $where";
    $count_stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();

    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : getRecordsPerPage($conn);
    $offset = ($page - 1) * $limit;

    // Get notifications with pagination, ordered by created_at DESC
    $query = "SELECT id, message, type, `read`, created_at FROM notifications $where ORDER BY created_at DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $allParams = array_merge($params, [$limit, $offset]);
        $allTypes = $types . 'ii';
        $stmt->bind_param($allTypes, ...$allParams);
    } else {
        $stmt->bind_param('ii', $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();

    echo json_encode([
        'success' => true, 
        'data' => $notifications, 
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $limit,
        'total_pages' => ceil($total / $limit)
    ]);
    
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? null;
    $read = (int)$data['read'];
    
    if (isset($data['mark_all']) && $data['mark_all']) {
        // Mark all notifications as read/unread
        $stmt = $conn->prepare("UPDATE notifications SET `read` = ?");
        $stmt->bind_param('i', $read);
    } else {
        // Mark single notification
        $stmt = $conn->prepare("UPDATE notifications SET `read` = ? WHERE id = ?");
        $stmt->bind_param('ii', $read, $id);
    }
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
    
} elseif ($method === 'DELETE') {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param('i', $id);
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>