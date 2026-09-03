<?php
// config/db.php - Database connection with proper error handling
$host = 'localhost';
$db = 'med_inventory';
$user = 'root';
$pass = '';

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    error_log("DB Connection failed: " . $conn->connect_error);
    
    // For API calls, don't die - just set conn to null
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Database connection failed', 'success' => false]);
            exit();
        }
    }
    $conn = null;
} else {
    // Set charset to UTF-8
    $conn->set_charset("utf8mb4");
    
    // Set timezone
    $conn->query("SET time_zone = '+08:00'");
}

// Include settings helper if connection successful
if ($conn && file_exists(__DIR__ . '/settings_helper.php')) {
    require_once __DIR__ . '/settings_helper.php';
}
?>