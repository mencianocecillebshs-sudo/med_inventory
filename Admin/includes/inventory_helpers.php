<?php
/**
 * inventory_helpers.php
 *
 * Shared helpers so purchase-fulfillment (purchases.php) and POS checkout
 * (sales.php) don't each carry their own copy of the same logic with
 * slightly different behavior.
 *
 * Include this the same way activity_logger.php is included elsewhere in
 * the project, e.g.:
 *
 *   foreach ([__DIR__ . '/../includes/inventory_helpers.php', __DIR__ . '/includes/inventory_helpers.php'] as $path) {
 *       if (file_exists($path)) { require_once $path; break; }
 *   }
 */

if (!function_exists('checkLowStockAndNotify')) {
    /**
     * Checks a medicine's current stock against the low-stock threshold and,
     * if it's at or below that threshold, inserts a notification.
     *
     * This replaces two near-duplicate copies of this logic that used to
     * live separately inside purchases.php and were missing entirely from
     * sales.php — so a sale could drop stock below the threshold with no
     * alert ever firing.
     */
    function checkLowStockAndNotify(mysqli $conn, int $medicine_id, int $user_id): void
    {
        $threshold = 10; // default fallback
        try {
            $threshold_stmt = $conn->prepare(
                "SELECT value FROM settings WHERE setting_key = 'low_stock_threshold' AND user_id = ? LIMIT 1"
            );
            if ($threshold_stmt) {
                $threshold_stmt->bind_param("i", $user_id);
                $threshold_stmt->execute();
                $threshold_result = $threshold_stmt->get_result();
                if ($threshold_row = $threshold_result->fetch_assoc()) {
                    $threshold = (int)$threshold_row['value'];
                }
                $threshold_stmt->close();
            }
        } catch (Exception $e) {
            error_log("checkLowStockAndNotify: error getting threshold: " . $e->getMessage());
        }

        $stock_stmt = $conn->prepare("SELECT quantity, name FROM medicines WHERE id = ?");
        if (!$stock_stmt) {
            error_log("checkLowStockAndNotify: prepare failed: " . $conn->error);
            return;
        }
        $stock_stmt->bind_param("i", $medicine_id);
        $stock_stmt->execute();
        $stock_row = $stock_stmt->get_result()->fetch_assoc();
        $stock_stmt->close();

        if (!$stock_row) {
            return;
        }

        if ((int)$stock_row['quantity'] <= $threshold) {
            try {
                $notif_stmt = $conn->prepare(
                    "INSERT INTO notifications (user_id, message, type, `read`, created_at) VALUES (?, ?, 'shortage', 0, NOW())"
                );
                if ($notif_stmt) {
                    $msg = "Low stock alert: {$stock_row['name']} ({$stock_row['quantity']} units left)";
                    $notif_stmt->bind_param("is", $user_id, $msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
            } catch (Exception $notifErr) {
                error_log("checkLowStockAndNotify: notification failed: " . $notifErr->getMessage());
            }
        }
    }
}

if (!function_exists('lockPendingPurchaseOrFail')) {
    /**
     * Locks a purchase row (FOR UPDATE) and guarantees it is still 'pending'
     * before letting either purchases.php ("Fill") or sales.php (checkout
     * with a linked purchase) touch it.
     *
     * This is the guard that was missing before: previously, either page
     * could finalize the same pending purchase without knowing the other
     * had already done so, which could deduct stock twice for one purchase.
     *
     * Must be called inside an open transaction. Throws Exception on
     * missing/non-pending purchase.
     */
    function lockPendingPurchaseOrFail(mysqli $conn, int $purchase_id): array
    {
        $stmt = $conn->prepare(
            "SELECT p.*, m.name AS medicine_name
             FROM purchases p
             JOIN medicines m ON p.medicine_id = m.id
             WHERE p.id = ?
             FOR UPDATE"
        );
        if (!$stmt) {
            throw new Exception('Purchase lookup failed: ' . $conn->error);
        }
        $stmt->bind_param("i", $purchase_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            throw new Exception("Purchase #{$purchase_id} not found.");
        }
        if ($row['status'] !== 'pending') {
            throw new Exception(
                "Purchase #{$purchase_id} ({$row['purchase_number']}) has already been {$row['status']} — it can't be filled again."
            );
        }
        return $row;
    }
}