<?php
session_start();
header('Content-Type: application/json');

// Check if session is started and user_id is set
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: No user session']);
    exit();
}

try {
    // Update with your actual database credentials
    $db = new PDO('mysql:host=localhost;dbname=med_inventory', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (isset($_GET['current_user']) && $_GET['current_user'] === 'true') {
        // Fetch current user's info, including name
        $stmt = $db->prepare('SELECT id, username, name, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode([
                'success' => true,
                'username' => $user['username'],
                'name' => $user['name'] ?? $user['username'],
                'role' => $user['role']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET' && !isset($_GET['id'])) {
        // Get all users
        $stmt = $db->query('SELECT id, username, name, role, created_at FROM users ORDER BY created_at DESC');
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        
    } elseif ($method === 'GET' && isset($_GET['id'])) {
        // Get single user
        $stmt = $db->prepare('SELECT id, username, name, role FROM users WHERE id = ?');
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode($user);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        
    } elseif ($method === 'POST') {
        // Add new user
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // Validate required fields
        if (empty($data['username']) || empty($data['password']) || empty($data['role'])) {
            echo json_encode(['success' => false, 'message' => 'Username, password, and role are required']);
            exit();
        }
        
        // Validate role
        $validRoles = ['admin', 'pharmacist', 'staff', 'supplier'];
        if (!in_array($data['role'], $validRoles)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role. Must be admin, pharmacist, staff, or supplier']);
            exit();
        }
        
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
            echo json_encode(['success' => false, 'message' => 'Username must be 3-50 characters (letters, numbers, underscore only)']);
            exit();
        }
        
        // Validate password strength
        if (strlen($data['password']) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            exit();
        }
        
        // Check if username already exists
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
        $stmt->execute([$data['username']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Insert user
            $stmt = $db->prepare('INSERT INTO users (username, name, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $data['username'],
                $data['name'] ?? $data['username'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role']
            ]);
            
            $userId = $db->lastInsertId();
            
            // Create default settings for the user
            $stmt = $db->prepare('INSERT INTO settings (user_id, setting_key, value) VALUES 
                (?, "low_stock_threshold", "10"),
                (?, "critical_stock_threshold", "5"),
                (?, "expiry_alert_days", "60"),
                (?, "notification_frequency", "daily")');
            $stmt->execute([$userId, $userId, $userId, $userId]);
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => 'User added successfully']);
            
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to add user: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'PUT') {
        // Update user
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit();
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // If data is not JSON, try to parse from form data
        if (!$data) {
            parse_str(file_get_contents('php://input'), $data);
        }
        
        // Validate required fields
        if (empty($data['username']) || empty($data['role'])) {
            echo json_encode(['success' => false, 'message' => 'Username and role are required']);
            exit();
        }
        
        // Validate role
        $validRoles = ['admin', 'pharmacist', 'staff', 'supplier'];
        if (!in_array($data['role'], $validRoles)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role. Must be admin, pharmacist, staff, or supplier']);
            exit();
        }
        
        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
            echo json_encode(['success' => false, 'message' => 'Username must be 3-50 characters (letters, numbers, underscore only)']);
            exit();
        }
        
        // Validate password if provided
        if (!empty($data['password']) && strlen($data['password']) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
            exit();
        }
        
        // Verify user exists before attempting update
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$_GET['id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
        
        // Check if username already exists for other users
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$data['username'], $_GET['id']]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit();
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update user
            if (!empty($data['password'])) {
                $stmt = $db->prepare('UPDATE users SET username = ?, name = ?, password = ?, role = ? WHERE id = ?');
                $stmt->execute([
                    $data['username'],
                    $data['name'] ?? $data['username'],
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['role'],
                    $_GET['id']
                ]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username = ?, name = ?, role = ? WHERE id = ?');
                $stmt->execute([
                    $data['username'],
                    $data['name'] ?? $data['username'],
                    $data['role'],
                    $_GET['id']
                ]);
            }
            
            $rowsAffected = $stmt->rowCount();
            
            // Commit the transaction
            $db->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'User updated successfully',
                'rows_affected' => $rowsAffected
            ]);
            
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()]);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete user
        if (!isset($_GET['id'])) {
            echo json_encode(['success' => false, 'message' => 'User ID is required']);
            exit();
        }
        
        // Prevent deleting yourself
        if ($_GET['id'] == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
            exit();
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // First, delete related settings
            $stmt = $db->prepare('DELETE FROM settings WHERE user_id = ?');
            $stmt->execute([$_GET['id']]);
            
            // Delete other related records
            $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = ?');
            $stmt->execute([$_GET['id']]);
            
            // Update orders to remove user reference
            $stmt = $db->prepare('UPDATE orders SET user_id = NULL WHERE user_id = ?');
            $stmt->execute([$_GET['id']]);
            
            // Update transactions
            $stmt = $db->prepare('UPDATE transactions SET user_id = NULL WHERE user_id = ?');
            $stmt->execute([$_GET['id']]);
            
            $stmt = $db->prepare('UPDATE transactions SET pharmacist_id = NULL WHERE pharmacist_id = ?');
            $stmt->execute([$_GET['id']]);
            
            // Update purchases
            $stmt = $db->prepare('UPDATE purchases SET pharmacist_id = NULL WHERE pharmacist_id = ?');
            $stmt->execute([$_GET['id']]);
            
            $stmt = $db->prepare('UPDATE purchases SET filled_by_user_id = NULL WHERE filled_by_user_id = ?');
            $stmt->execute([$_GET['id']]);
            
            // Update sales invoices
            $stmt = $db->prepare('UPDATE sales_invoices SET cashier_id = NULL WHERE cashier_id = ?');
            $stmt->execute([$_GET['id']]);
            
            // Finally, delete the user
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            
            $rowsAffected = $stmt->rowCount();
            
            $db->commit();
            
            if ($rowsAffected > 0) {
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found or already deleted']);
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}
?>