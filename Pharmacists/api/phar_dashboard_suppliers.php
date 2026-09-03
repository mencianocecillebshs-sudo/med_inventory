<?php
ob_start();
header('Content-Type: application/json');
session_start();

// This endpoint only supplies the dashboard summary. Supplier records cannot be
// viewed or managed from the pharmacist workspace.
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || isset($_GET['supplier_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Supplier management is restricted to administrators.']);
    exit();
}

$host        = 'localhost';
$dbname      = 'med_inventory';
$db_username = 'root';
$db_password = '';

try {
    $conn = new mysqli($host, $db_username, $db_password, $dbname);
    if ($conn->connect_error) throw new Exception('Connection failed: ' . $conn->connect_error);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'errors' => ['Database connection failed: ' . $e->getMessage()]]);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode(['success' => false, 'errors' => ['Unauthorized']]);
    exit();
}

$response = ['success' => false, 'errors' => [], 'data' => null];

// ══════════════════════════════════════════════════════
//  POST handlers
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add Supplier ────────────────────────────────────
    if ($action === 'add') {
        $errors = [];
        $name           = trim($_POST['name']           ?? '');
        $company        = trim($_POST['company']        ?? '');
        $address        = trim($_POST['address']        ?? '');
        $contact        = trim($_POST['contact']        ?? '');
        $total_buy      = floatval($_POST['total_buy']  ?? 0);
        $total_paid     = floatval($_POST['total_paid'] ?? 0);
        $total_due      = floatval($_POST['total_due']  ?? 0);
        $representative = trim($_POST['representative'] ?? '');
        $lead_time      = intval($_POST['lead_time']    ?? 0);

        if (empty($name))    $errors[] = 'Name is required.';
        if (strlen($name) > 100 || !preg_match('/^[A-Za-z\s]+$/', $name)) $errors[] = 'Name must be letters and spaces only (max 100).';
        if (empty($company)) $errors[] = 'Company is required.';
        if (strlen($company) > 150 || !preg_match('/^[A-Za-z0-9\s&.,\-]+$/', $company)) $errors[] = 'Company name too long or invalid characters.';
        if (empty($address)) $errors[] = 'Address is required.';
        if (strlen($address) > 500) $errors[] = 'Address too long (max 500).';
        if (empty($contact)) $errors[] = 'Contact is required.';
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL) && !preg_match('/^\+?[0-9\s\-]{7,15}$/', $contact)) $errors[] = 'Contact must be a valid email or phone.';
        if ($total_buy  < 0 || $total_buy  > 99999999.99) $errors[] = 'Total Buy out of range.';
        if ($total_paid < 0 || $total_paid > 99999999.99) $errors[] = 'Total Paid out of range.';
        if ($total_due  < 0 || $total_due  > 99999999.99) $errors[] = 'Total Due out of range.';
        if ($total_due > $total_buy) $errors[] = 'Total Due cannot exceed Total Buy.';
        if (strlen($representative) > 100 || (!empty($representative) && !preg_match('/^[A-Za-z\s]+$/', $representative))) $errors[] = 'Representative must be letters and spaces only.';
        if ($lead_time < 0 || $lead_time > 365) $errors[] = 'Lead Time must be 0–365 days.';

        if (empty($errors)) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO suppliers (name, company, address, contact, total_buy, total_paid, total_due, representative, lead_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
                $stmt->bind_param("ssssdddsi", $name, $company, $address, $contact, $total_buy, $total_paid, $total_due, $representative, $lead_time);
                if (!$stmt->execute()) throw new Exception('Failed to add supplier: ' . $stmt->error);
                $supplier_id = $conn->insert_id;
                $stmt->close();

                $username = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $company));
                $chk = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE username = ?");
                $chk->bind_param("s", $username);
                $chk->execute();
                $cnt = $chk->get_result()->fetch_assoc()['cnt'];
                $chk->close();
                if ($cnt > 0) $username .= $supplier_id;

                $hashed = password_hash("123456", PASSWORD_DEFAULT);
                $stmt_user = $conn->prepare("INSERT INTO users (username, name, password, role, created_at) VALUES (?, ?, ?, 'supplier', NOW())");
                if (!$stmt_user) throw new Exception('Failed to prepare user insert: ' . $conn->error);
                $stmt_user->bind_param("sss", $username, $name, $hashed);
                if (!$stmt_user->execute()) throw new Exception('Failed to create user: ' . $stmt_user->error);
                $user_id = $conn->insert_id;
                $stmt_user->close();

                $upd = $conn->prepare("UPDATE suppliers SET user_id = ? WHERE id = ?");
                if (!$upd) throw new Exception('Failed to prepare update: ' . $conn->error);
                $upd->bind_param("ii", $user_id, $supplier_id);
                if (!$upd->execute()) throw new Exception('Failed to link user: ' . $upd->error);
                $upd->close();

                $conn->commit();
                $response['success'] = true;
                $response['message'] = "Supplier added. Username: $username | Password: 123456";
            } catch (Exception $e) {
                $conn->rollback();
                $response['errors'] = [$e->getMessage()];
            }
        } else {
            $response['errors'] = $errors;
        }
    }

    // ── Edit Supplier ───────────────────────────────────
    if ($action === 'edit') {
        $errors = [];
        $id             = intval($_POST['id']           ?? 0);
        $name           = trim($_POST['name']           ?? '');
        $company        = trim($_POST['company']        ?? '');
        $address        = trim($_POST['address']        ?? '');
        $contact        = trim($_POST['contact']        ?? '');
        $total_buy      = floatval($_POST['total_buy']  ?? 0);
        $total_paid     = floatval($_POST['total_paid'] ?? 0);
        $total_due      = floatval($_POST['total_due']  ?? 0);
        $representative = trim($_POST['representative'] ?? '');
        $lead_time      = intval($_POST['lead_time']    ?? 0);

        if ($id <= 0)    $errors[] = 'Invalid supplier ID.';
        if (empty($name)) $errors[] = 'Name is required.';
        if (strlen($name) > 100 || !preg_match('/^[A-Za-z\s]+$/', $name)) $errors[] = 'Name must be letters and spaces only.';
        if (empty($company)) $errors[] = 'Company is required.';
        if (empty($address)) $errors[] = 'Address is required.';
        if (empty($contact)) $errors[] = 'Contact is required.';
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL) && !preg_match('/^\+?[0-9\s\-]{7,15}$/', $contact)) $errors[] = 'Contact must be a valid email or phone.';
        if ($total_due > $total_buy) $errors[] = 'Total Due cannot exceed Total Buy.';
        if ($lead_time < 0 || $lead_time > 365) $errors[] = 'Lead Time must be 0–365 days.';

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE suppliers SET name=?, company=?, address=?, contact=?, total_buy=?, total_paid=?, total_due=?, representative=?, lead_time=? WHERE id=?");
            if (!$stmt) {
                $response['errors'] = ['Prepare failed: ' . $conn->error];
            } else {
                $stmt->bind_param("ssssdddsii", $name, $company, $address, $contact, $total_buy, $total_paid, $total_due, $representative, $lead_time, $id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Supplier updated successfully.';
                } else {
                    $response['errors'] = ['Update failed: ' . $stmt->error];
                }
                $stmt->close();
            }
        } else {
            $response['errors'] = $errors;
        }
    }

    // ── Delete Supplier ─────────────────────────────────
    if ($action === 'delete') {
        $supplier_id = intval($_POST['id'] ?? 0);
        if ($supplier_id > 0) {
            $conn->begin_transaction();
            try {
                $get_uid = $conn->prepare("SELECT user_id FROM suppliers WHERE id = ?");
                $get_uid->bind_param("i", $supplier_id);
                $get_uid->execute();
                $uid_row = $get_uid->get_result()->fetch_assoc();
                $get_uid->close();

                $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->bind_param("i", $supplier_id);
                if (!$stmt->execute()) throw new Exception('Failed to delete supplier: ' . $stmt->error);
                $stmt->close();

                if (!empty($uid_row['user_id'])) {
                    $stmt_user = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'supplier'");
                    $stmt_user->bind_param("i", $uid_row['user_id']);
                    $stmt_user->execute();
                    $stmt_user->close();
                }

                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'Supplier and user account deleted successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $response['errors'] = [$e->getMessage()];
            }
        } else {
            $response['errors'] = ['Invalid supplier ID.'];
        }
    }

    // ── Add Medicine to Supplier ────────────────────────
    if ($action === 'add_medicine') {
        $supplier_id        = intval($_POST['supplier_id']        ?? 0);
        $medicine_id        = intval($_POST['medicine_id']        ?? 0);
        $unit_price         = floatval($_POST['unit_price']       ?? 0);
        $min_order_quantity = intval($_POST['min_order_quantity']  ?? 1);
        $quantity_supplied  = intval($_POST['quantity_supplied']   ?? 0);

        if ($supplier_id > 0 && $medicine_id > 0 && $unit_price >= 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("INSERT INTO supplier_medicines (supplier_id, medicine_id, unit_price, min_order_quantity, quantity_supplied) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE unit_price=VALUES(unit_price), min_order_quantity=VALUES(min_order_quantity), quantity_supplied=VALUES(quantity_supplied)");
                if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
                $stmt->bind_param("iidii", $supplier_id, $medicine_id, $unit_price, $min_order_quantity, $quantity_supplied);
                if (!$stmt->execute()) throw new Exception('Failed to add medicine: ' . $stmt->error);
                $stmt->close();
                $conn->commit();
                $response['success'] = true;
                $response['message'] = 'Medicine added to supplier successfully.';
            } catch (Exception $e) {
                $conn->rollback();
                $response['errors'] = [$e->getMessage()];
            }
        } else {
            $response['errors'] = ['Invalid input.'];
        }
    }

    // ── Edit Supplier Medicine ──────────────────────────
    if ($action === 'edit_supplier_medicine') {
        $supplier_id        = intval($_POST['supplier_id']        ?? 0);
        $medicine_id        = intval($_POST['medicine_id']        ?? 0);
        $unit_price         = floatval($_POST['unit_price']       ?? 0);
        $min_order_quantity = intval($_POST['min_order_quantity']  ?? 0);
        $quantity_supplied  = intval($_POST['quantity_supplied']   ?? 0);
        $preferred          = intval($_POST['preferred']           ?? 0);

        if ($supplier_id <= 0 || $medicine_id <= 0) {
            $response['errors'] = ['Invalid supplier or medicine ID.'];
        } elseif ($unit_price < 0) {
            $response['errors'] = ['Unit price cannot be negative.'];
        } else {
            $stmt = $conn->prepare("UPDATE supplier_medicines SET unit_price=?, min_order_quantity=?, quantity_supplied=?, preferred=? WHERE supplier_id=? AND medicine_id=?");
            if (!$stmt) {
                $response['errors'] = ['Prepare failed: ' . $conn->error];
            } else {
                $stmt->bind_param("diiiii", $unit_price, $min_order_quantity, $quantity_supplied, $preferred, $supplier_id, $medicine_id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Medicine supply details updated successfully.';
                } else {
                    $response['errors'] = ['Update failed: ' . $stmt->error];
                }
                $stmt->close();
            }
        }
    }

    // ── Delete Supplier Medicine ────────────────────────
    if ($action === 'delete_supplier_medicine') {
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $medicine_id = intval($_POST['medicine_id'] ?? 0);

        if ($supplier_id > 0 && $medicine_id > 0) {
            $stmt = $conn->prepare("DELETE FROM supplier_medicines WHERE supplier_id=? AND medicine_id=?");
            if (!$stmt) {
                $response['errors'] = ['Prepare failed: ' . $conn->error];
            } else {
                $stmt->bind_param("ii", $supplier_id, $medicine_id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Medicine removed from supplier successfully.';
                } else {
                    $response['errors'] = ['Delete failed: ' . $stmt->error];
                }
                $stmt->close();
            }
        } else {
            $response['errors'] = ['Invalid IDs.'];
        }
    }
}

// ══════════════════════════════════════════════════════
//  GET — fetch all suppliers (no supplier_id param)
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['supplier_id'])) {

    // If the request comes with explicit pagination params (e.g. from
    // a dedicated suppliers management page), honour them.
    // Otherwise (dashboard call with no params) return ALL suppliers.
    $paginate = isset($_GET['page']) || isset($_GET['per_page']);

    if ($paginate) {
        $page     = max(1, intval($_GET['page']     ?? 1));
        $per_page = max(1, intval($_GET['per_page'] ?? 20));
        $offset   = ($page - 1) * $per_page;
        $limit_clause = "LIMIT ?, ?";
    }

    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM suppliers");
    $total_suppliers = 0;
    if ($count_stmt) {
        $count_stmt->execute();
        $total_suppliers = intval($count_stmt->get_result()->fetch_assoc()['total']);
        $count_stmt->close();
    }

    $sql = "
        SELECT s.id, s.name, s.company, s.address, s.contact,
               s.total_buy, s.total_paid, s.total_due,
               s.representative, s.lead_time, s.created_at,
               COALESCE((
                   SELECT GROUP_CONCAT(DISTINCT m.name ORDER BY m.name SEPARATOR ', ')
                   FROM orders o
                   JOIN order_items oi ON oi.order_id = o.id
                   JOIN medicines m ON m.id = oi.medicine_id
                   WHERE o.supplier_id = s.id
                     AND o.status <> 'cancelled'
               ), '') AS bought_medicines,
               COALESCE((
                   SELECT SUM(oi.quantity)
                   FROM orders o
                   JOIN order_items oi ON oi.order_id = o.id
                   WHERE o.supplier_id = s.id
                     AND o.status <> 'cancelled'
               ), 0) AS bought_quantity
        FROM suppliers s
        ORDER BY s.name ASC
    ";

    if ($paginate) {
        $sql .= " LIMIT ?, ?";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($paginate) {
            $stmt->bind_param('ii', $offset, $per_page);
        }
        $stmt->execute();
        $result    = $stmt->get_result();
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = [
                'id'               => $row['id'],
                'name'             => $row['name'],
                'company'          => $row['company'],
                'contact'          => $row['contact'],
                'total_buy'        => $row['total_buy'],
                'total_paid'       => $row['total_paid'],
                'total_due'        => $row['total_due'],
                'representative'   => $row['representative'],
                'lead_time'        => $row['lead_time'],
                'created_at'       => $row['created_at'],
                'bought_medicines' => $row['bought_medicines'],
                'bought_quantity'  => $row['bought_quantity']
            ];
        }
        $response['success'] = true;
        $response['data']    = $suppliers;
        $response['meta']    = [
            'total'    => $total_suppliers,
            'returned' => count($suppliers),
            'page'     => $paginate ? $page : 1,
            'per_page' => $paginate ? $per_page : $total_suppliers
        ];
        $stmt->close();
    } else {
        $response['errors'] = ['Prepare failed: ' . $conn->error];
    }
}

// ══════════════════════════════════════════════════════
//  GET — fetch medicines for a specific supplier
// ══════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['supplier_id'])) {
    $supplier_id = intval($_GET['supplier_id']);
    if ($supplier_id <= 0) {
        $response['errors'] = ['Invalid supplier ID.'];
    } else {
        $stmt = $conn->prepare("
            SELECT m.id AS medicine_id, m.name,
                   sm.unit_price, sm.min_order_quantity,
                   sm.preferred,
                   COALESCE(si.quantity, sm.quantity_supplied, 0) AS quantity_supplied
            FROM supplier_medicines sm
            JOIN medicines m ON sm.medicine_id = m.id
            LEFT JOIN supplier_inventory si
              ON si.supplier_id = sm.supplier_id
             AND si.medicine_id = sm.medicine_id
            WHERE sm.supplier_id = ?
            ORDER BY m.name
        ");
        if ($stmt) {
            $stmt->bind_param("i", $supplier_id);
            $stmt->execute();
            $result    = $stmt->get_result();
            $medicines = [];
            while ($row = $result->fetch_assoc()) {
                $medicines[] = [
                    'medicine_id'        => $row['medicine_id'],
                    'name'               => $row['name'],
                    'unit_price'         => $row['unit_price'],
                    'min_order_quantity' => $row['min_order_quantity'],
                    'preferred'          => $row['preferred'],
                    'quantity_supplied'  => $row['quantity_supplied']
                ];
            }
            $response['success'] = true;
            $response['data']    = $medicines;
            $stmt->close();
        } else {
            $response['errors'] = ['Prepare failed: ' . $conn->error];
        }
    }
}

$conn->close();
ob_end_clean();
echo json_encode($response);
?>
