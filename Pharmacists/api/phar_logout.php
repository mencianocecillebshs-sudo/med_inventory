<?php
// File: api/phar_logout.php  (← Make sure this is in /api/, NOT in /Admin/)
session_start();
header('Content-Type: application/json');

require_once '../includes/activity_logger.php';

$user_id = $_SESSION['user_id'] ?? null;

if ($user_id) {
    logLogout($user_id);
}

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

// ALWAYS redirect to root index.php using full relative-to-root path
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully',
    'redirect' => '../index.php'  // This works from /api/ or /Admin/api/
    // Or use absolute: '/med_inventory_system/index.php'
]);
?>