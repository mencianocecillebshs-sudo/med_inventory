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
    <title>Inventory Management - Inventory System</title>
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

        .filter-select {
            width: 220px;
            min-width: 220px;
        }
        .inventory-item-modal {
            max-width: 760px;
        }
        .inventory-item-modal .modal-content {
            max-height: calc(100vh - 2rem);
        }
        .inventory-item-modal .modal-header {
            padding: 1rem 1.25rem;
        }
        .inventory-item-modal .modal-body {
            padding: 1.25rem;
            overflow-y: auto;
        }
        .inventory-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem 1rem;
        }
        .inventory-form-grid .mb-3 {
            margin-bottom: 0 !important;
        }
        .inventory-form-grid .form-span-2 {
            grid-column: 1 / -1;
        }
        .inventory-form-grid .form-label {
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
        }
        .inventory-form-grid .form-control,
        .inventory-form-grid .form-select {
            padding: 0.55rem 0.8rem;
        }
        .inventory-form-grid textarea.form-control {
            min-height: 72px;
        }
        .inventory-modal-actions {
            grid-column: 1 / -1;
            margin-top: 0.15rem;
        }
        #item_type option[value=""] {
            display: none;
        }
        @media (max-width: 576px) {
            .filter-select,
            #search {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
            }
            .inventory-form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── Custom Category Combobox ── */
        .category-combobox {
            position: relative;
        }
        .category-combobox .category-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .category-combobox .category-input-wrapper .form-control {
            padding-right: 2.8rem;
        }
        .category-combobox .category-chevron {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            transition: transform 0.2s ease;
            font-size: 1rem;
        }
        .category-combobox.open .category-chevron {
            transform: translateY(-50%) rotate(180deg);
        }
        .category-dropdown {
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
        .category-combobox.open .category-dropdown {
            display: flex;
        }
        .category-dropdown-search {
            padding: 0.6rem 0.85rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .category-dropdown-search input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.875rem;
            color: #1e293b;
        }
        .category-dropdown-list {
            overflow-y: auto;
            flex: 1;
        }
        .category-dropdown-list::-webkit-scrollbar { width: 6px; }
        .category-dropdown-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
        .category-dropdown-item {
            padding: 0.6rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.15s ease;
        }
        .category-dropdown-item:hover,
        .category-dropdown-item.active {
            background: #f0fdf4;
            color: #1b5e3f;
        }
        .category-dropdown-item .category-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dcfce7;
            color: #166534;
        }
        .category-dropdown-item.new-category-item {
            border-top: 1px solid #e2e8f0;
            color: #1b5e3f;
            font-weight: 600;
        }
        .category-dropdown-item.new-category-item .bi {
            font-size: 0.85rem;
        }
        .category-dropdown-empty {
            padding: 1rem;
            text-align: center;
            color: #94a3b8;
            font-size: 0.875rem;
        }
        /* Dark mode support */
        [data-bs-theme="dark"] .category-dropdown,
        .dark-mode .category-dropdown {
            background: #1e293b;
            border-color: #1b5e3f;
        }
        [data-bs-theme="dark"] .category-dropdown-search,
        .dark-mode .category-dropdown-search {
            background: #0f172a;
            border-color: #334155;
        }
        [data-bs-theme="dark"] .category-dropdown-search input,
        .dark-mode .category-dropdown-search input { color: #e2e8f0; }
        [data-bs-theme="dark"] .category-dropdown-item,
        .dark-mode .category-dropdown-item { color: #e2e8f0; }
        [data-bs-theme="dark"] .category-dropdown-item:hover,
        [data-bs-theme="dark"] .category-dropdown-item.active,
        .dark-mode .category-dropdown-item:hover,
        .dark-mode .category-dropdown-item.active { background: #1b5e3f33; color: #4ade80; }
        [data-bs-theme="dark"] .category-badge,
        .dark-mode .category-badge { background: #14532d; color: #86efac; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <button class="btn btn-primary d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-box-seam me-2"></i> Inventory Management</h2>
        </div>
        
        <div class="d-flex mb-3 align-items-center gap-2 flex-wrap">
            <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#addMedicineModal" id="add-medicine-btn">
                <i class="bi bi-plus-circle me-1"></i> Add Item
            </button>
            <input type="text" class="form-control" id="search" placeholder="Search inventory items..." style="max-width: 300px;">
            <select class="form-select" id="sort" style="width: 200px; min-width: 200px;">
                <option value="name">Sort by Name</option>
                <option value="quantity">Sort by Quantity</option>
                <option value="expiry_date">Sort by Expiry Date</option>
            </select>
            <select class="form-select filter-select" id="filter-item-type" aria-label="Filter by item type">
                <option value="">All Items</option>
                <option value="medicine">Medicines</option>
                <option value="non-medicine">Other Products</option>
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
                    <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Delete Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to delete this item?</p>
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

    <!-- Add Inventory Item Modal -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable inventory-item-modal">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Inventory Item</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="add-medicine-form" class="inventory-form-grid" novalidate>
                        <div class="mb-3">
                            <label for="item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="item_type" name="item_type" required>
                                <option value="" selected disabled>Choose what to add</option>
                                <option value="medicine">Medicine</option>
                                <option value="non-medicine">Other Product</option>
                            </select>
                            <div class="invalid-feedback">Item type is required.</div>
                        </div>
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
                        <div class="mb-3 form-span-2">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <!-- Hidden input that actually gets submitted -->
                            <input type="hidden" id="category" name="category" required>
                            <div class="category-combobox" id="add-category-combobox">
                                <div class="category-input-wrapper">
                                    <input type="text" class="form-control category-display-input" 
                                           id="add-category-display"
                                           placeholder="Select or type a category..."
                                           autocomplete="off">
                                    <i class="bi bi-chevron-down category-chevron"></i>
                                </div>
                                <div class="category-dropdown" id="add-category-dropdown">
                                    <div class="category-dropdown-search">
                                        <input type="text" id="add-category-search" placeholder="Search categories...">
                                    </div>
                                    <div class="category-dropdown-list" id="add-category-list"></div>
                                </div>
                            </div>
                            <div class="invalid-feedback d-block" id="add-category-error" style="display:none!important"></div>
                        </div>
                        <div class="mb-3 form-span-2">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="2" required></textarea>
                            <div class="invalid-feedback">Description is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Expiry date must be today or later.</div>
                        </div>
                        <div class="inventory-modal-actions d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary action-btn me-2" id="add-medicine-submit-btn">
                                <i class="bi bi-save me-1"></i> Save Item
                            </button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Inventory Item Modal -->
    <div class="modal fade" id="editMedicineModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable inventory-item-modal">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="edit-medicine-modal-title">Edit Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-medicine-form" class="inventory-form-grid" novalidate>
                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="mb-3">
                            <label for="edit_item_type" class="form-label">Item Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_item_type" name="edit_item_type" required>
                                <option value="medicine">Medicine</option>
                                <option value="non-medicine">Other Product</option>
                            </select>
                            <div class="invalid-feedback">Item type is required.</div>
                        </div>
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
                        <div class="mb-3 form-span-2">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <!-- Hidden input that actually gets submitted -->
                            <input type="hidden" id="edit_category" name="edit_category" required>
                            <div class="category-combobox" id="edit-category-combobox">
                                <div class="category-input-wrapper">
                                    <input type="text" class="form-control category-display-input"
                                           id="edit-category-display"
                                           placeholder="Select or type a new category..."
                                           autocomplete="off">
                                    <i class="bi bi-chevron-down category-chevron"></i>
                                </div>
                                <div class="category-dropdown" id="edit-category-dropdown">
                                    <div class="category-dropdown-search">
                                        <input type="text" id="edit-category-search" placeholder="Search categories...">
                                    </div>
                                    <div class="category-dropdown-list" id="edit-category-list"></div>
                                </div>
                            </div>
                            <div class="invalid-feedback d-block" id="edit-category-error" style="display:none!important"></div>
                        </div>
                        <div class="mb-3 form-span-2">
                            <label for="edit_description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="edit_description" name="edit_description" rows="2" required></textarea>
                            <div class="invalid-feedback">Description is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_expiry_date" class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_expiry_date" name="edit_expiry_date" required min="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Expiry date must be today or later.</div>
                        </div>
                        <div class="inventory-modal-actions d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary action-btn me-2" id="edit-medicine-submit-btn">
                                <i class="bi bi-save me-1"></i> Update Item
                            </button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/medicine.js"></script>
</body>
</html>
