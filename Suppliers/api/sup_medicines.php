<?php
// Updated sup_medicines.php
// Changes: Minor improvements to error handling and logging for consistency.
// No major changes needed, as this was already outputting JSON properly.
// Ensured compatibility with the updated inventory logic (includes 0 stock).

// Clean all output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start fresh output buffering
ob_start();

// Start session
session_start();

// Set headers FIRST before any output
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Error handling - log only, don't display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// JSON response helper
function jsonResponse($data, $httpCode = 200) {
    // Clean any remaining output
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function syncMedicineSuppliers(mysqli $conn, int $medicineId): void {
    $stmt = $conn->prepare("
        UPDATE medicines m
        SET m.suppliers = (
            SELECT GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ')
            FROM supplier_medicines sm
            JOIN suppliers s ON sm.supplier_id = s.id
            WHERE sm.medicine_id = m.id
        )
        WHERE m.id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $medicineId);
        $stmt->execute();
        $stmt->close();
    }
}

// Database connection - prefer local Suppliers/config/db.php first
$debug = (isset($_GET['debug']) && $_GET['debug'] === '1');
// Auto-enable debug for localhost requests to aid local troubleshooting
if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    $debug = true;
}
$dbPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../Config/db.php'
];

$dbConnected = false;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
                $dbConnected = true;
                $usedDbPath = $path;
                break;
            } else {
                // included file but connection failed
                error_log("DB include succeeded but connection failed for $path: " . ($conn->connect_error ?? 'no $conn'));
            }
        } catch (Exception $e) {
            error_log("Failed to load DB from $path: " . $e->getMessage());
        }
    }
}

if (!$dbConnected || !isset($conn) || !$conn) {
    $payload = ['status' => 'error', 'message' => 'Database connection failed'];
    if ($debug) {
        $payload['tried_paths'] = $dbPaths;
        $payload['connect_error'] = isset($conn) ? ($conn->connect_error ?? 'unknown') : 'conn not set';
    }
    jsonResponse($payload, 500);
}

require_once __DIR__ . '/../includes/supplier_auth.php';

// Allow a local debug override: ?debug=1&supplier_id=NN will let you load data for that supplier when testing on localhost.
$is_supplier = false;
$supplier_id = 0;
if (!empty($debug) && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    if (isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id'])) {
        $supplier_id = (int)$_GET['supplier_id'];
        $is_supplier = true;
    }
}

if (!$is_supplier) {
    $supplierProfile = requireSupplierSession($conn);
    $supplier_id = (int)$supplierProfile['id'];
    $is_supplier = true;
}

// Activity logging function
if (!function_exists('logUserActivity')) {
    function logUserActivity($user_id, $type, $data) {
        global $conn;
        try {
            $description = json_encode($data);
            $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_type, activity_description, created_at) VALUES (?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param('iss', $user_id, $type, $description);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ============================================
    // GET REQUESTS
    // ============================================
    if ($method === 'GET') {
        
        // LIST ALL MEDICINES
        if (!isset($_GET['id'])) {
            $searchTerm = trim((string)($_GET['search'] ?? ''));
            $search = '%' . $searchTerm . '%';
            $colExists = function(mysqli $c, string $table, string $col): bool {
                $tableEsc = $c->real_escape_string($table);
                $colEsc = $c->real_escape_string($col);
                $res = $c->query("SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
                return $res && $res->num_rows > 0;
            };

            $sel_barcode = $colExists($conn, 'medicines', 'barcode') ? "COALESCE(m.barcode, '')" : "''";
            $sel_type = $colExists($conn, 'medicines', 'type') ? 'm.type' : "''";
            $sel_description = $colExists($conn, 'medicines', 'description') ? 'm.description' : "''";
            $sel_expiry = $colExists($conn, 'medicines', 'expiry_date') ? 'm.expiry_date' : "NULL";
            $stockFilter = $_GET['stock_filter'] ?? '';
            require_once __DIR__ . '/pagination_helper.php';
            $paginationParams = supGetPaginationParams($conn);
            $limit = $paginationParams['limit'];
            $offset = $paginationParams['offset'];
            $page = $paginationParams['page'];
            $sort = in_array($_GET['sort'] ?? 'name', ['name', 'quantity', 'type']) ? $_GET['sort'] : 'name';

            $baseSql = "SELECT 
                            m.id,
                            m.name,
                            {$sel_barcode} AS barcode,
                            {$sel_type} AS type,
                            {$sel_description} AS description,
                            {$sel_expiry} AS expiry_date,
                            COALESCE(NULLIF(sm.unit_price, 0), si.unit_price, 0) as unit_price,
                            COALESCE(sm.min_order_quantity, 1) as min_order_quantity,
                            COALESCE(sm.preferred, 0) as preferred,
                            COALESCE(si.quantity, 0) as quantity,
                            CASE 
                                WHEN si.quantity > 0 THEN 'in_stock'
                                ELSE 'out_of_stock'
                            END as stock_status
                        FROM medicines m
                        LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND sm.supplier_id = ?
                        LEFT JOIN supplier_inventory si ON m.id = si.medicine_id AND si.supplier_id = ?
                        WHERE m.name LIKE ?
                          AND (sm.supplier_id = ? OR si.supplier_id = ?)";

            $params = [$supplier_id, $supplier_id, $search, $supplier_id, $supplier_id];
            $types = 'iisii';

            if ($stockFilter === 'in_stock') {
                $baseSql .= " AND COALESCE(si.quantity, 0) > 0";
            } elseif ($stockFilter === 'out_of_stock') {
                $baseSql .= " AND COALESCE(si.quantity, 0) = 0";
            }

            $countSql = "SELECT COUNT(DISTINCT m.id) as total FROM medicines m LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND sm.supplier_id = ? LEFT JOIN supplier_inventory si ON m.id = si.medicine_id AND si.supplier_id = ? WHERE m.name LIKE ? AND (sm.supplier_id = ? OR si.supplier_id = ?)";
            if ($stockFilter === 'in_stock') {
                $countSql .= " AND COALESCE(si.quantity, 0) > 0";
            } elseif ($stockFilter === 'out_of_stock') {
                $countSql .= " AND COALESCE(si.quantity, 0) = 0";
            }

            $countStmt = $conn->prepare($countSql);
            if (!$countStmt) throw new Exception('Count prepare failed: ' . $conn->error);
            $countStmt->bind_param('iisii', $supplier_id, $supplier_id, $search, $supplier_id, $supplier_id);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalItems = $countResult->fetch_assoc()['total'] ?? 0;
            $countStmt->close();

            $orderBy = "m.$sort ASC";
            if ($sort === 'quantity') {
                $orderBy = "COALESCE(si.quantity, 0) DESC";
            }

            $limit = (int)$limit;
            $offset = (int)$offset;
            $fullSql = "$baseSql ORDER BY $orderBy LIMIT $limit OFFSET $offset";
            $stmt = $conn->prepare($fullSql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $fallbackSql = "SELECT m.id, m.name, {$sel_barcode} AS barcode, {$sel_type} AS type, {$sel_description} AS description, {$sel_expiry} AS expiry_date,
                        COALESCE(NULLIF(sm.unit_price, 0), si.unit_price, 0) as unit_price,
                        COALESCE(sm.min_order_quantity, 1) as min_order_quantity,
                        COALESCE(sm.preferred, 0) as preferred,
                        COALESCE(si.quantity, 0) as quantity,
                        CASE WHEN si.quantity > 0 THEN 'in_stock' ELSE 'out_of_stock' END as stock_status
                        FROM medicines m
                        LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND sm.supplier_id = ?
                        LEFT JOIN supplier_inventory si ON m.id = si.medicine_id AND si.supplier_id = ?
                        WHERE m.name LIKE ?
                          AND (sm.supplier_id = ? OR si.supplier_id = ?)
                          ORDER BY m.name ASC LIMIT ? OFFSET ?";

                $fbStmt = $conn->prepare($fallbackSql);
                if (!$fbStmt) throw new Exception('Fallback prepare failed: ' . $conn->error);
                $fbStmt->bind_param('iisiii', $supplier_id, $supplier_id, $search, $supplier_id, $supplier_id, $limit, $offset);
                $fbStmt->execute();
                $result = $fbStmt->get_result();
            }

            $medicines = [];
            while ($row = $result->fetch_assoc()) {
                $medicines[] = $row;
            }
            if (isset($stmt) && $stmt) $stmt->close();

            jsonResponse([
                'status' => 'success',
                'data' => $medicines,
                'pagination' => supBuildPaginationMeta($page, $limit, $totalItems),
            ]);
        }

        // GET SINGLE MEDICINE
        elseif (isset($_GET['id'])) {
            $medicine_id = (int)$_GET['id'];
            $columnExists = function (mysqli $database, string $column): bool {
                $safeColumn = $database->real_escape_string($column);
                $result = $database->query("SHOW COLUMNS FROM medicines LIKE '{$safeColumn}'");
                return $result && $result->num_rows > 0;
            };
            $sel_barcode = $columnExists($conn, 'barcode') ? 'COALESCE(m.barcode, \'\')' : "''";
            $sel_type = $columnExists($conn, 'type') ? 'm.type' : "''";
            $sel_description = $columnExists($conn, 'description') ? 'm.description' : "''";
            $sel_expiry = $columnExists($conn, 'expiry_date') ? 'm.expiry_date' : 'NULL';
            // Single-medicine select: use safe column expressions to avoid referencing missing columns
            $sql = "SELECT 
                        m.id, m.name, {$sel_barcode} AS barcode, {$sel_type} AS type, {$sel_description} AS description, {$sel_expiry} AS expiry_date,
                        COALESCE(NULLIF(sm.unit_price, 0), si.unit_price, 0) as unit_price,
                        COALESCE(sm.min_order_quantity, 1) as min_order_quantity,
                        COALESCE(sm.preferred, 0) as preferred,
                        COALESCE(si.quantity, 0) as quantity
                    FROM medicines m
                    INNER JOIN (
                        SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ?
                        UNION
                        SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ?
                    ) owned ON owned.medicine_id = m.id
                    LEFT JOIN supplier_medicines sm ON m.id = sm.medicine_id AND sm.supplier_id = ?
                    LEFT JOIN supplier_inventory si ON m.id = si.medicine_id AND si.supplier_id = ?
                    WHERE m.id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
            $stmt->bind_param('iiiii', $supplier_id, $supplier_id, $supplier_id, $supplier_id, $medicine_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                jsonResponse(['status' => 'error', 'message' => 'Medicine not found'], 404);
            }
            jsonResponse(['status' => 'success', 'data' => $result->fetch_assoc()]);
            $stmt->close();
        }

    // ============================================
    // POST REQUESTS
    // ============================================
    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';

        // RECORD MANUAL SUPPLY
        if ($action === 'add_supply' && $is_supplier) {
            $medicine_id = intval($_POST['medicine_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 0);
            $supply_date = trim((string)($_POST['supply_date'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $date = DateTime::createFromFormat('!Y-m-d', $supply_date);
            $dateErrors = DateTime::getLastErrors();
            $dateIsValid = $date !== false && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) && $date->format('Y-m-d') === $supply_date;

            if ($medicine_id <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid medicine ID'], 400);
            }
            if ($quantity <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Supply quantity must be greater than 0'], 400);
            }
            if (!$dateIsValid || $supply_date > date('Y-m-d')) {
                jsonResponse(['status' => 'error', 'message' => 'Supply date must be valid and cannot be in the future'], 400);
            }
            if (strlen($notes) > 500) {
                jsonResponse(['status' => 'error', 'message' => 'Notes cannot exceed 500 characters'], 400);
            }

            $conn->begin_transaction();
            try {
                $ownerStmt = $conn->prepare("\n                    SELECT COALESCE(si.quantity, 0) AS current_quantity,\n                           COALESCE(NULLIF(si.unit_price, 0), sm.unit_price, 0) AS unit_price\n                    FROM medicines m\n                    INNER JOIN (\n                        SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ?\n                        UNION\n                        SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ?\n                    ) owned ON owned.medicine_id = m.id\n                    LEFT JOIN supplier_inventory si ON si.supplier_id = ? AND si.medicine_id = m.id\n                    LEFT JOIN supplier_medicines sm ON sm.supplier_id = ? AND sm.medicine_id = m.id\n                    WHERE m.id = ?\n                    LIMIT 1\n                ");
                if (!$ownerStmt) {
                    throw new Exception('Supply ownership check failed: ' . $conn->error);
                }
                $ownerStmt->bind_param('iiiii', $supplier_id, $supplier_id, $supplier_id, $supplier_id, $medicine_id);
                $ownerStmt->execute();
                $owner = $ownerStmt->get_result()->fetch_assoc();
                $ownerStmt->close();
                if (!$owner) {
                    throw new Exception('Medicine is not in your inventory');
                }

                $currentQuantity = (int)$owner['current_quantity'];
                $newQuantity = $currentQuantity + $quantity;
                $unitPrice = (float)$owner['unit_price'];

                $inventoryStmt = $conn->prepare("\n                    INSERT INTO supplier_inventory (supplier_id, medicine_id, quantity, unit_price)\n                    VALUES (?, ?, ?, ?)\n                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)\n                ");
                if (!$inventoryStmt) {
                    throw new Exception('Supply inventory prepare failed: ' . $conn->error);
                }
                $inventoryStmt->bind_param('iiid', $supplier_id, $medicine_id, $quantity, $unitPrice);
                if (!$inventoryStmt->execute()) {
                    throw new Exception('Supply inventory update failed: ' . $inventoryStmt->error);
                }
                $inventoryStmt->close();

                $suppliedStmt = $conn->prepare("UPDATE supplier_medicines SET quantity_supplied = COALESCE(quantity_supplied, 0) + ? WHERE supplier_id = ? AND medicine_id = ?");
                if (!$suppliedStmt) {
                    throw new Exception('Supply total prepare failed: ' . $conn->error);
                }
                $suppliedStmt->bind_param('iii', $quantity, $supplier_id, $medicine_id);
                if (!$suppliedStmt->execute()) {
                    throw new Exception('Supply total update failed: ' . $suppliedStmt->error);
                }
                $suppliedStmt->close();

                $timestamp = $supply_date . ' 00:00:00';
                $transactionNotes = $notes !== '' ? $notes : 'Manual supply entry';
                $transactionStmt = $conn->prepare("\n                    INSERT INTO supplier_transactions\n                        (user_id, supplier_id, medicine_id, action, transaction_type, quantity, notes, timestamp)\n                    VALUES (?, ?, ?, 'add', 'stock_in', ?, ?, ?)\n                ");
                if (!$transactionStmt) {
                    throw new Exception('Supply transaction prepare failed: ' . $conn->error);
                }
                $transactionStmt->bind_param('iiiiss', $_SESSION['user_id'], $supplier_id, $medicine_id, $quantity, $transactionNotes, $timestamp);
                if (!$transactionStmt->execute()) {
                    throw new Exception('Supply transaction insert failed: ' . $transactionStmt->error);
                }
                $transactionStmt->close();

                syncMedicineSuppliers($conn, $medicine_id);
                $conn->commit();

                logUserActivity($_SESSION['user_id'], 'add_supply', [
                    'medicine_id' => $medicine_id,
                    'supplier_id' => $supplier_id,
                    'quantity' => $quantity,
                    'supply_date' => $supply_date
                ]);

                jsonResponse(['status' => 'success', 'message' => "Supply recorded. Stock increased from {$currentQuantity} to {$newQuantity}."]);
            } catch (Exception $e) {
                $conn->rollback();
                error_log('Add supply error: ' . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        // SET STOCK
        if ($action === 'set_stock' && $is_supplier) {
            $medicine_id = intval($_POST['medicine_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 0);
            $unit_price = floatval($_POST['unit_price'] ?? 0);
            $min_order_quantity = intval($_POST['min_order_quantity'] ?? 1);
            $preferred = isset($_POST['preferred']) ? 1 : 0;

            if ($medicine_id <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid medicine ID'], 400);
            }

            if ($quantity < 0) {
                jsonResponse(['status' => 'error', 'message' => 'Quantity cannot be negative'], 400);
            }

            if ($unit_price <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Unit price must be greater than 0'], 400);
            }

            $conn->begin_transaction();
            try {
                // Check if medicine exists
                $checkStmt = $conn->prepare("SELECT id FROM medicines WHERE id = ?");
                $checkStmt->bind_param('i', $medicine_id);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows === 0) {
                    throw new Exception('Medicine not found');
                }
                $checkStmt->close();

                // Update or insert supplier_medicines
                $smCheck = $conn->prepare("SELECT supplier_id FROM supplier_medicines WHERE supplier_id = ? AND medicine_id = ?");
                $smCheck->bind_param('ii', $supplier_id, $medicine_id);
                $smCheck->execute();
                $smExists = $smCheck->get_result()->num_rows > 0;
                $smCheck->close();

                if ($smExists) {
                    $smStmt = $conn->prepare("UPDATE supplier_medicines SET unit_price = ?, min_order_quantity = ?, preferred = ?, quantity_supplied = ? WHERE supplier_id = ? AND medicine_id = ?");
                    $smStmt->bind_param('diiiii', $unit_price, $min_order_quantity, $preferred, $quantity, $supplier_id, $medicine_id);
                } else {
                    $smStmt = $conn->prepare("INSERT INTO supplier_medicines (supplier_id, medicine_id, unit_price, min_order_quantity, preferred, quantity_supplied) VALUES (?, ?, ?, ?, ?, ?)");
                    $smStmt->bind_param('iidiii', $supplier_id, $medicine_id, $unit_price, $min_order_quantity, $preferred, $quantity);
                }
                $smStmt->execute();
                $smStmt->close();

                // Update or insert inventory
                $invCheck = $conn->prepare("SELECT supplier_id FROM supplier_inventory WHERE supplier_id = ? AND medicine_id = ?");
                $invCheck->bind_param('ii', $supplier_id, $medicine_id);
                $invCheck->execute();
                $invExists = $invCheck->get_result()->num_rows > 0;
                $invCheck->close();

                if ($invExists) {
                    $invStmt = $conn->prepare("UPDATE supplier_inventory SET quantity = ?, unit_price = ? WHERE supplier_id = ? AND medicine_id = ?");
                    $invStmt->bind_param('idii', $quantity, $unit_price, $supplier_id, $medicine_id);
                } else {
                    $invStmt = $conn->prepare("INSERT INTO supplier_inventory (supplier_id, medicine_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                    $invStmt->bind_param('iiid', $supplier_id, $medicine_id, $quantity, $unit_price);
                }
                $invStmt->execute();
                $invStmt->close();

                syncMedicineSuppliers($conn, $medicine_id);
                $conn->commit();

                $msg = 'Stock updated successfully. ';
                if ($quantity > 0) {
                    $msg .= 'This medicine is now available for admin orders.';
                } else {
                    $msg .= 'Stock is 0 - medicine will not appear in admin orders until restocked.';
                }

                logUserActivity($_SESSION['user_id'], 'set_stock', [
                    'medicine_id' => $medicine_id,
                    'supplier_id' => $supplier_id,
                    'quantity' => $quantity
                ]);

                jsonResponse(['status' => 'success', 'message' => $msg]);

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Set stock error: " . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        // CREATE NEW MEDICINE
        elseif ($action === 'create_medicine' && $is_supplier) {
            // Get and sanitize inputs
            $name = trim($_POST['name'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $expiry_date_input = trim($_POST['expiry_date'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 0);
            $unit_price = floatval($_POST['unit_price'] ?? 0);
            $min_order_quantity = intval($_POST['min_order_quantity'] ?? 1);
            $preferred = isset($_POST['preferred']) ? 1 : 0;

            // Validate required fields
            if (empty($name)) {
                jsonResponse(['status' => 'error', 'message' => 'Medicine name is required'], 400);
            }

            if ($unit_price <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Unit price must be greater than 0'], 400);
            }

            if ($quantity < 0) {
                jsonResponse(['status' => 'error', 'message' => 'Quantity cannot be negative'], 400);
            }

            $barcode = $barcode !== '' ? $barcode : null;

            // Handle expiry date - convert empty string to NULL
            $expiry_date = null;
            if (!empty($expiry_date_input)) {
                $expiry_date = $expiry_date_input;
            }

            $conn->begin_transaction();
            try {
                if ($barcode !== null) {
                    $barcodeCheck = $conn->prepare("SELECT id FROM medicines WHERE barcode = ?");
                    if (!$barcodeCheck) {
                        throw new Exception('Barcode check prepare failed: ' . $conn->error);
                    }
                    $barcodeCheck->bind_param('s', $barcode);
                    $barcodeCheck->execute();
                    if ($barcodeCheck->get_result()->num_rows > 0) {
                        throw new Exception('Barcode already exists');
                    }
                    $barcodeCheck->close();
                }

                // Insert medicine into catalog. Pharmacy stock stays separate from supplier inventory.
                $catalogQuantity = 0;
                $typeColumnCheck = $conn->query("SHOW COLUMNS FROM medicines LIKE 'type'");
                $hasTypeColumn = $typeColumnCheck && $typeColumnCheck->num_rows > 0;
                if ($hasTypeColumn) {
                    $insertSql = "INSERT INTO medicines (name, barcode, quantity, type, description, expiry_date, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                } else {
                    $insertSql = "INSERT INTO medicines (name, barcode, quantity, description, expiry_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
                }
                $insertStmt = $conn->prepare($insertSql);
                if (!$insertStmt) {
                    throw new Exception('Insert prepare failed: ' . $conn->error);
                }

                if ($hasTypeColumn) {
                    $insertStmt->bind_param('ssisss', $name, $barcode, $catalogQuantity, $type, $description, $expiry_date);
                } else {
                    $insertStmt->bind_param('ssiss', $name, $barcode, $catalogQuantity, $description, $expiry_date);
                }
                
                if (!$insertStmt->execute()) {
                    throw new Exception('Failed to create medicine: ' . $insertStmt->error);
                }
                
                $medicine_id = $conn->insert_id;
                $insertStmt->close();

                // Link to supplier with pricing
                $linkStmt = $conn->prepare("INSERT INTO supplier_medicines (supplier_id, medicine_id, unit_price, min_order_quantity, preferred, quantity_supplied) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$linkStmt) {
                    throw new Exception('Link prepare failed: ' . $conn->error);
                }
                
                $linkStmt->bind_param('iidiii', $supplier_id, $medicine_id, $unit_price, $min_order_quantity, $preferred, $quantity);
                
                if (!$linkStmt->execute()) {
                    throw new Exception('Failed to link medicine: ' . $linkStmt->error);
                }
                $linkStmt->close();

                // Add initial inventory
                $invStmt = $conn->prepare("INSERT INTO supplier_inventory (supplier_id, medicine_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
                if (!$invStmt) {
                    throw new Exception('Inventory prepare failed: ' . $conn->error);
                }
                
                $invStmt->bind_param('iiid', $supplier_id, $medicine_id, $quantity, $unit_price);
                
                if (!$invStmt->execute()) {
                    throw new Exception('Failed to add inventory: ' . $invStmt->error);
                }
                $invStmt->close();

                syncMedicineSuppliers($conn, $medicine_id);
                $conn->commit();

                $msg = 'Medicine added to catalog successfully! ';
                if ($quantity > 0) {
                    $msg .= 'It is now available for admin orders.';
                } else {
                    $msg .= 'Stock is 0 - add stock to make it available for orders.';
                }

                logUserActivity($_SESSION['user_id'], 'create_medicine', [
                    'medicine_id' => $medicine_id,
                    'supplier_id' => $supplier_id,
                    'name' => $name,
                    'quantity' => $quantity
                ]);

                jsonResponse(['status' => 'success', 'message' => $msg, 'medicine_id' => $medicine_id]);

            } catch (Exception $e) {
                $conn->rollback();
                error_log("Create medicine error: " . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => 'Failed to create medicine: ' . $e->getMessage()], 500);
            }
        }

        // UPDATE MEDICINE + SUPPLIER INVENTORY
        elseif ($action === 'update_medicine' && $is_supplier) {
            $medicine_id = intval($_POST['medicine_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $barcode = trim($_POST['barcode'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $expiry_date_input = trim($_POST['expiry_date'] ?? '');
            $quantity = intval($_POST['quantity'] ?? 0);
            $unit_price = floatval($_POST['unit_price'] ?? 0);
            $min_order_quantity = intval($_POST['min_order_quantity'] ?? 1);
            $preferred = isset($_POST['preferred']) ? 1 : 0;

            if ($medicine_id <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid medicine ID'], 400);
            }
            if ($name === '') {
                jsonResponse(['status' => 'error', 'message' => 'Medicine name is required'], 400);
            }
            if ($quantity < 0) {
                jsonResponse(['status' => 'error', 'message' => 'Quantity cannot be negative'], 400);
            }
            if ($unit_price <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Unit price must be greater than 0'], 400);
            }

            $barcode = $barcode !== '' ? $barcode : null;
            $expiry_date = $expiry_date_input !== '' ? $expiry_date_input : null;

            $conn->begin_transaction();
            try {
                $ownerStmt = $conn->prepare("
                    SELECT 1
                    FROM (
                        SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ?
                        UNION
                        SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ?
                    ) owned
                    WHERE owned.medicine_id = ?
                ");
                if (!$ownerStmt) {
                    throw new Exception('Ownership check prepare failed: ' . $conn->error);
                }
                $ownerStmt->bind_param('iii', $supplier_id, $supplier_id, $medicine_id);
                $ownerStmt->execute();
                if ($ownerStmt->get_result()->num_rows === 0) {
                    throw new Exception('Medicine is not in your inventory');
                }
                $ownerStmt->close();

                if ($barcode !== null) {
                    $barcodeCheck = $conn->prepare("SELECT id FROM medicines WHERE barcode = ? AND id <> ?");
                    if (!$barcodeCheck) {
                        throw new Exception('Barcode check prepare failed: ' . $conn->error);
                    }
                    $barcodeCheck->bind_param('si', $barcode, $medicine_id);
                    $barcodeCheck->execute();
                    if ($barcodeCheck->get_result()->num_rows > 0) {
                        throw new Exception('Barcode already exists');
                    }
                    $barcodeCheck->close();
                }

                $medStmt = $conn->prepare("UPDATE medicines SET name = ?, barcode = ? WHERE id = ?");
                if (!$medStmt) {
                    throw new Exception('Medicine update prepare failed: ' . $conn->error);
                }
                $medStmt->bind_param('ssi', $name, $barcode, $medicine_id);
                if (!$medStmt->execute()) {
                    throw new Exception('Failed to update medicine: ' . $medStmt->error);
                }
                $medStmt->close();

                $optionalUpdates = [
                    'type' => [$type, 's'],
                    'description' => [$description, 's'],
                    'expiry_date' => [$expiry_date, 's'],
                ];
                foreach ($optionalUpdates as $column => [$value, $valueType]) {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM medicines LIKE '" . $conn->real_escape_string($column) . "'");
                    if (!$columnCheck || $columnCheck->num_rows === 0) {
                        continue;
                    }

                    $optionalStmt = $conn->prepare("UPDATE medicines SET `{$column}` = ? WHERE id = ?");
                    if (!$optionalStmt) {
                        throw new Exception("{$column} update prepare failed: " . $conn->error);
                    }
                    $optionalStmt->bind_param($valueType . 'i', $value, $medicine_id);
                    if (!$optionalStmt->execute()) {
                        throw new Exception("Failed to update {$column}: " . $optionalStmt->error);
                    }
                    $optionalStmt->close();
                }

                $smStmt = $conn->prepare("
                    INSERT INTO supplier_medicines
                        (supplier_id, medicine_id, unit_price, min_order_quantity, preferred, quantity_supplied)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        unit_price = VALUES(unit_price),
                        min_order_quantity = VALUES(min_order_quantity),
                        preferred = VALUES(preferred),
                        quantity_supplied = VALUES(quantity_supplied)
                ");
                if (!$smStmt) {
                    throw new Exception('Supplier medicine update prepare failed: ' . $conn->error);
                }
                $smStmt->bind_param('iidiii', $supplier_id, $medicine_id, $unit_price, $min_order_quantity, $preferred, $quantity);
                if (!$smStmt->execute()) {
                    throw new Exception('Failed to update supplier medicine: ' . $smStmt->error);
                }
                $smStmt->close();

                $invStmt = $conn->prepare("
                    INSERT INTO supplier_inventory (supplier_id, medicine_id, quantity, unit_price)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), unit_price = VALUES(unit_price)
                ");
                if (!$invStmt) {
                    throw new Exception('Inventory update prepare failed: ' . $conn->error);
                }
                $invStmt->bind_param('iiid', $supplier_id, $medicine_id, $quantity, $unit_price);
                if (!$invStmt->execute()) {
                    throw new Exception('Failed to update inventory: ' . $invStmt->error);
                }
                $invStmt->close();

                syncMedicineSuppliers($conn, $medicine_id);
                $conn->commit();

                logUserActivity($_SESSION['user_id'], 'update_medicine', [
                    'medicine_id' => $medicine_id,
                    'supplier_id' => $supplier_id,
                    'quantity' => $quantity
                ]);

                jsonResponse(['status' => 'success', 'message' => 'Medicine updated successfully']);
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Update medicine error: " . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        // REMOVE MEDICINE FROM THIS SUPPLIER
        elseif ($action === 'delete_medicine' && $is_supplier) {
            $medicine_id = intval($_POST['medicine_id'] ?? 0);
            if ($medicine_id <= 0) {
                jsonResponse(['status' => 'error', 'message' => 'Invalid medicine ID'], 400);
            }

            $conn->begin_transaction();
            try {
                $ownerStmt = $conn->prepare("
                    SELECT 1
                    FROM (
                        SELECT medicine_id FROM supplier_medicines WHERE supplier_id = ?
                        UNION
                        SELECT medicine_id FROM supplier_inventory WHERE supplier_id = ?
                    ) owned
                    WHERE owned.medicine_id = ?
                ");
                if (!$ownerStmt) {
                    throw new Exception('Ownership check prepare failed: ' . $conn->error);
                }
                $ownerStmt->bind_param('iii', $supplier_id, $supplier_id, $medicine_id);
                $ownerStmt->execute();
                if ($ownerStmt->get_result()->num_rows === 0) {
                    throw new Exception('Medicine is not in your inventory');
                }
                $ownerStmt->close();

                $stmt = $conn->prepare("DELETE FROM supplier_inventory WHERE supplier_id = ? AND medicine_id = ?");
                if (!$stmt) {
                    throw new Exception('Inventory delete prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('ii', $supplier_id, $medicine_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("DELETE FROM supplier_medicines WHERE supplier_id = ? AND medicine_id = ?");
                if (!$stmt) {
                    throw new Exception('Supplier medicine delete prepare failed: ' . $conn->error);
                }
                $stmt->bind_param('ii', $supplier_id, $medicine_id);
                $stmt->execute();
                $stmt->close();

                syncMedicineSuppliers($conn, $medicine_id);
                $conn->commit();

                logUserActivity($_SESSION['user_id'], 'delete_medicine', [
                    'medicine_id' => $medicine_id,
                    'supplier_id' => $supplier_id
                ]);

                jsonResponse(['status' => 'success', 'message' => 'Medicine removed from your inventory']);
            } catch (Exception $e) {
                $conn->rollback();
                error_log("Delete medicine error: " . $e->getMessage());
                jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        else {
            jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
        }

    } else {
        jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn) {
        $conn->rollback();
    }
    error_log("Critical error in sup_medicines.php: " . $e->getMessage());
    $payload = ['status' => 'error', 'message' => 'Server error occurred'];
    if (!empty($debug)) {
        $payload['debug'] = $e->getMessage();
        $payload['trace'] = $e->getTraceAsString();
    }
    jsonResponse($payload, 500);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
