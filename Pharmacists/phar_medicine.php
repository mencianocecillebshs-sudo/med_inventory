<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Management - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }
        
        .page-header { 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: white; 
            padding: 2rem; 
            border-radius: 15px; 
            margin-bottom: 2rem; 
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); 
        }
        .page-header h2 { color: white; margin: 0; }
        
        .card { 
            border-radius: 15px; 
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); 
            background: #fff; 
            border: none; 
        }
        .card-body { padding: 0; }
        
        .table-container {
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 15px;
            background: var(--table-bg);
            border: 1px solid var(--table-border);
        }
        .table-container::-webkit-scrollbar { width: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        
        .table { 
            border-radius: 12px; overflow: hidden; margin-bottom: 0;
            table-layout: auto; width: 100%;
            background-color: var(--table-bg); color: var(--text-primary);
        }
        .table th, .table td {
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 150px; padding: 1rem;
            border-color: var(--table-border); color: var(--text-primary);
        }
        .table th { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); 
            color: #fff; font-weight: 600; position: sticky; top: 0; z-index: 10; border: none;
        }
        .table td { vertical-align: middle; color: var(--text-primary); }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: var(--table-hover-bg); transform: translateX(2px); }
        .table-striped > tbody > tr:nth-of-type(odd) > * { background-color: var(--table-stripe-bg); }
        
        .action-btn { 
            padding: 0.5rem 1rem; font-size: 0.9rem; border-radius: 8px; 
            transition: all 0.3s ease; margin: 0.125rem;
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(27, 94, 63, 0.3); }
        
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #78350f; }
        .btn-warning:hover { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; }
        .btn-danger:hover { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        
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
            background: var(--modal-bg); color: var(--text-primary);
        }
        .modal-header { 
            border-bottom: none; padding: 1.5rem 2rem; border-radius: 20px 20px 0 0;
            background: var(--modal-header-bg); color: var(--modal-header-text);
        }
        .modal-header.bg-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); }
        .modal-header.bg-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
        .modal-body { background: var(--modal-bg); color: var(--text-primary); }
        .modal-footer { background: var(--modal-bg); border-top: 1px solid var(--modal-border); }
        
        .invalid-feedback { font-size: 0.85rem; color: #dc2626; font-weight: 500; }
        
        #toggle-sidebar-mobile { 
            position: fixed; top: 1rem; left: 1rem; z-index: 1100; 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: #fff; border-radius: 50%; width: 45px; height: 45px; border: none; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); 
        }
        .spinner-border-sm { width: 1rem; height: 1rem; }
        select option { color: #000000 !important; background-color: #ffffff !important; }

        /* ── Item Type Tabs ── */
        .item-type-tab.active {
            background-color: #1b5e3f;
            border-color: #1b5e3f;
            color: #fff;
        }

        /* ── Custom Type Combobox ── */
        .type-combobox {
            position: relative;
        }
        .type-combobox .type-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .type-combobox .type-input-wrapper .form-control {
            padding-right: 2.8rem;
        }
        .type-combobox .type-chevron {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            transition: transform 0.2s ease;
            font-size: 1rem;
        }
        .type-combobox.open .type-chevron {
            transform: translateY(-50%) rotate(180deg);
        }
        .type-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 2px solid #1b5e3f;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.15);
            z-index: 1060;
            overflow: hidden;
            max-height: 220px;
            flex-direction: column;
        }
        .type-combobox.open .type-dropdown {
            display: flex;
        }
        .type-dropdown-search {
            padding: 0.6rem 0.85rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .type-dropdown-search input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.875rem;
            color: #1e293b;
        }
        .type-dropdown-list {
            overflow-y: auto;
            flex: 1;
        }
        .type-dropdown-list::-webkit-scrollbar { width: 6px; }
        .type-dropdown-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        .type-dropdown-item {
            padding: 0.6rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.15s ease;
        }
        .type-dropdown-item:hover,
        .type-dropdown-item.active {
            background: #f0fdf4;
            color: #1b5e3f;
        }
        .type-dropdown-item .type-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dcfce7;
            color: #166534;
        }
        .type-dropdown-item.new-type-item {
            border-top: 1px solid #e2e8f0;
            color: #1b5e3f;
            font-weight: 600;
        }
        .type-dropdown-item.new-type-item .bi {
            font-size: 0.85rem;
        }
        .type-dropdown-empty {
            padding: 1rem;
            text-align: center;
            color: #94a3b8;
            font-size: 0.875rem;
        }
        /* Dark mode support */
        [data-bs-theme="dark"] .type-dropdown,
        .dark-mode .type-dropdown {
            background: #1e293b;
            border-color: #1b5e3f;
        }
        [data-bs-theme="dark"] .type-dropdown-search,
        .dark-mode .type-dropdown-search {
            background: #0f172a;
            border-color: #334155;
        }
        [data-bs-theme="dark"] .type-dropdown-search input,
        .dark-mode .type-dropdown-search input { color: #e2e8f0; }
        [data-bs-theme="dark"] .type-dropdown-item,
        .dark-mode .type-dropdown-item { color: #e2e8f0; }
        [data-bs-theme="dark"] .type-dropdown-item:hover,
        [data-bs-theme="dark"] .type-dropdown-item.active,
        .dark-mode .type-dropdown-item:hover,
        .dark-mode .type-dropdown-item.active { background: #1b5e3f33; color: #4ade80; }
        [data-bs-theme="dark"] .type-badge,
        .dark-mode .type-badge { background: #14532d; color: #86efac; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <button class="btn btn-primary d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-capsule me-2"></i> Medicine Management</h2>
        </div>
        
        <div class="d-flex mb-3 align-items-center gap-2 flex-wrap">
            <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal" id="add-medicine-btn">
                <i class="bi bi-plus-circle me-1"></i> Add Medicine
            </button>
            <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal" id="add-other-product-btn">
                <i class="bi bi-plus-circle me-1"></i> Other Products
            </button>
            <input type="text" class="form-control" id="search" placeholder="Search medicines..." style="max-width: 300px;">
            <select class="form-select" id="sort" style="width: 200px; min-width: 200px;">
                <option value="name">Sort by Name</option>
                <option value="quantity">Sort by Quantity</option>
                <option value="expiry_date">Sort by Expiry Date</option>
            </select>
        </div>

        <div class="d-flex mb-4 align-items-center gap-2 flex-wrap">
            <div class="btn-group" role="group" aria-label="Item type view" id="item-type-tabs">
                <button type="button" class="btn btn-outline-secondary item-type-tab active" data-value="">All Items</button>
                <button type="button" class="btn btn-outline-secondary item-type-tab" data-value="medicine">Medicines</button>
                <button type="button" class="btn btn-outline-secondary item-type-tab" data-value="non-medicine">Other Products</button>
            </div>
            <select class="form-select d-none" id="filter-item-type" style="width: 180px; min-width: 180px;">
                <option value="">All Item Types</option>
                <option value="medicine">Medicine Only</option>
                <option value="non-medicine">Non-Medicine Only</option>
            </select>
        </div>
        
        <div class="card admin-table-card">
            <div class="card-body p-0">
                <div class="table-container admin-table-scroll">
                    <div id="table-loading" class="text-center p-4" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <table class="table table-hover mb-0" data-admin-no-pagination="true">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Barcode</th>
                                <th>Quantity</th>
                                <th>Category</th>
                                <th>Item Type</th>
                                <th>Description</th>
                                <th>Expiry Date</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="medicine-table"></tbody>
                    </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="medicine-page-info" class="text-muted"></small>
                    <nav class="pagination-container" id="pagination"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Medicine</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to delete this medicine?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary action-btn" id="confirm-delete-medicine-btn">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Medicine Modal -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Medicine</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="add-medicine-form" novalidate>
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
                            <input type="number" class="form-control" id="quantity" name="quantity" required min="0">
                            <div class="invalid-feedback">Quantity must be non-negative.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <!-- Hidden input that actually gets submitted -->
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
                            <label for="item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="medicine">Medicine</option>
                                <option value="non-medicine">Non-Medicine</option>
                            </select>
                            <div class="invalid-feedback">Item type is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            <div class="invalid-feedback">Description is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Expiry date must be today or later.</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary action-btn me-2" id="add-medicine-submit-btn">
                                <i class="bi bi-save me-1"></i> Save Medicine
                            </button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="edit-medicine-modal-title">Edit Medicine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-medicine-form" novalidate>
                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="edit_name" required>
                            <div class="invalid-feedback">Name is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_barcode" class="form-label">Barcode (13 digits) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_barcode" name="edit_barcode" pattern="[0-9]{13}" required>
                            <div class="invalid-feedback">Barcode must be 13 digits.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_quantity" name="edit_quantity" required min="0">
                            <div class="invalid-feedback">Quantity must be non-negative.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <!-- Hidden input that actually gets submitted -->
                            <input type="hidden" id="edit_type" name="edit_type" required>
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
                            <label for="edit_item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_item_type" name="edit_item_type" required>
                                <option value="medicine">Medicine</option>
                                <option value="non-medicine">Non-Medicine</option>
                            </select>
                            <div class="invalid-feedback">Item type is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_description" name="edit_description" rows="3" required></textarea>
                            <div class="invalid-feedback">Description is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_expiry_date" name="edit_expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Expiry date must be today or later.</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary action-btn me-2" id="edit-medicine-submit-btn">
                                <i class="bi bi-save me-1"></i> Update Medicine
                            </button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/phar_medicine.js"></script>
</body>
</html>