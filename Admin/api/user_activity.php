<?php
// api/user_activity.php
// CRITICAL: Start session and set headers BEFORE any output
session_start();
require_once '../includes/activity_logger.php'; // This includes the function

// Clear any output buffer and set JSON header
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Enable error logging (disable displaying errors)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error_log.txt'); // Log to a file
error_reporting(E_ALL);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'med_inventory');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'fetch':
            fetchUserActivities($conn);
            break;
        case 'filter':
            filterActivities($conn);
            break;
        case 'export':
            exportActivities($conn);
            break;
        case 'stats':
            getActivityStats($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("User Activity Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}

$conn->close();
exit();

function fetchUserActivities($conn) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $filter_type = $_GET['filter_type'] ?? null;

    // Base SQL query
    $sql = "SELECT 
                ua.id, 
                ua.user_id, 
                COALESCE(u.username, 'Unknown') as username, 
                COALESCE(u.name, 'N/A') as name, 
                COALESCE(u.role, 'N/A') as role, 
                ua.action_type,
                ua.ip_address, 
                ua.user_agent, 
                ua.session_duration,
                ua.login_time,
                ua.logout_time,
                ua.created_at
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id";
    
    // Add filter if quick filter is applied
    if ($filter_type) {
        $sql .= " WHERE ua.action_type = ?";
    }
    
    $sql .= " ORDER BY ua.created_at DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
        return;
    }
    
    if ($filter_type) {
        $stmt->bind_param("sii", $filter_type, $limit, $offset);
    } else {
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'name' => $row['name'],
            'role' => $row['role'],
            'action_type' => $row['action_type'],
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'session_duration' => $row['session_duration'],
            'login_time' => $row['login_time'],
            'logout_time' => $row['logout_time'],
            'created_at' => $row['created_at']
        ];
    }

    // Get total count (with filter if applied)
    if ($filter_type) {
        $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM user_activity WHERE action_type = ?");
        $countStmt->bind_param("s", $filter_type);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
    } else {
        $countResult = $conn->query("SELECT COUNT(*) as total FROM user_activity");
    }
    
    $total = 0;
    if ($countResult) {
        $countRow = $countResult->fetch_assoc();
        $total = $countRow['total'];
    }

    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'total' => $total
    ]);
}

function filterActivities($conn) {
    $user_id = $_POST['user_id'] ?? null;
    $action_type = $_POST['action_type'] ?? null;
    $date_from = $_POST['date_from'] ?? null;
    $date_to = $_POST['date_to'] ?? null;
    $role = $_POST['role'] ?? null;

    $sql = "SELECT 
                ua.id, 
                ua.user_id, 
                COALESCE(u.username, 'Unknown') as username, 
                COALESCE(u.name, 'N/A') as name, 
                COALESCE(u.role, 'N/A') as role, 
                ua.action_type,
                ua.ip_address, 
                ua.user_agent, 
                ua.session_duration,
                ua.login_time,
                ua.logout_time,
                ua.created_at
            FROM user_activity ua
            LEFT JOIN users u ON ua.user_id = u.id
            WHERE 1=1";

    $params = [];
    $types = "";

    if ($user_id) { 
        $sql .= " AND ua.user_id = ?"; 
        $params[] = $user_id; 
        $types .= "i"; 
    }
    if ($action_type) { 
        $sql .= " AND ua.action_type = ?"; 
        $params[] = $action_type; 
        $types .= "s"; 
    }
    if ($role) { 
        $sql .= " AND u.role = ?"; 
        $params[] = $role; 
        $types .= "s"; 
    }
    if ($date_from) { 
        $sql .= " AND DATE(ua.created_at) >= ?"; 
        $params[] = $date_from; 
        $types .= "s"; 
    }
    if ($date_to) { 
        $sql .= " AND DATE(ua.created_at) <= ?"; 
        $params[] = $date_to; 
        $types .= "s"; 
    }

    $sql .= " ORDER BY ua.created_at DESC LIMIT 500";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
            return;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }

    $activities = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $activities[] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'username' => $row['username'],
                'name' => $row['name'],
                'role' => $row['role'],
                'action_type' => $row['action_type'],
                'ip_address' => $row['ip_address'],
                'user_agent' => $row['user_agent'],
                'session_duration' => $row['session_duration'],
                'login_time' => $row['login_time'],
                'logout_time' => $row['logout_time'],
                'created_at' => $row['created_at']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'count' => count($activities)
    ]);
}

function exportActivities($conn) {
    $format = $_GET['format'] ?? 'csv';
    
    if ($format === 'csv') {
        // Set CSV headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="user_activities_' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Write CSV headers
        fputcsv($output, ['ID', 'User ID', 'Username', 'Name', 'Role', 'Action Type', 'IP Address', 'User Agent', 'Session Duration (s)', 'Login Time', 'Logout Time', 'Created At']);
        
        // Fetch all activities
        $sql = "SELECT 
                    ua.id, 
                    ua.user_id, 
                    COALESCE(u.username, 'Unknown') as username, 
                    COALESCE(u.name, 'N/A') as name, 
                    COALESCE(u.role, 'N/A') as role, 
                    ua.action_type,
                    ua.ip_address, 
                    ua.user_agent, 
                    ua.session_duration,
                    ua.login_time,
                    ua.logout_time,
                    ua.created_at
                FROM user_activity ua
                LEFT JOIN users u ON ua.user_id = u.id
                ORDER BY ua.created_at DESC";
        
        $result = $conn->query($sql);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['id'],
                    $row['user_id'],
                    $row['username'],
                    $row['name'],
                    $row['role'],
                    $row['action_type'],
                    $row['ip_address'],
                    $row['user_agent'],
                    $row['session_duration'],
                    $row['login_time'],
                    $row['logout_time'],
                    $row['created_at']
                ]);
            }
        }
        
        fclose($output);
        exit();
    }
}

function getActivityStats($conn) {
    try {
        // Total activities
        $total = 0;
        $totalResult = $conn->query("SELECT COUNT(*) as total FROM user_activity");
        if ($totalResult && $row = $totalResult->fetch_assoc()) {
            $total = (int)$row['total'];
        }

        // Today's logins
        $todayLogins = 0;
        $todayLoginsResult = $conn->query("SELECT COUNT(*) as count FROM user_activity WHERE action_type = 'login' AND DATE(created_at) = CURDATE()");
        if ($todayLoginsResult && $row = $todayLoginsResult->fetch_assoc()) {
            $todayLogins = (int)$row['count'];
        }

        // Active users now - Users who logged in but haven't logged out yet
        $activeUsers = 0;
        $activeUsersQuery = "SELECT COUNT(DISTINCT ua1.user_id) as active_count
                            FROM user_activity ua1
                            WHERE ua1.action_type = 'login'
                            AND NOT EXISTS (
                                SELECT 1 FROM user_activity ua2
                                WHERE ua2.user_id = ua1.user_id
                                AND ua2.action_type = 'logout'
                                AND ua2.created_at > ua1.created_at
                            )
                            AND ua1.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $activeUsersResult = $conn->query($activeUsersQuery);
        if ($activeUsersResult && $row = $activeUsersResult->fetch_assoc()) {
            $activeUsers = (int)$row['active_count'];
        }

        // Average session duration
        $avgDuration = 0;
        $avgDurationResult = $conn->query("SELECT COALESCE(AVG(session_duration), 0) as avg_duration FROM user_activity WHERE session_duration IS NOT NULL AND session_duration > 0");
        if ($avgDurationResult && $row = $avgDurationResult->fetch_assoc()) {
            $avgDuration = (float)$row['avg_duration'];
        }

        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'today_logins' => $todayLogins,
                'active_users' => $activeUsers,
                'avg_session_duration' => round($avgDuration, 2)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Stats Error: " . $e->getMessage());
        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => 0,
                'today_logins' => 0,
                'active_users' => 0,
                'avg_session_duration' => 0
            ]
        ]);
    }
}
?>