<?php
session_start();
require_once 'db.php';

// Include activity logger (make sure this file exists)
require_once 'Admin/includes/activity_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Username and password are required.';
    header('Location: index.php');
    exit();
}

try {
    $stmt = $conn->prepare("SELECT
                                u.id,
                                u.username,
                                u.name,
                                u.password,
                                u.role,
                                (
                                    SELECT s.id
                                    FROM suppliers s
                                    WHERE s.user_id = u.id
                                    ORDER BY s.id ASC
                                    LIMIT 1
                                ) AS supplier_id
                            FROM users u
                            WHERE u.username = ?
                            LIMIT 1");
    if (!$stmt) {
        throw new Exception('Database prepare error.');
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // === SUCCESSFUL LOGIN ===
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['name']      = $user['name'] ?? $user['username'];
            $_SESSION['role']      = strtolower(trim((string)$user['role'])); // Normalize
            
            // Store supplier_id if user is a supplier
            if ($_SESSION['role'] === 'supplier' && $user['supplier_id']) {
                $_SESSION['supplier_id'] = (int)$user['supplier_id'];
            }
            
            // Log successful login
            logLogin($user['id']);

            // === ROLE-BASED REDIRECTION ===
            $role = $_SESSION['role'];

            if ($role === 'admin') {
                header('Location: Admin/dashboard.php');
            } elseif ($role === 'pharmacist') {
                header('Location: pharmacists/phar_dashboard.php');
            } elseif ($role === 'staff') {
                header('Location: Staff/staff_dashboard.php');
            } elseif ($role === 'supplier') {
                header('Location: Suppliers/sup_dashboard.php');
            } else {
                // Unknown role
                logFailedLogin($username, "Invalid role: $role");
                $_SESSION['error'] = 'Access denied: Invalid user role.';
                header('Location: index.php');
            }
            exit();

        } else {
            // Wrong password
            logFailedLogin($username, 'Incorrect password');
            $_SESSION['error'] = 'Invalid password.';
            header('Location: index.php');
            exit();
        }
    } else {
        // User not found
        logFailedLogin($username, 'User not found');
        $_SESSION['error'] = 'Invalid username or password.';
        header('Location: index.php');
        exit();
    }

} catch (Exception $e) {
    error_log('Auth error: ' . $e->getMessage());
    $_SESSION['error'] = 'An unexpected error occurred. Please try again.';
    header('Location: index.php');
    exit();
}

// Close statement and connection
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();
?>
