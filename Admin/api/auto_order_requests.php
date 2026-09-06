<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/db.php';

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    respond(['success' => false, 'message' => 'Administrator access is required.'], 401);
}

$userId = (int)$_SESSION['user_id'];

function ensureAutoOrderSchema(mysqli $conn): void {
    $check = $conn->query("SHOW COLUMNS FROM suppliers LIKE 'is_preferred'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE suppliers ADD COLUMN is_preferred TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id");
    }
    if ($check) $check->close();

    $conn->query("
        CREATE TABLE IF NOT EXISTS auto_order_requests (
            id INT NOT NULL AUTO_INCREMENT,
            user_id INT DEFAULT NULL,
            status ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
            payload LONGTEXT NOT NULL,
            payload_hash VARCHAR(64) DEFAULT NULL,
            order_ids VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_auto_order_status (status),
            KEY idx_auto_order_user (user_id),
            KEY idx_auto_order_hash (payload_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $conn->query("ALTER TABLE auto_order_requests MODIFY id INT NOT NULL AUTO_INCREMENT");
    $maxIdResult = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM auto_order_requests");
    if ($maxIdResult) {
        $nextId = max(1, (int)($maxIdResult->fetch_assoc()['next_id'] ?? 1));
        $maxIdResult->close();
        $conn->query("ALTER TABLE auto_order_requests AUTO_INCREMENT = {$nextId}");
    }
    $hashCheck = $conn->query("SHOW COLUMNS FROM auto_order_requests LIKE 'payload_hash'");
    if ($hashCheck && $hashCheck->num_rows === 0) {
        $conn->query("ALTER TABLE auto_order_requests ADD COLUMN payload_hash VARCHAR(64) DEFAULT NULL AFTER payload, ADD KEY idx_auto_order_hash (payload_hash)");
    }
    if ($hashCheck) $hashCheck->close();
}

function settingValue(mysqli $conn, string $key, string $default, int $userId): string {
    $stmt = $conn->prepare("
        SELECT value FROM settings
        WHERE setting_key = ? AND (user_id IS NULL OR user_id = ?)
        ORDER BY user_id IS NULL DESC, id DESC
        LIMIT 1
    ");
    if (!$stmt) return $default;
    $stmt->bind_param('si', $key, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (string)$row['value'] : $default;
}

function createOrderNotification(mysqli $conn, int $userId, string $message): void {
    if ($userId <= 0) return;
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, `read`, created_at) VALUES (?, ?, 'surge', 0, NOW())");
    if (!$stmt) return;
    $stmt->bind_param('is', $userId, $message);
    $stmt->execute();
    $stmt->close();
}

function pendingRequest(mysqli $conn): ?array {
    $res = $conn->query("SELECT id, payload, created_at FROM auto_order_requests WHERE status = 'pending' ORDER BY id DESC LIMIT 1");
    if (!$res) return null;
    $row = $res->fetch_assoc();
    $res->close();
    if (!$row) return null;
    $payload = json_decode($row['payload'], true);
    if (!is_array($payload)) $payload = ['items' => []];
    return [
        'id' => (int)$row['id'],
        'created_at' => $row['created_at'],
        'items' => $payload['items'] ?? [],
        'total_amount' => (float)($payload['total_amount'] ?? 0),
        'threshold' => (int)($payload['threshold'] ?? 0)
    ];
}

function buildRequest(mysqli $conn, int $userId): ?array {
    if (settingValue($conn, 'auto_reorder_enabled', '0', $userId) !== '1') {
        return null;
    }

    $threshold = (int)settingValue($conn, 'low_stock_threshold', '10', $userId);
    $stmt = $conn->prepare("
        SELECT id, name, quantity, reorder_point, markup_percentage
        FROM medicines
        WHERE quantity <= ?
        ORDER BY quantity ASC, name ASC
        LIMIT 25
    ");
    if (!$stmt) return null;
    $stmt->bind_param('i', $threshold);
    $stmt->execute();
    $meds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $items = [];
    $hasOrderableItems = false;
    foreach ($meds as $med) {
        $medicineId = (int)$med['id'];

        $dup = $conn->prepare("
            SELECT o.id, o.status, o.supplier_id,
                   s.name AS supplier_name, s.company AS supplier_company
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.id
            LEFT JOIN suppliers s ON s.id = o.supplier_id
            WHERE oi.medicine_id = ? AND o.status IN ('pending','ordered','accepted')
            ORDER BY o.id DESC
            LIMIT 1
        ");
        if ($dup) {
            $dup->bind_param('i', $medicineId);
            $dup->execute();
            $existing = $dup->get_result()->fetch_assoc();
            $dup->close();
            if ($existing) {
                continue;
            }
        }

        $supplierStmt = $conn->prepare("
            SELECT s.id AS supplier_id, s.name AS supplier_name, s.company, s.user_id AS supplier_user_id,
                   s.is_preferred,
                   COALESCE(si.quantity, sm.quantity_supplied, 0) AS supplier_stock,
                   COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) AS supplier_cost,
                   ROUND(COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) *
                       (1 + COALESCE(NULLIF(m.markup_percentage, 0), 20) / 100), 2) AS unit_price,
                   COALESCE(NULLIF(sm.min_order_quantity, 0), 1) AS min_order_quantity
            FROM (
                SELECT medicine_id FROM supplier_inventory WHERE medicine_id = ?
                UNION
                SELECT medicine_id FROM supplier_medicines WHERE medicine_id = ?
            ) owned
            JOIN medicines m ON m.id = owned.medicine_id
            JOIN suppliers s
            LEFT JOIN supplier_inventory si ON si.supplier_id = s.id AND si.medicine_id = owned.medicine_id
            LEFT JOIN supplier_medicines sm ON sm.supplier_id = s.id AND sm.medicine_id = owned.medicine_id
            WHERE COALESCE(si.quantity, sm.quantity_supplied, 0) > 0
              AND COALESCE(NULLIF(sm.unit_price, 0), NULLIF(si.unit_price, 0), 0) > 0
            ORDER BY s.is_preferred DESC, sm.preferred DESC, unit_price ASC, supplier_stock DESC
            LIMIT 1
        ");
        if (!$supplierStmt) {
            $items[] = [
                'medicine_id' => $medicineId,
                'medicine_name' => $med['name'],
                'current_quantity' => (int)$med['quantity'],
                'supplier_id' => 0,
                'supplier_user_id' => 0,
                'supplier_name' => 'No supplier available',
                'is_preferred' => 0,
                'supplier_stock' => 0,
                'quantity' => 0,
                'unit_price' => 0,
                'subtotal' => 0,
                'min_order_quantity' => 0,
                'can_order' => false,
                'reason' => 'Supplier lookup failed'
            ];
            continue;
        }
        $supplierStmt->bind_param('ii', $medicineId, $medicineId);
        $supplierStmt->execute();
        $supplier = $supplierStmt->get_result()->fetch_assoc();
        $supplierStmt->close();
        if (!$supplier) {
            $items[] = [
                'medicine_id' => $medicineId,
                'medicine_name' => $med['name'],
                'current_quantity' => (int)$med['quantity'],
                'supplier_id' => 0,
                'supplier_user_id' => 0,
                'supplier_name' => 'No supplier with stock',
                'is_preferred' => 0,
                'supplier_stock' => 0,
                'quantity' => 0,
                'unit_price' => 0,
                'subtotal' => 0,
                'min_order_quantity' => 0,
                'can_order' => false,
                'reason' => 'No supplier stock'
            ];
            continue;
        }

        $minOrder = max(1, (int)$supplier['min_order_quantity']);
        $stock = (int)$supplier['supplier_stock'];
        $quantity = max(200, $minOrder);
        if ($quantity > $stock) {
            if ($stock < $minOrder) {
                $items[] = [
                    'medicine_id' => $medicineId,
                    'medicine_name' => $med['name'],
                    'current_quantity' => (int)$med['quantity'],
                    'supplier_id' => (int)$supplier['supplier_id'],
                    'supplier_user_id' => (int)($supplier['supplier_user_id'] ?? 0),
                    'supplier_name' => trim(($supplier['company'] ?: $supplier['supplier_name']) . ' - ' . $supplier['supplier_name'], ' -'),
                    'is_preferred' => (int)$supplier['is_preferred'],
                    'supplier_stock' => $stock,
                    'quantity' => 0,
                    'unit_price' => (float)$supplier['unit_price'],
                    'subtotal' => 0,
                    'min_order_quantity' => $minOrder,
                    'can_order' => false,
                    'reason' => 'Supplier stock is below minimum order'
                ];
                continue;
            }
            $quantity = $stock;
        }

        $unitPrice = (float)$supplier['unit_price'];
        $items[] = [
            'medicine_id' => $medicineId,
            'medicine_name' => $med['name'],
            'current_quantity' => (int)$med['quantity'],
            'supplier_id' => (int)$supplier['supplier_id'],
            'supplier_user_id' => (int)($supplier['supplier_user_id'] ?? 0),
            'supplier_name' => trim(($supplier['company'] ?: $supplier['supplier_name']) . ' - ' . $supplier['supplier_name'], ' -'),
            'is_preferred' => (int)$supplier['is_preferred'],
            'supplier_stock' => $stock,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'min_order_quantity' => $minOrder,
            'can_order' => true,
            'reason' => ''
        ];
        $hasOrderableItems = true;
    }

    if (!$items || !$hasOrderableItems) return null;
    $total = array_sum(array_map(fn($item) => !empty($item['can_order']) ? (float)$item['subtotal'] : 0, $items));
    $payload = ['threshold' => $threshold, 'total_amount' => $total, 'items' => $items];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $payloadHash = hash('sha256', json_encode(array_map(fn($item) => [
        'medicine_id' => $item['medicine_id'],
        'supplier_id' => $item['supplier_id'],
        'quantity' => $item['quantity'],
        'unit_price' => $item['unit_price'],
        'can_order' => !empty($item['can_order']),
        'reason' => $item['reason'] ?? ''
    ], $items)));

    $dismissed = $conn->prepare("SELECT id FROM auto_order_requests WHERE status = 'rejected' AND payload_hash = ? ORDER BY id DESC LIMIT 1");
    if ($dismissed) {
        $dismissed->bind_param('s', $payloadHash);
        $dismissed->execute();
        $dismissedRow = $dismissed->get_result()->fetch_assoc();
        $dismissed->close();
        if ($dismissedRow) return null;
    }

    $insert = $conn->prepare("INSERT INTO auto_order_requests (user_id, status, payload, payload_hash, created_at) VALUES (?, 'pending', ?, ?, NOW())");
    if (!$insert) return null;
    $insert->bind_param('iss', $userId, $json, $payloadHash);
    if (!$insert->execute()) {
        throw new Exception('Auto-order request insert failed: ' . $insert->error);
    }
    $id = $conn->insert_id;
    $insert->close();

    return ['id' => $id, 'created_at' => date('Y-m-d H:i:s'), 'threshold' => $threshold, 'total_amount' => $total, 'items' => $items];
}

ensureAutoOrderSchema($conn);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $request = pendingRequest($conn);
        if (!$request) $request = buildRequest($conn, $userId);
        respond(['success' => true, 'data' => $request]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    if ($requestId <= 0) respond(['success' => false, 'message' => 'Invalid request ID.'], 400);

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE auto_order_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->close();
        respond(['success' => true, 'message' => 'Auto-order request rejected.']);
    }

    if ($action !== 'accept') {
        respond(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    $conn->begin_transaction();
    $req = $conn->prepare("SELECT payload FROM auto_order_requests WHERE id = ? AND status = 'pending' FOR UPDATE");
    if (!$req) throw new Exception('Request fetch prepare failed: ' . $conn->error);
    $req->bind_param('i', $requestId);
    $req->execute();
    $row = $req->get_result()->fetch_assoc();
    $req->close();
    if (!$row) throw new Exception('Auto-order request is no longer pending.');

    $payload = json_decode($row['payload'], true);
    $items = $payload['items'] ?? [];
    if (!$items) throw new Exception('No items found in this auto-order request.');

    $groups = [];
    foreach ($items as $item) {
        if (empty($item['can_order'])) continue;
        $groups[(int)$item['supplier_id']][] = $item;
    }
    if (!$groups) {
        throw new Exception('No orderable items are available yet. Check supplier stock or existing pending orders.');
    }

    $orderIds = [];
    foreach ($groups as $supplierId => $supplierItems) {
        $total = array_sum(array_map(fn($item) => (float)$item['subtotal'], $supplierItems));
        $first = $supplierItems[0];
        $expected = date('Y-m-d', strtotime('+7 days'));
        $notes = 'Auto-order approved from dashboard low-stock request #' . $requestId;
        $paymentMethod = 'cash';
        $paymentReference = '';

        $stmt = $conn->prepare("
            INSERT INTO orders
                (supplier_id, medicine_id, quantity, unit_price, total_amount, status, order_date, expected_delivery, notes, payment_method, payment_reference, ordered_by)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW(), ?, ?, ?, ?, ?)
        ");
        if (!$stmt) throw new Exception('Order prepare failed: ' . $conn->error);
        $firstMedicine = (int)$first['medicine_id'];
        $firstQty = (int)$first['quantity'];
        $firstPrice = (float)$first['unit_price'];
        $stmt->bind_param('iiiddssssi', $supplierId, $firstMedicine, $firstQty, $firstPrice, $total, $expected, $notes, $paymentMethod, $paymentReference, $userId);
        if (!$stmt->execute()) throw new Exception('Order insert failed: ' . $stmt->error);
        $orderId = $conn->insert_id;
        $stmt->close();
        $orderIds[] = $orderId;

        foreach ($supplierItems as $item) {
            $medicineId = (int)$item['medicine_id'];
            $qty = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];
            $subtotal = $qty * $unitPrice;
            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            if (!$itemStmt) throw new Exception('Order item prepare failed: ' . $conn->error);
            $itemStmt->bind_param('iiidd', $orderId, $medicineId, $qty, $unitPrice, $subtotal);
            if (!$itemStmt->execute()) throw new Exception('Order item insert failed: ' . $itemStmt->error);
            $itemStmt->close();
        }

        createOrderNotification($conn, (int)($first['supplier_user_id'] ?? 0), "New Auto Order #{$orderId} placed from low-stock approval.");
    }

    $orderIdsText = implode(',', $orderIds);
    $done = $conn->prepare("UPDATE auto_order_requests SET status = 'accepted', order_ids = ? WHERE id = ?");
    if (!$done) throw new Exception('Request update prepare failed: ' . $conn->error);
    $done->bind_param('si', $orderIdsText, $requestId);
    $done->execute();
    $done->close();
    $conn->commit();

    respond(['success' => true, 'message' => 'Auto-order created successfully.', 'order_ids' => $orderIds]);
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // No-op; mysqli does not expose transaction state, rollback is harmless.
    }
    $conn->rollback();
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}