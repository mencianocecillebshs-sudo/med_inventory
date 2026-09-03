<?php
/**
 * Auto-reorder helper
 *
 * When a medicine's quantity <= reorder_point this will attempt to create a
 * pending order with a supplier that has available stock. The supplier
 * still needs to accept the order (status remains 'pending' until accepted).
 *
 * Policy: fixed order quantity (200) as requested — but if the supplier defines
 * a min_order_quantity we respect that by rounding up to the supplier minimum.
 */

function triggerAutoReorder(mysqli $conn, int $medicine_id, int $trigger_user_id = 0, int $order_qty = 200) {
    $logFile = __DIR__ . '/../../tmp/auto_reorder.log';
    $log = function($m) use ($logFile) {
        @file_put_contents($logFile, "[".date('c')."] " . $m . "\n", FILE_APPEND | LOCK_EX);
    };

    $log("Auto-reorder check: medicine_id={$medicine_id}, trigger_user_id={$trigger_user_id}, requested_qty={$order_qty}");

    $prefCheck = $conn->query("SHOW COLUMNS FROM suppliers LIKE 'is_preferred'");
    if ($prefCheck && $prefCheck->num_rows === 0) {
        $conn->query("ALTER TABLE suppliers ADD COLUMN is_preferred TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id");
    }
    if ($prefCheck) $prefCheck->close();

    // Keep the automatic order path disabled unless the system setting is enabled.
    $settingStmt = $conn->prepare(
        "SELECT value FROM settings
         WHERE setting_key = 'auto_reorder_enabled'
           AND (user_id IS NULL OR user_id = ?)
         ORDER BY user_id IS NULL DESC, id DESC
         LIMIT 1"
    );
    if (!$settingStmt) {
        $log("Failed to read auto-reorder setting: " . $conn->error);
        return null;
    }
    $settingStmt->bind_param('i', $trigger_user_id);
    $settingStmt->execute();
    $setting = $settingStmt->get_result()->fetch_assoc();
    $settingStmt->close();
    if (!$setting || (string)$setting['value'] !== '1') {
        $log("Skip: auto-reorder is disabled");
        return null;
    }

    // Read current medicine state
    $mstmt = $conn->prepare("SELECT id, name, quantity, reorder_point FROM medicines WHERE id = ? LIMIT 1");
    if (!$mstmt) {
        $log("Failed to prepare medicine lookup: " . $conn->error);
        return null;
    }
    $mstmt->bind_param('i', $medicine_id);
    $mstmt->execute();
    $med = $mstmt->get_result()->fetch_assoc();
    $mstmt->close();
    if (!$med) {
        $log("Medicine not found: id={$medicine_id}");
        return null;
    }

    // Determine threshold: admin users trigger at an absolute 150 units, others use medicine.reorder_point
    $use_admin_threshold = false;
    if ($trigger_user_id > 0) {
        $uStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        if ($uStmt) {
            $uStmt->bind_param('i', $trigger_user_id);
            $uStmt->execute();
            $urole = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
            if ($urole && isset($urole['role']) && $urole['role'] === 'admin') {
                $use_admin_threshold = true;
            }
        } else {
            $log("Failed to prepare user role lookup: " . $conn->error);
        }
    }

    if ($use_admin_threshold) {
        // Admin-specific rule: trigger when quantity <= 150
        if ((int)$med['quantity'] > 150) {
            $log("Skip: admin threshold not reached (quantity={$med['quantity']})");
            return null; // not low for admin threshold
        }
    } else {
        if ($med['reorder_point'] === null) {
            $log("Skip: no reorder_point set for medicine_id={$medicine_id}");
            return null;
        }
        if ((int)$med['quantity'] > (int)$med['reorder_point']) {
            $log("Skip: quantity {$med['quantity']} > reorder_point {$med['reorder_point']}");
            return null; // not low
        }
    }

    // Find supplier offering this medicine with available stock and a price.
    // The supplier's unit_price is their cost; orders use the selling price so
    // the supplier's margin is included in the order total.
    $stmt = $conn->prepare(
        "SELECT s.id AS supplier_id, s.user_id AS supplier_user_id,
                COALESCE(si.quantity, sm.quantity_supplied, 0) AS supplier_stock,
                COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost,
                ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                    (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS unit_price,
                COALESCE(NULLIF(sm.min_order_quantity, 0), 1) AS min_order_quantity,
                s.is_preferred
         FROM (
            SELECT medicine_id FROM supplier_inventory WHERE medicine_id = ?
            UNION
            SELECT medicine_id FROM supplier_medicines WHERE medicine_id = ?
         ) owned
         JOIN medicines m ON m.id = owned.medicine_id
         JOIN (
            SELECT id, user_id, is_preferred FROM suppliers
         ) s
         LEFT JOIN supplier_inventory si ON si.supplier_id = s.id AND si.medicine_id = owned.medicine_id
         LEFT JOIN supplier_medicines sm ON sm.supplier_id = s.id AND sm.medicine_id = owned.medicine_id
         WHERE COALESCE(si.quantity, sm.quantity_supplied, 0) > 0
           AND s.id IS NOT NULL
         ORDER BY s.is_preferred DESC, sm.preferred DESC, unit_price ASC, supplier_stock DESC
         LIMIT 1"
    );
    if (!$stmt) {
        $log("Failed to prepare supplier lookup: " . $conn->error);
        return null;
    }
    $stmt->bind_param('ii', $medicine_id, $medicine_id);
    $stmt->execute();
    $supplierRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$supplierRow) {
        $log("No supplier with available stock found for medicine_id={$medicine_id}");
        return null;
    }

    $supplier_id = (int)$supplierRow['supplier_id'];
    $supplier_user_id = (int)($supplierRow['supplier_user_id'] ?? 0);
    $supplier_stock = (int)$supplierRow['supplier_stock'];
    $supplier_cost = (float)$supplierRow['supplier_cost'];
    $unit_price = (float)$supplierRow['unit_price'];
    $min_order = max(1, (int)$supplierRow['min_order_quantity']);

    $log("Found supplier_id={$supplier_id}, supplier_user_id={$supplier_user_id}, supplier_stock={$supplier_stock}, supplier_cost={$supplier_cost}, selling_price={$unit_price}, min_order={$min_order}");

    // Determine order quantity: use requested order_qty but respect supplier min
    $qty = max($order_qty, $min_order);
    if ($supplier_stock > 0 && $qty > $supplier_stock) {
        // If supplier has less than requested, order whatever they have if it's >= min_order
        if ($supplier_stock >= $min_order) {
            $qty = $supplier_stock;
            $log("Adjusted qty to supplier_stock={$supplier_stock} due to limited availability");
        } else {
            // can't satisfy min_order, skip auto-order
            $log("Skip: supplier stock {$supplier_stock} < min_order {$min_order}");
            return null;
        }
    }

    // Avoid duplicate pending orders for same medicine & supplier
    $dupStmt = $conn->prepare("SELECT id FROM orders WHERE supplier_id = ? AND status IN ('pending','ordered') AND id IN (SELECT order_id FROM order_items WHERE medicine_id = ?) LIMIT 1");
    if ($dupStmt) {
        $dupStmt->bind_param('ii', $supplier_id, $medicine_id);
        $dupStmt->execute();
        $exists = $dupStmt->get_result()->fetch_assoc();
        $dupStmt->close();
        if ($exists) {
            $log("Skip: duplicate pending order exists (order_id=" . ($exists['id'] ?? 'unknown') . ") for supplier_id={$supplier_id}, medicine_id={$medicine_id}");
            return null; // already pending
        }
    } else {
        $log("Warning: duplicate check prepare failed: " . $conn->error);
    }

    // Insert order and order_item (minimal fields similar to Admin/orders.php)
    $conn->begin_transaction();
    try {
        $total_amount = $qty * $unit_price;
        $expected_delivery = date('Y-m-d', strtotime('+7 days'));
        if ($use_admin_threshold) {
            $notes = 'Auto-reorder triggered: low stock (qty ' . $med['quantity'] . ' <= admin threshold 150)';
        } else {
            $notes = 'Auto-reorder triggered: low stock (qty ' . $med['quantity'] . ' <= reorder_point ' . $med['reorder_point'] . ')';
        }
        $payment_method = 'cash';
        $payment_reference = '';

        $stmt = $conn->prepare(
            "INSERT INTO orders (supplier_id, medicine_id, quantity, unit_price, total_amount, status, order_date, expected_delivery, notes, payment_method, payment_reference, ordered_by)
             VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?, ?, ?)"
        );
        if (!$stmt) { $log("Failed to prepare insert order: " . $conn->error); $conn->rollback(); return null; }
        $stmt->bind_param('iiiddssssi', $supplier_id, $medicine_id, $qty, $unit_price, $total_amount, $expected_delivery, $notes, $payment_method, $payment_reference, $trigger_user_id);
        if (!$stmt->execute()) { $log("Failed to execute insert order: " . $stmt->error); $stmt->close(); $conn->rollback(); return null; }
        $order_id = $conn->insert_id;
        $stmt->close();

        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
        if (!$item_stmt) { $log("Failed to prepare insert order_item: " . $conn->error); $conn->rollback(); return null; }
        $subtotal = $qty * $unit_price;
        $item_stmt->bind_param('iiidd', $order_id, $medicine_id, $qty, $unit_price, $subtotal);
        if (!$item_stmt->execute()) { $log("Failed to execute insert order_item: " . $item_stmt->error); $item_stmt->close(); $conn->rollback(); return null; }
        $item_stmt->close();

        // Notify supplier user if available
        if ($supplier_user_id > 0) {
            $notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, `read`, created_at) VALUES (?, ?, 'surge', 0, NOW())");
            if ($notif) {
                $msg = "New Auto Order #{$order_id} requires your confirmation (medicine: {$med['name']}, qty: {$qty}).";
                $notif->bind_param('is', $supplier_user_id, $msg);
                $notif->execute();
                $notif->close();
            } else {
                $log("Warning: failed to prepare supplier notification: " . $conn->error);
            }
        }

        $conn->commit();
        $log("Auto-order created: order_id={$order_id}, supplier_id={$supplier_id}, medicine_id={$medicine_id}, qty={$qty}, total_amount={$total_amount}");
        return $order_id;
    } catch (Exception $e) {
        $conn->rollback();
        $log("Exception during auto-reorder: " . $e->getMessage());
        return null;
    }
}
