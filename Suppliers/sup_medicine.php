<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/includes/supplier_auth.php';

if (!$conn) {
    die('Database connection failed. Please try again later.');
}

// Allow local debug override: ?debug=1&supplier_id=NN will load the page for that supplier (dev only)
$is_supplier = false;
$supplier_profile = null;
if (isset($_GET['debug']) && $_GET['debug'] === '1' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) && isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id'])) {
    $sid = (int)$_GET['supplier_id'];
    $stmt = $conn->prepare('SELECT id, name FROM suppliers WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $sid);
        $stmt->execute();
        $supplier_profile = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    if ($supplier_profile) {
        $supplier_profile['id'] = (int)$supplier_profile['id'];
        $is_supplier = true;
    }
}

if (!$is_supplier) {
    $supplier_profile = requireSupplierPage($conn);
    $is_supplier = true;
}

$supplier_id = (int)$supplier_profile['id'];
$supplier_name = $supplier_profile['name'] ?? 'Supplier';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Supplier Medicines - Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }

        .page-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2);
        }
        .page-header h2 { color: white; margin: 0; }
        .supplier-subtitle { font-size: 1.1rem; opacity: 0.9; }

        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); background: #fff; border: none; }
        .card:hover { transform: none; }
        .card-body { padding: 0; }

        .table-container { max-height: 600px; overflow-y: auto; overflow-x: auto; border-radius: 15px; background: var(--table-bg, #fff); border: 1px solid var(--table-border, #e2e8f0); }
        .table-container::-webkit-scrollbar { width: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }

        .table { border-radius: 12px; overflow: hidden; margin-bottom: 0; table-layout: auto; width: 100%; background-color: var(--table-bg, #fff); color: var(--text-primary); }
        .table th, .table td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; }
        .table th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #fff; font-weight: 600; padding: 1rem; border: none; position: sticky; top: 0; z-index: 10;
        }
        .table td { vertical-align: middle; color: #2d3748; padding: 1rem; border-color: #e2e8f0; }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); }

        .action-btn, .table .btn-sm {
            padding: 0.45rem 0.75rem !important; font-size: 0.9rem; border-radius: 8px !important;
            transition: all 0.3s ease; margin: 0.125rem; font-weight: 500 !important;
            display: inline-flex !important; align-items: center !important; justify-content: center !important;
            gap: 0.25rem !important; line-height: 1.2 !important; white-space: nowrap !important;
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(27, 94, 63, 0.3); }

        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; }
        .btn-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #78350f; }
        .btn-warning:hover { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); color: #78350f; }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; }
        .btn-danger:hover { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }

        .stock-badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .stock-in { background: #d1fae5; color: #065f46; }
        .stock-out { background: #fee2e2; color: #991b1b; }

        .info-badge { background: #dbeafe; color: #1b5e3f; padding: 0.25rem 0.5rem; border-radius: 5px; font-size: 0.8rem; }
        .supplier-add-medicine-btn { min-height: 46px; width: 100%; }

        .form-control, .form-select {
            border-radius: 10px; border: 2px solid #e2e8f0;
            transition: all 0.3s ease; padding: 0.65rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1b5e3f; box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.15); transform: translateY(-1px);
        }
        .form-label { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }

        .modal-content {
            border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            background: var(--modal-bg, #fff); color: var(--text-primary);
        }
        #editMedicineModal .modal-dialog,
        #addNewMedicineModal .modal-dialog { max-width: min(900px, calc(100vw - 2rem)); }
        #editMedicineModal .modal-content,
        #addNewMedicineModal .modal-content { border-radius: 20px; overflow: hidden; }
        #editMedicineModal .modal-header,
        #addNewMedicineModal .modal-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
            color: #fff !important; padding: 1.35rem 1.75rem;
        }
        #editMedicineModal .modal-title,
        #addNewMedicineModal .modal-title { color: #fff; font-size: 1.55rem; font-weight: 800; }
        #editMedicineModal .modal-body,
        #addNewMedicineModal .modal-body { padding: 1.35rem 1.75rem; }
        #editMedicineModal .mb-3,
        #addNewMedicineModal .mb-3 { margin-bottom: 0.85rem !important; }
        #editMedicineModal .form-label,
        #addNewMedicineModal .form-label { font-size: 0.95rem; margin-bottom: 0.35rem; }
        #editMedicineModal .form-control,
        #editMedicineModal .type-display-input,
        #addNewMedicineModal .form-control,
        #addNewMedicineModal .type-display-input {
            min-height: 46px; border-radius: 12px; font-size: 1rem; padding: 0.5rem 0.85rem;
        }
        #editMedicineModal textarea.form-control,
        #addNewMedicineModal textarea.form-control { min-height: 84px; }
        #addNewMedicineModal .modal-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; }
        #addNewMedicineModal .modal-form-grid .full-width { grid-column: 1 / -1; }
        @media (max-width: 768px) {
            #addNewMedicineModal .modal-form-grid { grid-template-columns: 1fr; }
        }
        #editMedicineModal .btn-close,
        #addNewMedicineModal .btn-close { filter: invert(1) grayscale(100%) brightness(200%); opacity: 0.75; }
        #editMedicineModal .btn-close:hover,
        #addNewMedicineModal .btn-close:hover { opacity: 1; }
        .modal-header {
            border-bottom: none; padding: 1.5rem 2rem; border-radius: 20px 20px 0 0;
            background: var(--modal-header-bg, #fff); color: var(--modal-header-text, inherit);
        }
        .modal-header.bg-primary { background: linear-gradient(135deg, var(--primary-color, #1b5e3f) 0%, var(--primary-dark, #0f3f28) 100%) !important; }
        .modal-header.bg-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%) !important; }
        .modal-body { background: var(--modal-bg, #fff); color: var(--text-primary); }
        .modal-footer { background: var(--modal-bg, #fff); border-top: 1px solid var(--modal-border, #e2e8f0); }

        .invalid-feedback { font-size: 0.85rem; color: #dc2626; font-weight: 500; }

        .table td:last-child, .table th:last-child { max-width: none !important; overflow: visible !important; text-overflow: clip !important; }
        .table td:last-child { white-space: normal; }
        .table td:last-child .action-btn { margin: 0.25rem 0.2rem; }

        .type-combobox { position: relative; }
        .type-combobox .type-input-wrapper { position: relative; display: flex; align-items: center; }
        .type-combobox .type-input-wrapper .form-control { padding-right: 2.8rem; }
        .type-combobox .type-chevron {
            position: absolute; right: 0.85rem; top: 50%; transform: translateY(-50%);
            color: #64748b; pointer-events: none; transition: transform 0.2s ease; font-size: 1rem;
        }
        .type-combobox.open .type-chevron { transform: translateY(-50%) rotate(180deg); }
        .type-dropdown {
            display: none; position: absolute; top: calc(100% + 6px); left: 0; right: 0;
            background: #fff; border: 2px solid #1b5e3f; border-radius: 12px;
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.15); z-index: 1060;
            overflow: hidden; max-height: 220px; flex-direction: column;
        }
        .type-combobox.open .type-dropdown { display: flex; }
        .type-dropdown-search { padding: 0.6rem 0.85rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .type-dropdown-search input {
            width: 100%; border: none; outline: none; background: transparent; font-size: 0.875rem; color: #1e293b;
        }
        .type-dropdown-list { overflow-y: auto; flex: 1; }
        .type-dropdown-list::-webkit-scrollbar { width: 6px; }
        .type-dropdown-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        .type-dropdown-item {
            padding: 0.6rem 1rem; cursor: pointer; font-size: 0.9rem; color: #1e293b;
            display: flex; align-items: center; gap: 0.5rem; transition: background 0.15s ease;
        }
        .type-dropdown-item:hover, .type-dropdown-item.active { background: #ecfdf5; color: #1b5e3f; }
        .type-dropdown-empty { padding: 0.75rem 1rem; color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2><i class="bi bi-capsule-pill me-2"></i>My Supplier Medicines</h2>
                    <p class="supplier-subtitle mb-0">
                        <i class="bi bi-shop me-1"></i><?php echo htmlspecialchars($supplier_name); ?> -
                        Add, edit, and remove medicines saved under your supplier account
                    </p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#addNewMedicineModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Medicine
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="p-4 border-bottom bg-light">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-4 col-md-12">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="search" placeholder="Search medicines...">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-4">
                            <select class="form-select" id="stock-filter">
                                <option value="">My Medicines</option>
                                <option value="in_stock">With Stock</option>
                                <option value="out_of_stock">No Stock</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <select class="form-select" id="sort">
                                <option value="name">Sort: Name</option>
                                <option value="quantity">My Stock</option>
                                <option value="type">Type</option>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <button class="btn btn-primary action-btn supplier-add-medicine-btn w-100" data-bs-toggle="modal" data-bs-target="#addNewMedicineModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Medicine
                            </button>
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="toggle-medicine-columns">
                                <i class="bi bi-arrows-angle-expand me-1"></i> Show All Columns
                            </button>
                        </div>
                        <div class="col-lg-1 col-md-2 text-md-end">
                            <span id="medicine-count" class="info-badge">Loading...</span>
                        </div>
                    </div>
                </div>

                <div class="table-container sup-table-wrap" id="medicine-table-wrap">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Medicine Name</th>
                                <th class="col-detail">Type</th>
                                <th class="col-detail">Description</th>
                                <th>My Stock</th>
                                <th>Unit Price</th>
                                <th class="col-detail">Min Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="medicine-table">
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading your medicines...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="sup-table-footer">
                    <span class="sup-pagination-info" id="pagination-info">Page 1 of 1 · 0 items</span>
                    <nav aria-label="Medicines pagination">
                        <ul class="pagination justify-content-center mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!-- Add New Medicine Modal                         -->
        <!-- ══════════════════════════════════════════════ -->
        <div class="modal fade" id="addNewMedicineModal" tabindex="-1" aria-labelledby="addNewMedicineModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addNewMedicineModalLabel">
                            <i class="bi bi-plus-circle me-2"></i>Add Medicine
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="add-new-medicine-form" novalidate>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="create_medicine">
                            <div class="modal-form-grid">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                    <div class="invalid-feedback">Name is required.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="barcode" class="form-label">Barcode (13 digits) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="barcode" name="barcode" pattern="[0-9]{13}" required>
                                    <div class="invalid-feedback">Barcode must be 13 digits.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="0" required>
                                    <div class="invalid-feedback">Quantity must be non-negative.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Type <span class="text-danger">*</span></label>
                                    <input type="hidden" id="type" name="type" required>
                                    <div class="type-combobox" id="add-type-combobox">
                                        <div class="type-input-wrapper">
                                            <input type="text" class="form-control type-display-input"
                                                   id="add-type-display"
                                                   placeholder="Select or type a new type..."
                                                   autocomplete="off">
                                            <i class="bi bi-chevron-down type-chevron"></i>
                                        </div>
                                        <div class="type-dropdown" id="add-type-dropdown">
                                            <div class="type-dropdown-search">
                                                <input type="text" id="add-type-search" placeholder="Search types...">
                                            </div>
                                            <div class="type-dropdown-list" id="add-type-list"></div>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback d-block" id="add-type-error" style="display:none!important"></div>
                                </div>
                                <div class="mb-3">
                                    <label for="expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                                    <div class="invalid-feedback">Expiry date must be today or later.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="unit_price" class="form-label">Unit Price (&#8369;) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="unit_price" name="unit_price" min="0.01" required>
                                    <div class="invalid-feedback">Unit price must be greater than 0.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="min_order_quantity" class="form-label">Min Order Qty</label>
                                    <input type="number" class="form-control" id="min_order_quantity" name="min_order_quantity" min="1" value="1">
                                </div>
                                <div class="mb-3 full-width">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                                    <div class="invalid-feedback">Description is required.</div>
                                </div>
                                <div class="form-check mb-3 full-width">
                                    <input class="form-check-input" type="checkbox" id="preferred" name="preferred" value="1">
                                    <!-- <label class="form-check-label" for="preferred">
                                        Mark as Preferred Supplier
                                    </label> -->
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary action-btn">
                                <i class="bi bi-save me-1"></i> Save Medicine
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Supply Modal -->
        <div class="modal fade" id="addSupplyModal" tabindex="-1" aria-labelledby="addSupplyModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="addSupplyModalLabel"><i class="bi bi-box-arrow-in-down me-2"></i>Add Supply</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="add-supply-form" novalidate>
                        <div class="modal-body">
                            <input type="hidden" name="action" value="add_supply">
                            <input type="hidden" id="supply_medicine_id" name="medicine_id">
                            <div class="alert alert-light border mb-3">
                                <strong>Medicine:</strong> <span id="supply_medicine_name"></span>
                            </div>
                            <div class="mb-3">
                                <label for="supply_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="supply_quantity" name="quantity" min="1" step="1" required>
                            </div>
                            <div class="mb-3">
                                <label for="supply_date" class="form-label">Supply Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="supply_date" name="supply_date" max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-0">
                                <label for="supply_notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="supply_notes" name="notes" rows="3" maxlength="500" placeholder="Optional reference or delivery note"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success action-btn"><i class="bi bi-check-circle me-1"></i>Record Supply</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Medicine Modal -->
        <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Medicine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="edit-medicine-form" novalidate>
                            <input type="hidden" name="action" value="update_medicine">
                            <input type="hidden" id="edit_medicine_id" name="medicine_id">

                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                                <div class="invalid-feedback">Name is required.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_barcode" class="form-label">Barcode (13 digits) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_barcode" name="barcode" pattern="[0-9]{13}" required>
                                <div class="invalid-feedback">Barcode must be 13 digits.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" required>
                                <div class="invalid-feedback">Quantity must be non-negative.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Type <span class="text-danger">*</span></label>
                                <input type="hidden" id="edit_type" name="type" required>
                                <div class="type-combobox" id="edit-type-combobox">
                                    <div class="type-input-wrapper">
                                        <input type="text" class="form-control type-display-input"
                                               id="edit-type-display"
                                               placeholder="Select or type a new type..."
                                               autocomplete="off">
                                        <i class="bi bi-chevron-down type-chevron"></i>
                                    </div>
                                    <div class="type-dropdown" id="edit-type-dropdown">
                                        <div class="type-dropdown-search">
                                            <input type="text" id="edit-type-search" placeholder="Search types...">
                                        </div>
                                        <div class="type-dropdown-list" id="edit-type-list"></div>
                                    </div>
                                </div>
                                <div class="invalid-feedback d-block" id="edit-type-error" style="display:none!important"></div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                                <div class="invalid-feedback">Description is required.</div>
                            </div>
                            <div class="mb-3">
                                <label for="edit_expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_expiry_date" name="expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                                <div class="invalid-feedback">Expiry date must be today or later.</div>
                            </div>

                            <input type="hidden" id="edit_unit_price" name="unit_price">
                            <input type="hidden" id="edit_min_order" name="min_order_quantity">
                            <input class="d-none" type="checkbox" id="edit_preferred" name="preferred" value="1" tabindex="-1">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary action-btn me-2">
                                    <i class="bi bi-save me-1"></i> Update Medicine
                                </button>
                                <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!-- Remove Medicine Modal                          -->
        <!-- ══════════════════════════════════════════════ -->
        <div class="modal fade" id="deleteMedicineModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Remove Medicine</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Remove <strong id="delete_medicine_name"></strong> from your inventory?</p>
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>This removes your supplier stock and pricing. It does not delete admin order history.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary action-btn" id="confirm-delete-medicine-btn">
                            <i class="bi bi-trash me-1"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.isSupplier = <?= json_encode($is_supplier) ?>;
        window.supplierId = <?= json_encode($supplier_id) ?>;
        window.debugMode = <?= json_encode(isset($_GET['debug']) && $_GET['debug'] === '1') ?>;
    </script>
    <script src="assets/js/sup_medicine.js"></script>
</body>
</html>
<?php $conn->close(); ?>
