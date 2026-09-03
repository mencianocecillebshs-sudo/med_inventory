<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'supplier') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$supplier_id = (int)($_SESSION['supplier_id'] ?? 0);
if ($supplier_id === 0) {
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $supplier_id = $result->fetch_assoc()['id'];
        $_SESSION['supplier_id'] = $supplier_id;
    }
    $stmt->close();
}

if ($supplier_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Supplier profile not found']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$medicine_id = (int)($input['medicine_id'] ?? 0);
$qty_change = (int)($input['qty'] ?? 0);

if ($medicine_id <= 0 || $qty_change == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit();
}

$conn->begin_transaction();

try {
    // Get current quantity
    $stmt = $conn->prepare("SELECT quantity FROM supplier_inventory WHERE supplier_id = ? AND medicine_id = ?");
    $stmt->bind_param('ii', $supplier_id, $medicine_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Medicine not found in inventory');
    }
    
    $current_qty = (int)$result->fetch_assoc()['quantity'];
    $stmt->close();

    $new_qty = $current_qty + $qty_change;

    if ($new_qty < 0) {
        throw new Exception('Cannot reduce stock below 0');
    }

    // Update quantity
    $updateStmt = $conn->prepare("UPDATE supplier_inventory SET quantity = ? WHERE supplier_id = ? AND medicine_id = ?");
    $updateStmt->bind_param('iii', $new_qty, $supplier_id, $medicine_id);
    $updateStmt->execute();
    $updateStmt->close();

    $transactionAction = $qty_change > 0 ? 'add' : 'remove';
    $transactionQuantity = abs($qty_change);
    $transactionNotes = "Manual stock adjustment: {$current_qty} to {$new_qty}";
    $transactionStmt = $conn->prepare("\n        INSERT INTO supplier_transactions\n            (user_id, supplier_id, medicine_id, action, transaction_type, quantity, notes)\n        VALUES (?, ?, ?, ?, 'adjustment', ?, ?)\n    ");
    if (!$transactionStmt) {
        throw new Exception('Stock transaction prepare failed');
    }
    $transactionStmt->bind_param(
        'iiisis',
        $_SESSION['user_id'],
        $supplier_id,
        $medicine_id,
        $transactionAction,
        $transactionQuantity,
        $transactionNotes
    );
    if (!$transactionStmt->execute()) {
        throw new Exception('Stock transaction insert failed: ' . $transactionStmt->error);
    }
    $transactionStmt->close();

    // Get medicine name for logging
    $nameStmt = $conn->prepare("SELECT name FROM medicines WHERE id = ?");
    $nameStmt->bind_param('i', $medicine_id);
    $nameStmt->execute();
    $nameResult = $nameStmt->get_result();
    $medicine_name = $nameResult->fetch_assoc()['name'] ?? 'Unknown';
    $nameStmt->close();

    // Log activity
    $activity_desc = "Adjusted stock for {$medicine_name}: {$current_qty} → {$new_qty} (change: {$qty_change})";
    $logStmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_type, activity_description, created_at) VALUES (?, 'adjust_stock', ?, NOW())");
    $logStmt->bind_param('is', $_SESSION['user_id'], $activity_desc);
    $logStmt->execute();
    $logStmt->close();

    $conn->commit();

    $visibility_note = '';
    if ($current_qty == 0 && $new_qty > 0) {
        $visibility_note = ' Medicine is now visible in your catalog and available for admin orders.';
    } elseif ($new_qty == 0) {
        $visibility_note = ' Medicine is now hidden from your catalog and unavailable for admin orders.';
    }

    echo json_encode([
        'status' => 'success', 
        'message' => "Stock adjusted from {$current_qty} to {$new_qty}.{$visibility_note}",
        'new_quantity' => $new_qty,
        'medicine_name' => $medicine_name
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>