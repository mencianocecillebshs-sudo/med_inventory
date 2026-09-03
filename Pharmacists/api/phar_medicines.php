<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT id, name, barcode, quantity, category, item_type, description, expiry_date, created_at, selling_price FROM medicines WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            if ($data) {
                $data['is_low_stock'] = isLowStock($conn, $data['quantity']);
                $data['is_critical_stock'] = isCriticalStock($conn, $data['quantity']);
                $data['is_expiring_soon'] = isExpiringSoon($conn, $data['expiry_date']);
                $data['stock_status_badge'] = getStockStatusBadge($conn, $data['quantity']);
                $data['expiry_status_badge'] = getExpiryStatusBadge($conn, $data['expiry_date']);
                $data['formatted_expiry_date'] = formatUserDate($conn, $data['expiry_date']);

                echo json_encode($data);
            } else {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Medicine not found']);
            }
            $stmt->close();
        } else {
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $item_type = isset($_GET['item_type']) ? trim($_GET['item_type']) : '';

            $limit = getRecordsPerPage($conn);
            if (isset($_GET['limit'])) {
                $limit = max(10, min(1000, intval($_GET['limit'])));
            }

            $offset = ($page - 1) * $limit;
            $searchParam = "%$search%";

            $allowedSorts = ['name', 'quantity', 'expiry_date', 'created_at'];
            $orderBy = in_array($sort, $allowedSorts) ? "$sort ASC" : 'name ASC';

            $sql_count = "SELECT COUNT(*) FROM medicines WHERE (name LIKE ? OR barcode LIKE ? OR description LIKE ? OR category LIKE ?)";
            $sql_select = "SELECT id, name, barcode, quantity, category, item_type, description, expiry_date, created_at, selling_price FROM medicines WHERE (name LIKE ? OR barcode LIKE ? OR description LIKE ? OR category LIKE ?)";

            if ($item_type !== '') {
                $sql_count .= " AND item_type = ?";
                $sql_select .= " AND item_type = ?";
            }

            $sql_select .= " ORDER BY $orderBy LIMIT ? OFFSET ?";

            $countStmt = $conn->prepare($sql_count);
            if ($item_type !== '') {
                $countStmt->bind_param('sssss', $searchParam, $searchParam, $searchParam, $searchParam, $item_type);
            } else {
                $countStmt->bind_param('ssss', $searchParam, $searchParam, $searchParam, $searchParam);
            }
            $countStmt->execute();
            $total = $countStmt->get_result()->fetch_row()[0];
            $countStmt->close();

            $stmt = $conn->prepare($sql_select);
            if ($item_type !== '') {
                $stmt->bind_param('sssssii', $searchParam, $searchParam, $searchParam, $searchParam, $item_type, $limit, $offset);
            } else {
                $stmt->bind_param('ssssii', $searchParam, $searchParam, $searchParam, $searchParam, $limit, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $medicines = [];
            while ($row = $result->fetch_assoc()) {
                $row['is_low_stock'] = isLowStock($conn, $row['quantity']);
                $row['is_critical_stock'] = isCriticalStock($conn, $row['quantity']);
                $row['is_expiring_soon'] = isExpiringSoon($conn, $row['expiry_date']);
                $row['stock_status_badge'] = getStockStatusBadge($conn, $row['quantity']);
                $row['expiry_status_badge'] = getExpiryStatusBadge($conn, $row['expiry_date']);
                $row['formatted_expiry_date'] = formatUserDate($conn, $row['expiry_date']);
                $medicines[] = $row;
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'medicines' => $medicines,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]);
        }

    } elseif ($method === 'POST') {
        if (isset($_POST['_method']) && $_POST['_method'] === 'PUT') {
            $id = intval($_POST['edit_id']);
            $name = trim($_POST['edit_name']);
            $barcode = trim($_POST['edit_barcode']);
            $quantity = intval($_POST['edit_quantity']);
            $category = trim($_POST['edit_category'] ?? $_POST['edit_type'] ?? '');
            $item_type = trim($_POST['edit_item_type'] ?? 'medicine');
            $description = trim($_POST['edit_description']);
            $expiry = trim($_POST['edit_expiry_date']);
            $selling_price = floatval($_POST['edit_selling_price'] ?? 0);

            if (empty($name) || empty($barcode)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Name and barcode required']);
                exit();
            }

            $stmt = $conn->prepare("UPDATE medicines SET name=?, barcode=?, quantity=?, category=?, item_type=?, description=?, expiry_date=?, selling_price=? WHERE id=?");
            $stmt->bind_param('ssisssssi', $name, $barcode, $quantity, $category, $item_type, $description, $expiry, $selling_price, $id);
            $success = $stmt->execute();
            $stmt->close();

            if ($success) {
                createNotification($conn, "Medicine updated: $name", '');
                echo json_encode(['status' => 'success', 'message' => 'Updated']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Update failed']);
            }
        } else {
            $name = trim($_POST['name']);
            $barcode = trim($_POST['barcode']);
            $quantity = intval($_POST['quantity']);
            $category = trim($_POST['category'] ?? $_POST['type'] ?? '');
            $item_type = trim($_POST['item_type'] ?? 'medicine');
            $description = trim($_POST['description']);
            $expiry = trim($_POST['expiry_date']);
            $selling_price = floatval($_POST['selling_price'] ?? 0);

            if (empty($name) || empty($barcode)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Name and barcode required']);
                exit();
            }

            $checkStmt = $conn->prepare("SELECT id FROM medicines WHERE barcode = ?");
            $checkStmt->bind_param('s', $barcode);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Barcode exists']);
                $checkStmt->close();
                exit();
            }
            $checkStmt->close();

            $stmt = $conn->prepare("INSERT INTO medicines (name, barcode, quantity, category, item_type, description, expiry_date, selling_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('ssissssd', $name, $barcode, $quantity, $category, $item_type, $description, $expiry, $selling_price);
            $success = $stmt->execute();
            $insertId = $conn->insert_id;
            $stmt->close();

            if ($success) {
                createNotification($conn, "New medicine: $name", '');
                http_response_code(201);
                echo json_encode(['status' => 'success', 'message' => 'Added', 'id' => $insertId]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed']);
            }
        }

    } elseif ($method === 'DELETE') {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID required']);
            exit();
        }
        $id = intval($_GET['id']);
        $nameStmt = $conn->prepare("SELECT name FROM medicines WHERE id = ?");
        $nameStmt->bind_param('i', $id);
        $nameStmt->execute();
        $name = $nameStmt->get_result()->fetch_row()[0] ?? 'Unknown';
        $nameStmt->close();

        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            createNotification($conn, "Deleted: $name", '');
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}

$conn->close();
?>
