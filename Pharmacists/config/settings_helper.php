<?php
/**
 * Settings Helper Functions
 * Use these functions throughout the system to retrieve and apply user settings
 */

// Get setting with global system-wide value first, then legacy user-specific fallback.
// Priority: global row (user_id IS NULL) -> user-specific row -> $default
function getUserSetting($conn, $key, $default = null) {
    try {
        $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ? AND user_id IS NULL LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $value = $row['value'];
                $stmt->close();
                return $value;
            }
            $stmt->close();
        }

        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT value FROM settings WHERE setting_key = ? AND user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("si", $key, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $value = $row['value'];
                    $stmt->close();
                    return $value;
                }
                $stmt->close();
            }
        }

        return $default;
    } catch (Exception $e) {
        error_log("getUserSetting error: " . $e->getMessage());
        return $default;
    }
}

// Get low stock threshold for current user
function getLowStockThreshold($conn) {
    return intval(getUserSetting($conn, 'low_stock_threshold', 10));
}

// Get critical stock threshold for current user
function getCriticalStockThreshold($conn) {
    return intval(getUserSetting($conn, 'critical_stock_threshold', 5));
}

// Get expiry alert days for current user
function getExpiryAlertDays($conn) {
    return intval(getUserSetting($conn, 'expiry_alert_days', 30));
}

// Check if auto-reorder is enabled
function isAutoReorderEnabled($conn) {
    return getUserSetting($conn, 'auto_reorder_enabled', '0') == '1';
}

// Get notification frequency
function getNotificationFrequency($conn) {
    return getUserSetting($conn, 'notification_frequency', 'daily');
}

// Get notification method
function getNotificationMethod($conn) {
    return getUserSetting($conn, 'notification_method', 'system');
}

// Check if specific notification type is enabled
function isNotificationEnabled($conn, $type) {
    $key = 'notif_' . $type;
    return getUserSetting($conn, $key, '1') != '0';
}

// Get currency symbol
function getCurrencySymbol($conn) {
    return getUserSetting($conn, 'currency_symbol', '₱');
}

// Get date format
function getDateFormat($conn) {
    return getUserSetting($conn, 'date_format', 'Y-m-d');
}

// Format date according to user preference
function formatUserDate($conn, $date) {
    if (empty($date)) return 'N/A';
    $format = getDateFormat($conn);
    return date($format, strtotime($date));
}

// Get records per page
function getRecordsPerPage($conn) {
    return intval(getUserSetting($conn, 'records_per_page', 25));
}

// Get default language
function getDefaultLanguage($conn) {
    return getUserSetting($conn, 'default_language', 'en');
}

// Format currency according to user preference
function formatCurrency($conn, $amount) {
    $symbol = getCurrencySymbol($conn);
    return $symbol . ' ' . number_format($amount, 2);
}

// Check if item is low stock
function isLowStock($conn, $quantity) {
    $lowThreshold = getLowStockThreshold($conn);
    $criticalThreshold = getCriticalStockThreshold($conn);
    return $quantity <= $lowThreshold && $quantity > $criticalThreshold;
}

// Check if item is critical stock
function isCriticalStock($conn, $quantity) {
    return $quantity <= getCriticalStockThreshold($conn);
}

// Check if item is expiring soon
function isExpiringSoon($conn, $expiryDate) {
    if (empty($expiryDate)) return false;
    
    $alertDays = getExpiryAlertDays($conn);
    $expiryTimestamp = strtotime($expiryDate);
    $alertTimestamp = strtotime("+$alertDays days");
    
    return $expiryTimestamp <= $alertTimestamp && $expiryTimestamp >= time();
}

// Get stock status (text)
function getStockStatus($conn, $quantity) {
    if (isCriticalStock($conn, $quantity)) {
        return 'critical';
    } elseif (isLowStock($conn, $quantity)) {
        return 'low';
    } else {
        return 'normal';
    }
}

// Get stock status badge HTML
function getStockStatusBadge($conn, $quantity) {
    if (isCriticalStock($conn, $quantity)) {
        return '<span class="badge bg-danger">Critical Stock</span>';
    } elseif (isLowStock($conn, $quantity)) {
        return '<span class="badge bg-warning text-dark">Low Stock</span>';
    } else {
        return '<span class="badge bg-success">In Stock</span>';
    }
}

// Get expiry status badge HTML
function getExpiryStatusBadge($conn, $expiryDate) {
    if (empty($expiryDate)) {
        return '<span class="badge bg-secondary">No Expiry</span>';
    }
    
    $expiryTimestamp = strtotime($expiryDate);
    $now = time();
    
    if ($expiryTimestamp < $now) {
        return '<span class="badge bg-danger">Expired</span>';
    } elseif (isExpiringSoon($conn, $expiryDate)) {
        return '<span class="badge bg-warning text-dark">Expiring Soon</span>';
    } else {
        return '<span class="badge bg-success">Valid</span>';
    }
}

// Create notification (respects user settings)
function createNotification($conn, $message, $type = '') {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $userId = $_SESSION['user_id'];
    
    // Check if this notification type is enabled
    $typeMap = [
        'shortage' => 'low_stock',
        'expiry' => 'expiry',
        '' => 'purchase' // default to purchase for general notifications
    ];
    
    $settingKey = isset($typeMap[$type]) ? $typeMap[$type] : 'purchase';
    
    if (!isNotificationEnabled($conn, $settingKey)) {
        error_log("Notification type '$settingKey' is disabled for user $userId");
        return false; // Don't create notification if disabled
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, `read`, created_at) VALUES (?, ?, ?, 0, NOW())");
        if (!$stmt) {
            error_log("createNotification prepare failed: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("iss", $userId, $message, $type);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("createNotification error: " . $e->getMessage());
        return false;
    }
}

// Generate auto-reorder suggestions based on stock levels
function generateReorderSuggestions($conn) {
    if (!isAutoReorderEnabled($conn)) {
        return []; // Return empty if auto-reorder is disabled
    }
    
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    $userId = $_SESSION['user_id'];
    $lowThreshold = getLowStockThreshold($conn);
    $criticalThreshold = getCriticalStockThreshold($conn);
    
    try {
        // Get medicines below low stock threshold
        $stmt = $conn->prepare("
            SELECT id, name, quantity, type 
            FROM medicines 
            WHERE quantity <= ? 
            ORDER BY quantity ASC
        ");
        
        if (!$stmt) {
            error_log("generateReorderSuggestions prepare failed: " . $conn->error);
            return [];
        }
        
        $stmt->bind_param("i", $lowThreshold);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $suggestions = [];
        while ($row = $result->fetch_assoc()) {
            $reorderQty = $lowThreshold * 3; // Reorder to 3x the threshold
            
            $suggestions[] = [
                'medicine_id' => $row['id'],
                'medicine_name' => $row['name'],
                'current_quantity' => $row['quantity'],
                'suggested_quantity' => $reorderQty,
                'priority' => $row['quantity'] <= $criticalThreshold ? 'high' : 'medium',
                'type' => $row['type']
            ];
        }
        
        $stmt->close();
        return $suggestions;
        
    } catch (Exception $e) {
        error_log("generateReorderSuggestions error: " . $e->getMessage());
        return [];
    }
}

// Get medicines that need reordering
function getMedicinesNeedingReorder($conn) {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    $lowThreshold = getLowStockThreshold($conn);
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM medicines 
            WHERE quantity <= ?
        ");
        
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param("i", $lowThreshold);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return intval($row['count']);
        
    } catch (Exception $e) {
        error_log("getMedicinesNeedingReorder error: " . $e->getMessage());
        return 0;
    }
}

// Get expiring medicines count
function getExpiringMedicinesCount($conn) {
    if (!isset($_SESSION['user_id'])) {
        return 0;
    }
    
    $alertDays = getExpiryAlertDays($conn);
    $alertDate = date('Y-m-d', strtotime("+$alertDays days"));
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM medicines 
            WHERE expiry_date <= ? AND expiry_date >= CURDATE()
        ");
        
        if (!$stmt) {
            return 0;
        }
        
        $stmt->bind_param("s", $alertDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return intval($row['count']);
        
    } catch (Exception $e) {
        error_log("getExpiringMedicinesCount error: " . $e->getMessage());
        return 0;
    }
}

// Check if notifications should be sent based on frequency
function shouldSendNotifications($conn) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $userId = $_SESSION['user_id'];
    $frequency = getNotificationFrequency($conn);
    
    // Get last notification time
    try {
        $stmt = $conn->prepare("
            SELECT MAX(created_at) as last_notif 
            FROM notifications 
            WHERE user_id = ?
        ");
        
        if (!$stmt) {
            return true; // If error, allow notifications
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row || !$row['last_notif']) {
            return true; // No notifications yet, allow
        }
        
        $lastNotif = strtotime($row['last_notif']);
        $now = time();
        $diff = $now - $lastNotif;
        
        // Check based on frequency
        switch ($frequency) {
            case 'realtime':
                return true; // Always allow
            case 'hourly':
                return $diff >= 3600; // 1 hour
            case 'daily':
                return $diff >= 86400; // 24 hours
            case 'weekly':
                return $diff >= 604800; // 7 days
            default:
                return true;
        }
        
    } catch (Exception $e) {
        error_log("shouldSendNotifications error: " . $e->getMessage());
        return true; // On error, allow notifications
    }
}

// Apply language translation (placeholder for future implementation)
function translate($conn, $text) {
    $language = getDefaultLanguage($conn);
    
    // For now, return as-is. Can be expanded with translation arrays
    // Example: $translations['en']['dashboard'] = 'Dashboard';
    //          $translations['tl']['dashboard'] = 'Talaarawan';
    
    return $text;
}
?>
