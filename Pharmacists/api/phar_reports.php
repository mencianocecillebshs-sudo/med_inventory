<?php
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$medicine_id = isset($_GET['medicine_id']) ? (int)$_GET['medicine_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'inventory';

try {
    if ($type === 'inventory') {
        // Inventory Report - Original logic unchanged
        $where = "1=1";
        if ($medicine_id > 0) {
            $where .= " AND id = $medicine_id";
        }
        
        // Get all inventory records without pagination
        $query = "SELECT 
                    id, 
                    name as 'Item Name',
                    name as 'Medicine Name',
                    barcode, 
                    quantity as 'Remaining Stock', 
                    COALESCE(NULLIF(item_type, ''), NULLIF(category, ''), 'N/A') as 'Type', 
                    description, 
                    created_at as 'Date Acquired', 
                    expiry_date as 'Expiry Date' 
                  FROM medicines 
                  WHERE $where 
                  ORDER BY name ASC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reports, 
            'total' => count($reports),
            'type' => 'inventory'
        ]);
        
    } elseif ($type === 'transactions') {
        // Transactions Report - Improved aggregate logic to include deleted medicines and ensure exact match to transaction quantities
        $year = date('Y');
        $year_start = $year . '-01-01';
        $year_end = $year . '-12-31';
        $fifteen_days_ago = date('Y-m-d', strtotime('-15 days'));
        $date_span_start = date('m/d/Y', strtotime('-15 days'));
        $date_span_end = date('m/d/Y');

        $fifteen_sum = "SUM(CASE WHEN t.timestamp >= '$fifteen_days_ago' THEN t.quantity ELSE 0 END)";
        $yearly_sum = "SUM(CASE WHEN DATE(t.timestamp) BETWEEN '$year_start' AND '$year_end' THEN t.quantity ELSE 0 END)";
        $having = "HAVING COALESCE($fifteen_sum, 0) > 0 OR COALESCE($yearly_sum, 0) > 0";

        if ($medicine_id > 0) {
            // For specific medicine - handle if deleted
            $query = "SELECT 
                        COALESCE(m.name, 'Deleted Item') as `Item Name`,
                        COALESCE(m.name, 'Deleted Medicine') as `Medicine Name`,
                        COALESCE($fifteen_sum, 0) as `Total Dispensed (15 Days)`, 
                        '$date_span_start' as `Date Span Start`, 
                        '$date_span_end' as `Date Span End`, 
                        COALESCE($yearly_sum, 0) as `Yearly Total`
                      FROM transactions t 
                      LEFT JOIN medicines m ON t.medicine_id = m.id 
                      WHERE t.action = 'remove' AND t.medicine_id = $medicine_id
                      GROUP BY t.medicine_id
                      $having";
        } else {
            // For all medicines - separate queries for existing and deleted to avoid undefined and group deleted as one row
            // Existing medicines query
            $existing_query = "SELECT 
                                m.name as `Item Name`,
                                m.name as `Medicine Name`,
                                COALESCE($fifteen_sum, 0) as `Total Dispensed (15 Days)`, 
                                '$date_span_start' as `Date Span Start`, 
                                '$date_span_end' as `Date Span End`, 
                                COALESCE($yearly_sum, 0) as `Yearly Total`
                              FROM medicines m 
                              INNER JOIN transactions t ON t.medicine_id = m.id AND t.action = 'remove'
                              GROUP BY m.id, m.name
                              $having
                              ORDER BY m.name ASC";
            
            $result1 = $conn->query($existing_query);
            $reports = [];
            if ($result1) {
                while ($row = $result1->fetch_assoc()) {
                    $reports[] = $row;
                }
            }
            
            // Deleted items query - grouped as one row to avoid multiple undefined entries
            $deleted_query = "SELECT 
                                'Deleted Item' as `Item Name`,
                                'Deleted Medicine' as `Medicine Name`,
                                COALESCE($fifteen_sum, 0) as `Total Dispensed (15 Days)`, 
                                '$date_span_start' as `Date Span Start`, 
                                '$date_span_end' as `Date Span End`, 
                                COALESCE($yearly_sum, 0) as `Yearly Total`
                              FROM transactions t 
                              LEFT JOIN medicines m ON t.medicine_id = m.id 
                              WHERE t.action = 'remove' AND m.id IS NULL
                              $having";
            
            $result2 = $conn->query($deleted_query);
            if ($result2 && $row = $result2->fetch_assoc()) {
                if ($row['Total Dispensed (15 Days)'] > 0 || $row['Yearly Total'] > 0) {
                    $reports[] = $row;
                }
            }
            
            // Sort the combined reports by Item Name (existing first, then Deleted)
            usort($reports, function($a, $b) {
                return strcmp($a['Item Name'], $b['Item Name']);
            });
            
            echo json_encode([
                'success' => true,
                'data' => $reports, 
                'total' => count($reports),
                'type' => 'transactions'
            ]);
            return; // Early return for all-medicines case
        }
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reports, 
            'total' => count($reports),
            'type' => 'transactions'
        ]);
        
    } elseif ($type === 'sales') {
        $where = "1=1";
        if ($medicine_id > 0) {
            $where = "EXISTS (SELECT 1 FROM sales s WHERE s.medicine_id = $medicine_id AND s.purchase_id = si.purchase_id)";
        }
        
        $query = "SELECT si.*, u.username as cashier_name, p.purchase_number,
                    (SELECT COUNT(*) FROM sales s WHERE s.purchase_id = si.purchase_id) as item_count
                  FROM sales_invoices si 
                  LEFT JOIN users u ON si.cashier_id = u.id 
                  LEFT JOIN purchases p ON si.purchase_id = p.id
                  WHERE $where
                  ORDER BY si.created_at DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Database query failed: ' . $conn->error);
        }
        
        $reports = [];
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $reports, 
            'total' => count($reports),
            'type' => 'sales'
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type. Use "inventory", "transactions" or "sales".']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to generate report',
        'message' => $e->getMessage()
    ]);
}
?>
