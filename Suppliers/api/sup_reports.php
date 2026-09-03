<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

require_once __DIR__ . '/../includes/supplier_auth.php';
$supplierProfile = requireSupplierSession($conn);
$supplier_id = (int)$supplierProfile['id'];

require_once __DIR__ . '/pagination_helper.php';

$medicine_id = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'inventory';
$paginationParams = supGetPaginationParams($conn);
$page = $paginationParams['page'];
$limit = $paginationParams['limit'];
$offset = $paginationParams['offset'];
if (isset($_GET['all']) && $_GET['all'] === '1') {
    $page = 1;
    $limit = 1000000;
    $offset = 0;
}

try {
    if ($type === 'inventory') {
        $searchFilter = '';
        $countParams = [$supplier_id];
        $countTypes = 'i';

        if ($medicine_id > 0) {
            $searchFilter .= ' AND si.medicine_id = ?';
            $countParams[] = $medicine_id;
            $countTypes .= 'i';
        } elseif ($search !== '') {
            $searchFilter .= ' AND m.name LIKE ?';
            $countParams[] = '%' . $search . '%';
            $countTypes .= 's';
        }

        $count_query = "SELECT COUNT(*) as total FROM supplier_inventory si
                        INNER JOIN medicines m ON si.medicine_id = m.id
                        WHERE si.quantity > 0 AND si.supplier_id = ?$searchFilter";
        $count_stmt = $conn->prepare($count_query);
        if (!$count_stmt) throw new Exception('Count query failed: ' . $conn->error);
        $count_stmt->bind_param($countTypes, ...$countParams);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = (int)$count_result->fetch_assoc()['total'];
        $count_stmt->close();

        $queryFilter = '';
        $queryParams = [$supplier_id];
        $queryTypes = 'i';

        if ($medicine_id > 0) {
            $queryFilter .= ' AND si.medicine_id = ?';
            $queryParams[] = $medicine_id;
            $queryTypes .= 'i';
        } elseif ($search !== '') {
            $queryFilter .= ' AND m.name LIKE ?';
            $queryParams[] = '%' . $search . '%';
            $queryTypes .= 's';
        }

        $query = "SELECT 
                    m.id, 
                    m.name as 'Medicine Name', 
                    m.barcode, 
                    si.quantity as 'Remaining Stock', 
                    COALESCE(NULLIF(m.item_type, ''), NULLIF(m.category, ''), 'N/A') as type, 
                    m.description, 
                    m.created_at as 'Date Acquired', 
                    m.expiry_date as 'Expiry Date' 
                  FROM supplier_inventory si 
                  INNER JOIN medicines m ON si.medicine_id = m.id 
                  WHERE si.quantity > 0 AND si.supplier_id = ?$queryFilter
                  ORDER BY m.name ASC 
                  LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Database query failed: ' . $conn->error);
        $queryParams[] = $limit;
        $queryParams[] = $offset;
        $queryTypes .= 'ii';
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => $reports,
            'pagination' => supBuildPaginationMeta($page, $limit, $total),
            'type' => 'inventory'
        ]);
        
    } elseif ($type === 'transactions') {
        $searchFilter = '';
        $countParams = [$supplier_id];
        $countTypes = 'i';

        if ($medicine_id > 0) {
            $searchFilter .= ' AND ss.medicine_id = ?';
            $countParams[] = $medicine_id;
            $countTypes .= 'i';
        } elseif ($search !== '') {
            $searchFilter .= ' AND m.name LIKE ?';
            $countParams[] = '%' . $search . '%';
            $countTypes .= 's';
        }

        $count_query = "SELECT COUNT(ss.id) as total 
                        FROM supplier_sales ss
                        INNER JOIN sales_invoices si ON ss.invoice_id = si.id
                        LEFT JOIN medicines m ON ss.medicine_id = m.id 
                        LEFT JOIN users u ON si.cashier_id = u.id
                        WHERE ss.supplier_id = ?$searchFilter";
        $count_stmt = $conn->prepare($count_query);
        if (!$count_stmt) throw new Exception('Count query failed: ' . $conn->error);
        $count_stmt->bind_param($countTypes, ...$countParams);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = (int)$count_result->fetch_assoc()['total'];
        $count_stmt->close();

        $queryFilter = '';
        $queryParams = [$supplier_id];
        $queryTypes = 'i';

        if ($medicine_id > 0) {
            $queryFilter .= ' AND ss.medicine_id = ?';
            $queryParams[] = $medicine_id;
            $queryTypes .= 'i';
        } elseif ($search !== '') {
            $queryFilter .= ' AND m.name LIKE ?';
            $queryParams[] = '%' . $search . '%';
            $queryTypes .= 's';
        }

        $query = "SELECT 
                    ss.id,
                    ss.medicine_id,
                    COALESCE(m.name, 'Deleted Medicine') AS medicine_name,
                    'sale' as action,
                    ss.quantity,
                    ss.selling_price,
                    ss.line_total as total_cost,
                    si.invoice_number,
                    ss.order_id,
                    COALESCE(u.username, 'Unknown') as cashier_name,
                    si.created_at as timestamp
                  FROM supplier_sales ss
                  INNER JOIN sales_invoices si ON ss.invoice_id = si.id
                  LEFT JOIN medicines m ON ss.medicine_id = m.id 
                  LEFT JOIN users u ON si.cashier_id = u.id
                  WHERE ss.supplier_id = ?$queryFilter
                  ORDER BY si.created_at DESC, ss.id DESC
                  LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($query);
        if (!$stmt) throw new Exception('Database query failed: ' . $conn->error);
        $queryParams[] = $limit;
        $queryParams[] = $offset;
        $queryTypes .= 'ii';
        $stmt->bind_param($queryTypes, ...$queryParams);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'data' => $reports,
            'pagination' => supBuildPaginationMeta($page, $limit, $total),
            'type' => 'transactions'
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type. Use "inventory" or "transactions".']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate report',
        'message' => $e->getMessage()
    ]);
}
?>
