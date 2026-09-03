<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.php');
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'med_inventory';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception('Connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die('Database connection error: ' . $e->getMessage());
}

require_once __DIR__ . '/config/settings_helper.php';

function ensureSupplierPreferenceColumn(mysqli $conn): void {
    $check = $conn->query("SHOW COLUMNS FROM suppliers LIKE 'is_preferred'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE suppliers ADD COLUMN is_preferred TINYINT(1) NOT NULL DEFAULT 0 AFTER user_id");
    }
    if ($check) $check->close();
}

ensureSupplierPreferenceColumn($conn);

// Fetch suppliers and compute bought quantity from actual order history.
// The legacy `bought_quantity` column is not maintained anywhere in the app,
// so the UI must derive it from the orders/order_items tables.
$sql = "SELECT s.*,
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
        FROM suppliers s ORDER BY name ASC";
$result = $conn->query($sql);

$suppliers = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
} else {
    $error = 'Query failed: ' . $conn->error;
}

// Display messages
$success = $_SESSION['success'] ?? '';
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['success'], $_SESSION['errors']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f5f7fa; 
            overflow: hidden;
            height: 100vh;
            margin: 0;
            padding: 0;
        }
        .main-content { 
            margin-left: 250px; 
            padding: 1rem; 
            transition: margin-left 0.3s ease;
            height: 100vh;
            overflow: hidden;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
        }
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; padding: 1rem; } 
        }
        .card { 
            border-radius: 15px; 
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); 
            background: #fff; 
            border: none; 
        }
        .table-container {
            flex: 1;
            max-height: none;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 12px;
        }
        .table-container::-webkit-scrollbar { width: 10px; height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        
        .table { 
            border-radius: 12px; 
            overflow: hidden; 
            margin-bottom: 0;
            min-width: 1050px;
        }
        .table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table th { 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: #fff; 
            font-weight: 600; 
            padding: 1rem; 
            border: none;
            white-space: nowrap;
        }
        .table td { 
            vertical-align: middle; 
            color: #2d3748; 
            padding: 1rem; 
            border-color: #e2e8f0;
            white-space: nowrap;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); }
        
        .action-btn { 
            padding: 0.4rem 0.8rem; 
            font-size: 0.85rem; 
            border-radius: 8px; 
            transition: all 0.3s ease; 
            margin: 0 0.2rem; 
            font-weight: 500;
            white-space: nowrap;
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
        
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); border: none; color: white; }
        .btn-info:hover { background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); }
        .btn-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #78350f; }
        .btn-warning:hover { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; }
        .btn-danger:hover { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .btn-secondary { background: linear-gradient(135deg, #64748b 0%, #475569 100%); border: none; }
        .btn-secondary:hover { background: linear-gradient(135deg, #475569 0%, #64748b 100%); }
        .preferred-toggle {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #64748b;
        }
        .preferred-toggle.active {
            border-color: #16a34a;
            background: #dcfce7;
            color: #15803d;
        }
        
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
        .modal-dialog { max-height: 90vh; display: flex; align-items: center; }
        .modal-body { max-height: calc(90vh - 180px); overflow-y: auto; }
        .modal-body::-webkit-scrollbar { width: 10px; }
        .modal-body::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .modal-body::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .modal-body::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        
        .modal-header { border-bottom: none; padding: 1.5rem 2rem; border-radius: 20px 20px 0 0; }
        .modal-header.bg-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); }
        .modal-header.bg-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
        .modal-header.bg-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        
        .form-control, .form-select { 
            border-radius: 10px; 
            border: 2px solid #e2e8f0; 
            transition: all 0.3s ease; 
            padding: 0.65rem 1rem; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: #1b5e3f; 
            box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.15); 
            transform: translateY(-1px); 
        }
        .form-label { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }
        .invalid-feedback { font-size: 0.85rem; color: #dc2626; font-weight: 500; }
        
        select option {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }
        .dark-mode select option,
        .dark-mode .form-select option {
            color: var(--text-primary) !important;
            background-color: var(--input-bg) !important;
        }
        
        #toggle-sidebar-mobile { 
            position: fixed; 
            top: 1rem; 
            left: 1rem; 
            z-index: 1100; 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: #fff; 
            border-radius: 50%; 
            width: 45px; 
            height: 45px; 
            border: none; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); 
        }
        
        h2 { color: #1e293b; font-weight: 700; }
        .card-body { padding: 1rem; }
        .page-header { 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: white; 
            padding: 1.25rem 1.5rem; 
            border-radius: 15px; 
            margin-bottom: 1.25rem; 
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); 
        }
        .page-header h2 { color: white; margin: 0; font-size: 1.5rem; }
        
        .alert { 
            border-radius: 12px; 
            padding: 0.75rem 1rem; 
            margin-bottom: 1rem; 
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .alert-success { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; }
        .alert-danger { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #7f1d1d; }
        
        .filter-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            border-radius: 15px;
            border-left: 5px solid #1b5e3f;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        
        .filter-card .card-body {
            padding: 1.5rem;
        }
        #supplier-table.show-all {
            min-width: 1800px;
        }
        #supplier-table.show-all .optional-col { display: table-cell !important; }
        #supplier-table:not(.show-all) th.optional-col,
#supplier-table:not(.show-all) td.optional-col { display: none !important; }
#supplier-table.show-all th.optional-col,
#supplier-table.show-all td.optional-col { display: table-cell !important; }
        
        .filter-card h5 {
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .form-control-sm, .form-select-sm {
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .text-muted { color: #64748b !important; }
        
        select option {
            color: #000000 !important;
            background-color: #ffffff !important;
        }

        #no-medicines-message i {
            opacity: 0.3;
            font-size: 3.5rem;
        }
        #no-medicines-message p {
            font-size: 1.1rem;
            color: #6c757d;
        }

        /* ===== DARK MODE ===== */
        body.dark-mode {
            background: #0f1419 !important;
            color: #f8fafc !important;
        }
        body.dark-mode .main-content {
            background: transparent !important;
        }
        body.dark-mode .card {
            background: #111827 !important;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35) !important;
        }
        body.dark-mode .table-container {
            background: #0f172a !important;
            border-color: #1f2937 !important;
        }
        body.dark-mode .table td {
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .table tbody tr:hover {
            background: #111827 !important;
        }
        body.dark-mode .action-btn,
        body.dark-mode .btn-primary,
        body.dark-mode .btn-info,
        body.dark-mode .btn-warning,
        body.dark-mode .btn-danger,
        body.dark-mode .btn-secondary {
            color: #ffffff !important;
        }
        body.dark-mode .modal-content {
            background: #111827 !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .modal-body {
            background: #0f172a !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            border-color: #2ecc71 !important;
            box-shadow: 0 0 0 0.2rem rgba(46,204,113,0.15) !important;
        }
        body.dark-mode select option {
            color: #e2e8f0 !important;
            background-color: #0f172a !important;
        }
        body.dark-mode .page-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        }
        body.dark-mode .page-header h2 {
            color: white;
        }
        body.dark-mode .page-header .btn-light {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: rgba(255, 255, 255, 0.25) !important;
            color: white !important;
        }
        body.dark-mode .page-header .btn-light:hover {
            background: rgba(255, 255, 255, 0.25) !important;
            color: white !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>
    
    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-truck me-2"></i> Supplier Management</h2>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Form -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                    <h5 class="mb-0"><i class="bi bi-funnel me-2"></i>Filter Suppliers</h5>
                    <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#addSupplierModal" type="button">
                        <i class="bi bi-plus-circle me-1"></i> Add Supplier
                    </button>
                </div>
                <form id="filter-supplier-form" novalidate class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label for="filter_search" class="form-label fw-bold">Search Suppliers</label>
                        <input type="text" class="form-control form-control-sm" id="filter_search" name="filter_search" placeholder="Search by name, company, contact, representative, or items...">
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                            <button type="button" id="apply-supplier-filter" class="btn btn-primary action-btn"><i class="bi bi-funnel"></i> Filter</button>
                            <button type="button" id="toggle-supplier-columns" class="btn btn-outline-primary action-btn"><i class="bi bi-layout-three-columns"></i> Show All Columns</button>
                            <button type="button" id="reset-filter" class="btn btn-secondary action-btn"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="card flex-grow-1 overflow-hidden admin-table-card">
            <div class="card-body p-0">
                <div class="table-container admin-table-scroll">
                        <table class="table table-hover" id="supplier-table" data-admin-no-pagination="true">
                            <thead>
                                <tr>
                                <th>Preferred</th>
                                <th>Name</th>
                                <th>Company</th>
                                <th class="optional-col">Address</th>
                                <th>Contact</th>
                                <th class="optional-col">Total Buy</th>
                                <th class="optional-col">Total Paid</th>
                                <th class="optional-col">Total Due</th>
                                <th>Representative</th>
                                <th class="optional-col">Lead Time (days)</th>
                                <th class="optional-col">Created At</th>
                                <th class="optional-col">Items Bought</th>
                                <th>Bought Quantity</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody id="supplier-table-body">
                                <?php if (empty($suppliers)): ?>
            <tr id="no-results-row"><td colspan="14" class="text-center py-4 text-muted">No suppliers found.</td></tr>
        <?php else: ?>
            <?php foreach ($suppliers as $s): ?>
                <tr>
                    <td>
                        <button type="button"
                            class="preferred-toggle <?php echo !empty($s['is_preferred']) ? 'active' : ''; ?>"
                            data-id="<?php echo $s['id']; ?>"
                            data-name="<?php echo htmlspecialchars($s['name']); ?>"
                            data-preferred="<?php echo !empty($s['is_preferred']) ? '1' : '0'; ?>"
                            title="<?php echo !empty($s['is_preferred']) ? 'Preferred supplier' : 'Mark as preferred supplier'; ?>">
                            <i class="bi <?php echo !empty($s['is_preferred']) ? 'bi-check-circle-fill' : 'bi-circle'; ?>"></i>
                        </button>
                    </td>
                    <td><?php echo htmlspecialchars($s['name']); ?></td>
                    <td><?php echo htmlspecialchars($s['company']); ?></td>
                    <td class="optional-col"><?php echo htmlspecialchars($s['address']); ?></td>
                    <td><?php echo htmlspecialchars($s['contact']); ?></td>
                    <td class="optional-col">PHP <?php echo number_format($s['total_buy'], 2); ?></td>
                    <td class="optional-col">PHP <?php echo number_format($s['total_paid'], 2); ?></td>
                    <td class="optional-col">PHP <?php echo number_format($s['total_due'], 2); ?></td>
                    <td><?php echo htmlspecialchars($s['representative'] ?: '—'); ?></td>
                    <td class="optional-col"><?php echo $s['lead_time']; ?></td>
                    <td class="optional-col"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
                    <td class="optional-col"><?php echo htmlspecialchars($s['bought_medicines'] ?: '—'); ?></td>
                    <td><strong><?php echo $s['bought_quantity']; ?></strong></td>
                    <td>
                        <button class="btn btn-info btn-sm action-btn view-medicines-btn" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>">
                            <i class="bi bi-box-seam"></i> Items
                        </button>
                        <button class="btn btn-warning btn-sm action-btn edit-btn" data-id="<?php echo $s['id']; ?>" data-name="<?php echo htmlspecialchars($s['name']); ?>" data-company="<?php echo htmlspecialchars($s['company']); ?>" data-address="<?php echo htmlspecialchars($s['address']); ?>" data-contact="<?php echo htmlspecialchars($s['contact']); ?>" data-total-buy="<?php echo $s['total_buy']; ?>" data-total-paid="<?php echo $s['total_paid']; ?>" data-total-due="<?php echo $s['total_due']; ?>" data-representative="<?php echo htmlspecialchars($s['representative']); ?>" data-lead-time="<?php echo $s['lead_time']; ?>">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm action-btn delete-btn" data-id="<?php echo $s['id']; ?>">
                            <i class="bi bi-trash"></i> Delete
                        </button>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
                            </tbody>
                        </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="supplier-page-info" class="text-muted"></small>
                    <nav class="pagination-container" id="supplier-pagination" aria-label="Supplier pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Supplier Modal -->
    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add-supplier-form" novalidate>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required maxlength="100" pattern="[A-Za-z\s]+">
                                <div class="invalid-feedback">Name is required and must contain only letters and spaces.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Company <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="company" required maxlength="150">
                                <div class="invalid-feedback">Company is required.</div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="address" rows="2" required maxlength="500"></textarea>
                                <div class="invalid-feedback">Address is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="contact" name="contact" required>
                                <div class="invalid-feedback">Valid email or phone required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Representative</label>
                                <input type="text" class="form-control" name="representative" maxlength="100" pattern="[A-Za-z\s]*">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Supplier</button>
                    </div>
                    <input type="hidden" name="action" value="add">
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit-supplier-form" novalidate>
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required maxlength="100" pattern="[A-Za-z\s]+">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Company <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_company" name="company" required maxlength="150">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_address" name="address" rows="2" required maxlength="500"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_contact" name="contact" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Representative</label>
                                <input type="text" class="form-control" id="edit_representative" name="representative" maxlength="100" pattern="[A-Za-z\s]*">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Buy</label>
                                <input type="number" class="form-control" id="edit_total_buy" name="total_buy" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Paid</label>
                                <input type="number" class="form-control" id="edit_total_paid" name="total_paid" step="0.01" min="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Total Due</label>
                                <input type="number" class="form-control" id="edit_total_due" name="total_due" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lead Time (days)</label>
                                <input type="number" class="form-control" id="edit_lead_time" name="lead_time" min="0" max="365">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark">Update Supplier</button>
                    </div>
                    <input type="hidden" name="action" value="edit">
                </form>
            </div>
        </div>
    </div>

    <!-- View Supplier Items Modal -->
    <div class="modal fade" id="viewMedicinesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewMedicinesModalLabel"><i class="bi bi-box-seam me-2"></i>Items</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="medicines-loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading items...</p>
                    </div>
                    <div id="medicines-error" class="alert alert-danger d-none">
                        <span></span>
                    </div>
                    <div id="no-medicines-message" class="text-center py-5 d-none">
                        <i class="bi bi-box-seam text-muted"></i>
                        <p>No items from this supplier.</p>
                    </div>
                    <div id="medicines-table-container" style="display: none;">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Unit Price</th>
                                    <th>Min Order</th>
                                    <th>Preferred</th>
                                    <th>Qty Supplied</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="medicines-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Supplier Item Modal -->
    <div class="modal fade" id="editSupplierMedicineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Item Supply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit-supplier-medicine-form" novalidate>
                    <div class="modal-body">
                        <input type="hidden" id="edit_sm_supplier_id" name="supplier_id">
                        <input type="hidden" id="edit_sm_medicine_id" name="medicine_id">
                        <p><strong>Item:</strong> <span id="edit_sm_medicine_name"></span></p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Unit Price</label>
                                <input type="number" class="form-control" id="edit_sm_unit_price" name="unit_price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Min Order Qty</label>
                                <input type="number" class="form-control" id="edit_sm_min_order_quantity" name="min_order_quantity" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Quantity Supplied</label>
                                <input type="number" class="form-control" id="edit_sm_quantity_supplied" name="quantity_supplied" min="0" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="edit_sm_preferred" name="preferred" value="1">
                                    <label class="form-check-label" for="edit_sm_preferred">Preferred Supplier</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-dark">Update</button>
                    </div>
                    <input type="hidden" name="action" value="edit_supplier_medicine">
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-labelledby="deleteSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSupplierModalLabel"><i class="bi bi-trash me-2"></i>Delete Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">This will delete the supplier and the associated user account.</p>
                    <div class="alert alert-warning mb-2">
                        <i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.
                    </div>
                    <div class="alert alert-danger d-none mb-0" id="delete-supplier-blocked-alert">
                        <i class="bi bi-x-octagon me-2"></i><span id="delete-supplier-blocked-message"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-delete-supplier-btn">
                        <i class="bi bi-check-circle me-1"></i>Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="preferredSupplierModal" tabindex="-1" aria-labelledby="preferredSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="preferredSupplierModalLabel"><i class="bi bi-check-circle me-2"></i>Preferred Supplier</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="preferred-supplier-message"></p>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>Preferred suppliers are prioritized first for automatic low-stock orders when they have the item in stock.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-preferred-supplier-btn">
                        <i class="bi bi-check2-circle me-1"></i>Yes, Continue
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteSupplierMedicineModal" tabindex="-1" aria-labelledby="deleteSupplierMedicineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSupplierMedicineModalLabel"><i class="bi bi-trash me-2"></i>Remove Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Remove "<strong id="delete-sm-medicine-name"></strong>" from this supplier?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-supplier-medicine-btn">
                        <i class="bi bi-trash me-1"></i>Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/suppliers.js?v=<?= filemtime(__DIR__ . '/assets/js/suppliers.js') ?>"></script>
</body>
</html>
