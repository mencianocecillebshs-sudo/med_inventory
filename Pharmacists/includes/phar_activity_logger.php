<?php
/**
 * Activity Logger Helper
 * Place this file in: Pharmacists/includes/phar_activity_logger.php
 * Updated to handle failed logins for non-existent users
 */

function logUserActivity($user_id, $action_type, $additional_data = []) {
    // Validate user_id - if 0 or null and action is failed_login, skip foreign key
    if (($user_id === 0 || $user_id === null) && $action_type === 'failed_login') {
        return logFailedLoginUnknownUser($additional_data['username'] ?? 'Unknown');
    }
    
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        error_log("Activity Logger DB Connection failed: " . $conn->connect_error);
        return false;
    }
    
    // Get IP address
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Get session duration if logout
    $session_duration = null;
    if ($action_type === 'logout' && isset($_SESSION['login_time'])) {
        $session_duration = time() - $_SESSION['login_time'];
    }
    
    // Set login/logout times
    $login_time = ($action_type === 'login') ? date('Y-m-d H:i:s') : null;
    $logout_time = ($action_type === 'logout') ? date('Y-m-d H:i:s') : null;
    
    // Prepare SQL - matches your exact database structure
    $sql = "INSERT INTO user_activity 
            (user_id, action_type, ip_address, user_agent, session_duration, login_time, logout_time, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Activity Logger SQL preparation failed: " . $conn->error);
        $conn->close();
        return false;
    }
    
    $stmt->bind_param(
        "isssiss",
        $user_id,
        $action_type,
        $ip_address,
        $user_agent,
        $session_duration,
        $login_time,
        $logout_time
    );
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Activity Logger execution failed: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
    return $result;
}

/**
 * Log failed login for unknown user (no foreign key constraint)
 * Logs to a separate table or just logs to error log
 */
function logFailedLoginUnknownUser($username) {
    // Option 1: Log to error log file only (safest)
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_address = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    error_log("FAILED LOGIN ATTEMPT - Username: {$username}, IP: {$ip_address}, Time: {$timestamp}, User Agent: {$user_agent}");
    
    // Option 2: Create a separate failed_login_attempts table (optional)
    // This table doesn't need foreign key constraints
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        return true; // Already logged to error_log
    }
    
    // Check if failed_login_attempts table exists, if not create it
    $createTableSQL = "CREATE TABLE IF NOT EXISTS failed_login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_ip (ip_address),
        INDEX idx_time (attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($createTableSQL);
    
    // Insert failed attempt
    $sql = "INSERT INTO failed_login_attempts (username, ip_address, user_agent, attempt_time) 
            VALUES (?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sss", $username, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    return true;
}

/**
 * Log login activity
 */
function logLogin($user_id) {
    $_SESSION['login_time'] = time();
    return logUserActivity($user_id, 'login');
}

/**
 * Log logout activity
 */
function logLogout($user_id) {
    $result = logUserActivity($user_id, 'logout');
    unset($_SESSION['login_time']);
    return $result;
}

/**
 * Log failed login attempt
 * Updated to handle both existing and non-existing users
 */
function logFailedLogin($username) {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        error_log("Activity Logger DB Connection failed: " . $conn->connect_error);
        // Still log to file even if DB fails
        return logFailedLoginUnknownUser($username);
    }
    
    // Try to get user_id from username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        $conn->close();
        return logFailedLoginUnknownUser($username);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $user_id = null;
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
    }
    
    $stmt->close();
    $conn->close();
    
    // If user exists in database, log with their ID
    if ($user_id !== null && $user_id > 0) {
        return logUserActivity($user_id, 'failed_login');
    } else {
        // If user doesn't exist, log to separate table
        return logUserActivity(null, 'failed_login', ['username' => $username]);
    }
}

/**
 * Log password change
 */
function logPasswordChange($user_id) {
    return logUserActivity($user_id, 'password_change');
}

/**
 * Log profile update
 */
function logProfileUpdate($user_id) {
    return logUserActivity($user_id, 'profile_update');
}

/**
 * Get active sessions count
 */
function getActiveSessionsCount() {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        error_log("Activity Logger DB Connection failed: " . $conn->connect_error);
        return 0;
    }
    
    // Get users who logged in but haven't logged out yet (within last 24 hours)
    $sql = "SELECT COUNT(DISTINCT ua1.user_id) as active_count
            FROM user_activity ua1
            WHERE ua1.action_type = 'login'
            AND NOT EXISTS (
                SELECT 1 FROM user_activity ua2
                WHERE ua2.user_id = ua1.user_id
                AND ua2.action_type = 'logout'
                AND ua2.created_at > ua1.created_at
            )
            AND ua1.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $result = $conn->query($sql);
    $count = 0;
    
    if ($result && $row = $result->fetch_assoc()) {
        $count = $row['active_count'];
    }
    
    $conn->close();
    return $count;
}

/**
 * Get user's last activity
 */
function getLastActivity($user_id) {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT * FROM user_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    
    if (!$stmt) {
        $conn->close();
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activity = null;
    if ($row = $result->fetch_assoc()) {
        $activity = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $activity;
}

/**
 * Get failed login attempts for a username (from separate table)
 */
function getFailedLoginAttempts($username, $hours = 24) {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        return [];
    }
    
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'failed_login_attempts'");
    if ($tableCheck->num_rows === 0) {
        $conn->close();
        return [];
    }
    
    $sql = "SELECT * FROM failed_login_attempts 
            WHERE username = ? 
            AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY attempt_time DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $conn->close();
        return [];
    }
    
    $stmt->bind_param("si", $username, $hours);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attempts = [];
    while ($row = $result->fetch_assoc()) {
        $attempts[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $attempts;
}

/**
 * Clean old activity logs (optional - run via cron)
 * Keeps only last 90 days of logs
 */
function cleanOldActivityLogs($days = 90) {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        error_log("Activity Logger DB Connection failed: " . $conn->connect_error);
        return false;
    }
    
    // Clean user_activity table
    $sql = "DELETE FROM user_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Failed to prepare cleanup statement: " . $conn->error);
        $conn->close();
        return false;
    }
    
    $stmt->bind_param("i", $days);
    $result = $stmt->execute();
    
    if ($result) {
        $deleted = $stmt->affected_rows;
        error_log("Activity Logger: Cleaned {$deleted} old records from user_activity");
    }
    
    $stmt->close();
    
    // Clean failed_login_attempts table if it exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'failed_login_attempts'");
    if ($tableCheck->num_rows > 0) {
        $sql2 = "DELETE FROM failed_login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt2 = $conn->prepare($sql2);
        
        if ($stmt2) {
            $stmt2->bind_param("i", $days);
            $stmt2->execute();
            $deleted2 = $stmt2->affected_rows;
            error_log("Activity Logger: Cleaned {$deleted2} old records from failed_login_attempts");
            $stmt2->close();
        }
    }
    
    $conn->close();
    
    return $result;
}

/**
 * Insert sample activity data for testing
 */
function insertSampleActivityData() {
    $conn = new mysqli('localhost', 'root', '', 'med_inventory');
    
    if ($conn->connect_error) {
        return false;
    }
    
    $users = [1, 7, 2]; // Admin and pharmacist IDs from your database
    $actions = ['login', 'logout', 'profile_update'];
    $ips = ['192.168.1.1', '192.168.1.2', '10.0.0.1'];
    
    $sql = "INSERT INTO user_activity (user_id, action_type, ip_address, user_agent, session_duration, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // Insert 50 sample records
    for ($i = 0; $i < 50; $i++) {
        $user_id = $users[array_rand($users)];
        $action = $actions[array_rand($actions)];
        $ip = $ips[array_rand($ips)];
        $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Sample Browser';
        $duration = ($action === 'logout') ? rand(300, 3600) : null;
        $created = date('Y-m-d H:i:s', strtotime("-" . rand(0, 30) . " days"));
        
        $stmt->bind_param("isssis", $user_id, $action, $ip, $user_agent, $duration, $created);
        $stmt->execute();
    }
    
    $stmt->close();
    $conn->close();
    
    return true;
}
?>