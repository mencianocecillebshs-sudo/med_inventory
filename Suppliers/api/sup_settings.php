<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access', 'success' => false]);
    exit();
}

if (($_SESSION['role'] ?? '') !== 'supplier') {
    http_response_code(403);
    echo json_encode(['error' => 'Supplier access only', 'success' => false]);
    exit();
}

// Database connection - FIXED PATH
$db_path = __DIR__ . '/../config/db.php';
if (!file_exists($db_path)) {
    error_log("Database file not found at: " . $db_path);
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration file not found', 'success' => false]);
    exit();
}

require_once $db_path;

$settingsHelperPath = __DIR__ . '/../config/sup_settings_helper.php';
if (file_exists($settingsHelperPath)) {
    require_once $settingsHelperPath;
}

if (!$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'success' => false]);
    exit();
}

// Use the actual logged-in user's ID
$userId = $_SESSION['user_id'];

// Helper: Get system-wide setting (matches Admin portal and sup_settings_helper).
function getSetting($conn, $key, $default = null, $userId = null) {
    if (function_exists('getUserSetting')) {
        return getUserSetting($conn, $key, $default);
    }

    try {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ? AND user_id IS NULL LIMIT 1");
        if (!$stmt) {
            error_log("getSetting prepare failed: " . $conn->error);
            return $default;
        }

        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $value = $row['value'];
            $stmt->close();
            return $value;
        }

        $stmt->close();
        return $default;
    } catch (Exception $e) {
        error_log("getSetting error: " . $e->getMessage());
        return $default;
    }
}

// Helper: Save system-wide setting so all supplier pages read the same values.
function setSetting($conn, $key, $value, $userId = null) {
    try {
        $checkStmt = $conn->prepare("SELECT id FROM settings WHERE setting_key = ? AND user_id IS NULL");
        if (!$checkStmt) {
            error_log("setSetting check prepare failed: " . $conn->error);
            return false;
        }

        $checkStmt->bind_param("s", $key);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->num_rows > 0;
        $checkStmt->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE setting_key = ? AND user_id IS NULL");
            if (!$stmt) {
                error_log("setSetting update prepare failed: " . $conn->error);
                return false;
            }
            $stmt->bind_param("ss", $value, $key);
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, value, user_id, updated_at) VALUES (?, ?, NULL, NOW())");
            if (!$stmt) {
                error_log("setSetting insert prepare failed: " . $conn->error);
                return false;
            }
            $stmt->bind_param("ss", $key, $value);
        }

        $success = $stmt->execute();

        if (!$success) {
            error_log("setSetting execute failed: " . $stmt->error);
        }

        $stmt->close();
        return $success;
    } catch (Exception $e) {
        error_log("setSetting error: " . $e->getMessage());
        return false;
    }
}

// Helper: Validate setting
function validateSetting($key, $value) {
    $validations = [
        'low_stock_threshold' => ['type' => 'int', 'min' => 1, 'max' => 1000],
        'critical_stock_threshold' => ['type' => 'int', 'min' => 1, 'max' => 100],
        'expiry_alert_days' => ['type' => 'int', 'min' => 1, 'max' => 365],
        'auto_reorder_enabled' => ['type' => 'bool'],
        'notification_frequency' => ['type' => 'enum', 'values' => ['realtime', 'hourly', 'daily', 'weekly']],
        'notification_method' => ['type' => 'enum', 'values' => ['system', 'email', 'both']],
        'notif_low_stock' => ['type' => 'bool'],
        'notif_expiry' => ['type' => 'bool'],
        'notif_prescription' => ['type' => 'bool'],
        'currency_symbol' => ['type' => 'string', 'max_length' => 10],
        'date_format' => ['type' => 'enum', 'values' => ['Y-m-d', 'm/d/Y', 'd/m/Y', 'd-M-Y']],
        'records_per_page' => ['type' => 'int', 'min' => 10, 'max' => 100],
        'default_language' => ['type' => 'enum', 'values' => ['en', 'es', 'fr', 'tl']]
    ];

    if (!isset($validations[$key])) return ['valid' => true];

    $rules = $validations[$key];
    switch ($rules['type']) {
        case 'int':
            if (!is_numeric($value)) return ['valid' => false, 'error' => "$key must be a number"];
            $intVal = intval($value);
            if (isset($rules['min']) && $intVal < $rules['min']) return ['valid' => false, 'error' => "$key must be at least {$rules['min']}"];
            if (isset($rules['max']) && $intVal > $rules['max']) return ['valid' => false, 'error' => "$key must not exceed {$rules['max']}"];
            break;
        case 'bool':
            if (!in_array($value, ['0', '1', 0, 1, true, false], true)) return ['valid' => false, 'error' => "$key must be 0 or 1"];
            break;
        case 'enum':
            if (!in_array($value, $rules['values'], true)) return ['valid' => false, 'error' => "$key has invalid value"];
            break;
        case 'string':
            if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) return ['valid' => false, 'error' => "$key exceeds maximum length"];
            break;
    }

    return ['valid' => true];
}

// GET: Retrieve settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $settings = [
            'low_stock_threshold' => getSetting($conn, 'low_stock_threshold', '10', $userId),
            'critical_stock_threshold' => getSetting($conn, 'critical_stock_threshold', '5', $userId),
            'expiry_alert_days' => getSetting($conn, 'expiry_alert_days', '30', $userId),
            'auto_reorder_enabled' => getSetting($conn, 'auto_reorder_enabled', '0', $userId),
            'notification_frequency' => getSetting($conn, 'notification_frequency', 'daily', $userId),
            'notification_method' => getSetting($conn, 'notification_method', 'system', $userId),
            'notif_low_stock' => getSetting($conn, 'notif_low_stock', '1', $userId),
            'notif_expiry' => getSetting($conn, 'notif_expiry', '1', $userId),
            'notif_prescription' => getSetting($conn, 'notif_prescription', '1', $userId),
            'currency_symbol' => function_exists('normalizeCurrencySymbol')
                ? normalizeCurrencySymbol(getSetting($conn, 'currency_symbol', '₱', $userId))
                : getSetting($conn, 'currency_symbol', '₱', $userId),
            'date_format' => getSetting($conn, 'date_format', 'Y-m-d', $userId),
            'records_per_page' => getSetting($conn, 'records_per_page', '25', $userId),
            'default_language' => getSetting($conn, 'default_language', 'en', $userId),
            'last_update' => null,
            'pending_notifications' => 0
        ];

        // Get last update time for system-wide settings.
        $stmt = $conn->prepare("SELECT MAX(updated_at) as last_update FROM settings WHERE user_id IS NULL");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $settings['last_update'] = $row['last_update'];
            }
            $stmt->close();
        }

        // Get pending notifications count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND `read` = 0");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $settings['pending_notifications'] = intval($row['count']);
            }
            $stmt->close();
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'data' => $settings]);

    } catch (Exception $e) {
        error_log("Settings GET error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve settings', 'success' => false]);
    }
}

// POST: Save settings
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data', 'success' => false]);
            exit();
        }

        // Validate all settings
        $errors = [];
        foreach ($input as $key => $value) {
            $validation = validateSetting($key, $value);
            if (!$validation['valid']) {
                $errors[] = $validation['error'];
            }
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['error' => implode(', ', $errors), 'success' => false]);
            exit();
        }

        // Cross-field validation
        if (isset($input['critical_stock_threshold']) && isset($input['low_stock_threshold'])) {
            if (intval($input['critical_stock_threshold']) >= intval($input['low_stock_threshold'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Critical stock threshold must be less than low stock threshold', 'success' => false]);
                exit();
            }
        }

        // Begin transaction
        $conn->begin_transaction();

        try {
            $failedSettings = [];
            $successCount = 0;
            
            foreach ($input as $key => $value) {
                $result = setSetting($conn, $key, $value, $userId);
                if (!$result) {
                    $failedSettings[] = $key;
                    error_log("Failed to save supplier setting: $key = $value");
                } else {
                    $successCount++;
                }
            }

            if (!empty($failedSettings)) {
                throw new Exception("Failed to save settings: " . implode(', ', $failedSettings));
            }

            // Log transaction in transactions table if it exists
            $checkTable = $conn->query("SHOW TABLES LIKE 'transactions'");
            if ($checkTable && $checkTable->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, action, reason, notes, timestamp) VALUES (?, 'update', 'settings_updated', ?, NOW())");
                if ($stmt) {
                    $details = 'Settings updated by user ID: ' . $userId . ' (' . ($_SESSION['username'] ?? 'Unknown') . ') - Keys: ' . implode(', ', array_keys($input));
                    $stmt->bind_param("is", $userId, $details);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();

            error_log("Successfully saved $successCount supplier settings by user $userId");

            http_response_code(200);
            echo json_encode([
                'success' => true, 
                'message' => 'Settings saved successfully.',
                'saved_count' => $successCount,
                'updated' => array_keys($input)
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Transaction error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save settings: ' . $e->getMessage(), 'success' => false]);
        }

    } catch (Exception $e) {
        error_log("Settings POST error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save settings', 'success' => false]);
    }
}

// Method not allowed
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
}

// Close connection
if ($conn) {
    $conn->close();
}
?>
