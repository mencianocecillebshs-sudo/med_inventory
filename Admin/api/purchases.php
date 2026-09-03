<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            $query = "SELECT p.*, m.name as medicine_name, u1.username as pharmacist_name, u2.username as filled_by_name 
                      FROM purchases p 
                      JOIN medicines m ON p.medicine_id = m.id 
                      LEFT JOIN users u1 ON p.pharmacist_id = u1.id 
                      LEFT JOIN users u2 ON p.filled_by_user_id = u2.id 
                      WHERE p.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $purchase = $result->fetch_assoc();
            if (!$purchase) {
                http_response_code(404);
                echo json_encode(['error' => 'Purchase not found']);
                exit;
            }
            echo json_encode($purchase);
        } else {
            $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : getRecordsPerPage($conn);
            $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
            $medicine_id = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;
            $status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

            $where = ['1=1'];
            if ($search) {
                $where[] = "(p.purchase_number LIKE '%$search%' OR m.name LIKE '%$search%' OR p.notes LIKE '%$search%')";
            }
            if ($medicine_id) {
                $where[] = "p.medicine_id = $medicine_id";
            }
            if ($status) {
                $where[] = "p.status = '$status'";
            }
            $where_sql = implode(' AND ', $where);

            $count_query = "SELECT COUNT(*) as total FROM purchases p JOIN medicines m ON p.medicine_id = m.id WHERE $where_sql";
            $count_result = $conn->query($count_query);
            $total = $count_result->fetch_assoc()['total'];

            if ($per_page >= 9999) {
                $query = "SELECT p.*, m.name as medicine_name, u1.username as pharmacist_name, u2.username as filled_by_name 
                          FROM purchases p 
                          JOIN medicines m ON p.medicine_id = m.id 
                          LEFT JOIN users u1 ON p.pharmacist_id = u1.id 
                          LEFT JOIN users u2 ON p.filled_by_user_id = u2.id 
                          WHERE $where_sql ORDER BY p.purchase_date DESC, p.id DESC";
            } else {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $offset = ($page - 1) * $per_page;
                $query = "SELECT p.*, m.name as medicine_name, u1.username as pharmacist_name, u2.username as filled_by_name 
                          FROM purchases p 
                          JOIN medicines m ON p.medicine_id = m.id 
                          LEFT JOIN users u1 ON p.pharmacist_id = u1.id 
                          LEFT JOIN users u2 ON p.filled_by_user_id = u2.id 
                          WHERE $where_sql ORDER BY p.purchase_date DESC, p.id DESC LIMIT $per_page OFFSET $offset";
            }
            
            $result = $conn->query($query);
            $purchases = [];
            while ($row = $result->fetch_assoc()) {
                $purchases[] = $row;
            }
            echo json_encode(['data' => $purchases, 'total' => $total]);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['medicine_id']) || !isset($input['quantity']) || !isset($input['total_cost'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields: medicine_id, quantity, total_cost']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $today = date('Ymd');
            $max_query = "SELECT MAX(CAST(SUBSTRING(purchase_number, 12) AS UNSIGNED)) as max_num 
                          FROM purchases 
                          WHERE purchase_number LIKE 'P-$today-%'";
            $max_result = $conn->query($max_query);
            $max_num = $max_result->fetch_assoc()['max_num'] ?? 0;
            $count = $max_num + 1;
            $purchase_number = 'P-' . $today . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO purchases (medicine_id, pharmacist_id, purchase_number, purchase_date, quantity, status, notes, total_cost) 
                                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
            $purchase_date = $input['purchase_date'] ?? date('Y-m-d');
            $notes = $input['notes'] ?? '';
            $stmt->bind_param("iissisd", $input['medicine_id'], $user_id, $purchase_number, $purchase_date, $input['quantity'], $notes, $input['total_cost']);
            $stmt->execute();
            $new_id = $conn->insert_id;

            $conn->commit();
            echo json_encode(['success' => true, 'id' => $new_id, 'purchase_number' => $purchase_number, 'message' => 'Purchase created successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create purchase: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing purchase ID']);
            exit;
        }
        $id = (int)$input['id'];

        $old_query = $conn->prepare("SELECT p.*, m.name as medicine_name FROM purchases p JOIN medicines m ON p.medicine_id = m.id WHERE p.id = ?");
        $old_query->bind_param("i", $id);
        $old_query->execute();
        $old_row = $old_query->get_result()->fetch_assoc();
        if (!$old_row) {
            http_response_code(404);
            echo json_encode(['error' => 'Purchase not found']);
            exit;
        }

        // This endpoint only manages a purchase while it's still pending. It can no longer
        // mark a purchase 'filled' or touch stock/transactions — that only happens through
        // Sales checkout now (sales.php), so there's exactly one place a purchase gets
        // finalized instead of two paths that can disagree with each other.
        if (isset($input['status']) && $input['status'] === 'filled') {
            http_response_code(400);
            echo json_encode(['error' => "Purchases are fulfilled through checkout on the Sales page, not here. Use \"Ring Up\" to send this purchase to Sales."]);
            exit;
        }
        if (isset($input['status']) && !in_array($input['status'], ['pending', 'cancelled'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid status. This endpoint only accepts pending or cancelled.']);
            exit;
        }
        if ($old_row['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(['error' => "Purchase #{$id} is already {$old_row['status']} and can't be edited."]);
            exit;
        }

        $fields = [];
        $params = [];
        $types = '';
        if (isset($input['medicine_id'])) { $fields[] = 'medicine_id = ?'; $params[] = (int)$input['medicine_id']; $types .= 'i'; }
        if (isset($input['quantity'])) { $fields[] = 'quantity = ?'; $params[] = (int)$input['quantity']; $types .= 'i'; }
        if (isset($input['purchase_date'])) { $fields[] = 'purchase_date = ?'; $params[] = $input['purchase_date']; $types .= 's'; }
        if (isset($input['status'])) { $fields[] = 'status = ?'; $params[] = $input['status']; $types .= 's'; }
        if (isset($input['notes'])) { $fields[] = 'notes = ?'; $params[] = $input['notes']; $types .= 's'; }
        if (isset($input['total_cost'])) { $fields[] = 'total_cost = ?'; $params[] = (float)$input['total_cost']; $types .= 'd'; }
        $params[] = $id;
        $types .= 'i';

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }

        $update_sql = "UPDATE purchases SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param($types, ...$params);

        $conn->begin_transaction();
        try {
            $stmt->execute();
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Purchase updated successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing purchase ID']);
            exit;
        }
        $id = (int)$input['id'];

        $conn->begin_transaction();
        try {
            $delete_stmt = $conn->prepare("DELETE FROM purchases WHERE id = ?");
            $delete_stmt->bind_param("i", $id);
            $delete_stmt->execute();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Purchase deleted successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete purchase: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>