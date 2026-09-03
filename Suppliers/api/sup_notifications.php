<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'supplier') {
    http_response_code(403);
    echo json_encode(['error' => 'Supplier access only']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['unread_count'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND `read` = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode(['count' => (int)($row['count'] ?? 0)]);
        exit;
    }

    $where = ' WHERE user_id = ?';
    $params = [$userId];
    $types = 'i';

    // Filter by read status (0 = unread/recent, 1 = read/old)
    if (isset($_GET['read'])) {
        $read = (int)$_GET['read'];
        $where .= " AND `read` = ?";
        $params[] = $read;
        $types .= 'i';
    }

    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $search = '%' . $_GET['search'] . '%';
        $where .= " AND (message LIKE ? OR type LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }

    if (isset($_GET['from_date']) && $_GET['from_date'] !== '') {
        $from_date = $_GET['from_date'] . ' 00:00:00';
        $where .= " AND created_at >= ?";
        $params[] = $from_date;
        $types .= 's';
    }

    if (isset($_GET['to_date']) && $_GET['to_date'] !== '') {
        $to_date = $_GET['to_date'] . ' 23:59:59';
        $where .= " AND created_at <= ?";
        $params[] = $to_date;
        $types .= 's';
    }

    // Paginated notifications
    require_once __DIR__ . '/pagination_helper.php';
    $pagination = supGetPaginationParams($conn);
    $page = $pagination['page'];
    $limit = $pagination['limit'];
    $offset = $pagination['offset'];

    $countQuery = "SELECT COUNT(*) AS total FROM notifications $where";
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $totalItems = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $query = "SELECT id, message, type, `read`, created_at FROM notifications $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $listParams = $params;
    $listTypes = $types . 'ii';
    $listParams[] = $limit;
    $listParams[] = $offset;

    $stmt = $conn->prepare($query);
    if (!empty($listParams)) {
        $stmt->bind_param($listTypes, ...$listParams);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    echo json_encode([
        'notifications' => $notifications,
        'total' => $totalItems,
        'pagination' => supBuildPaginationMeta($page, $limit, $totalItems)
    ]);
    
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? null;
    $read = (int)$data['read'];
    
    if (isset($data['mark_all']) && $data['mark_all']) {
        // Mark all notifications as read/unread
        $stmt = $conn->prepare("UPDATE notifications SET `read` = ? WHERE user_id = ?");
        $stmt->bind_param('ii', $read, $userId);
    } else {
        // Mark single notification
        $stmt = $conn->prepare("UPDATE notifications SET `read` = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('iii', $read, $id, $userId);
    }
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
    
} elseif ($method === 'DELETE') {
    $id = $_GET['id'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $userId);
    $success = $stmt->execute();
    echo json_encode(['success' => $success]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
