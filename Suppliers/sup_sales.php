<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/includes/supplier_auth.php';
requireSupplierPage($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales & Invoices - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; overflow-x: hidden; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; max-height: 100vh; overflow-y: auto; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }
        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); background: #fff; border: none; }
        .table-container { max-height: calc(100vh - 280px); overflow-y: auto; }
        .table { border-radius: 12px; margin-bottom: 0; white-space: nowrap; width: 100%; }
        .table th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; padding: 0.75rem; border: none; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .table td { vertical-align: middle; color: #2d3748; padding: 0.5rem; border-color: #e2e8f0; white-space: nowrap; }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; }
        .payment-badge { padding: 0.3rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; white-space: nowrap; }
        .payment-cash { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .payment-credit-card { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
        .payment-debit-card { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; }
        .payment-gcash { background: linear-gradient(135deg, #0066cc 0%, #003d7a 100%); color: white; }
        .payment-maya { background: linear-gradient(135deg, #00b140 0%, #008030 100%); color: white; }
        .payment-insurance { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
        .action-btn { padding: 0.3rem 0.6rem; font-size: 0.8rem; border-radius: 6px; transition: all 0.3s ease; margin: 0 0.1rem; font-weight: 500; white-space: nowrap; }
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); border: none; }
        .btn-info:hover { background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; }
        .btn-danger:hover { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; }
        .btn-success:hover { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .btn-outline-primary { border: 2px solid #1b5e3f; color: #1b5e3f; background: transparent; font-weight: 600; }
        .btn-outline-primary:hover { background: #1b5e3f; color: white; }
        .modal-content { border-radius: 20px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
        .modal-dialog { max-height: 90vh; display: flex; align-items: center; }
        .modal-dialog-scrollable .modal-body { max-height: calc(90vh - 180px); overflow-y: auto; }
        .modal-body::-webkit-scrollbar { width: 10px; }
        .modal-body::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .modal-body::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .modal-header { border-bottom: none; padding: 1.5rem 2rem; border-radius: 20px 20px 0 0; }
        .modal-header.bg-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); }
        .modal-header.bg-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e2e8f0; transition: all 0.3s ease; padding: 0.5rem 0.75rem; }
        .form-control:focus, .form-select:focus { border-color: #1b5e3f; box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.15); transform: translateY(-1px); }
        .form-label { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }
        .medicine-row { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0; border-radius: 15px; padding: 1rem; margin-bottom: 1rem; position: relative; transition: all 0.3s ease; }
        .medicine-row:hover { border-color: #cbd5e1; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); }
        .medicine-row-header { font-size: 0.9rem; font-weight: 700; color: #1b5e3f; margin-bottom: 0.5rem; padding-bottom: 0.3rem; border-bottom: 2px solid #e2e8f0; }
        .remove-row { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; color: white; font-size: 0.9rem; cursor: pointer; position: absolute; top: 0.5rem; right: 0.5rem; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3); }
        .remove-row:hover { transform: scale(1.1) rotate(90deg); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.5); }
        .invalid-feedback { font-size: 0.8rem; color: #dc2626; font-weight: 500; }
        #toggle-sidebar-mobile { position: fixed; top: 1rem; left: 1rem; z-index: 1100; background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; border-radius: 50%; width: 45px; height: 45px; border: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        #sale-medicine-container { max-height: 400px; overflow-y: auto; padding-right: 0.5rem; }
        #sale-medicine-container::-webkit-scrollbar { width: 8px; }
        #sale-medicine-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        #sale-medicine-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .input-group-text { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0; font-weight: 600; color: #1b5e3f; }
        .page-header { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: white; padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem; box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); }
        .page-title { font-size: 1.8rem; font-weight: 700; margin-bottom: 0; letter-spacing: 0.5px; }
        .page-subtitle { font-size: 1rem; opacity: 0.8; margin-top: 0.3rem; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; }
        .summary-label { color: #1e293b; }
        .summary-value { color: #1b5e3f; }
        .invoice-header { text-align: center; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; }
        .invoice-header h3 { font-weight: 700; color: #1b5e3f; margin-bottom: 0.5rem; }
        .invoice-details p { margin-bottom: 0.3rem; font-size: 0.9rem; }
        .summary-section { margin-top: 1rem; border-top: 2px solid #e2e8f0; padding-top: 1rem; }
        .revenue-card { border: none; border-radius: 15px; box-shadow: 0 4px 16px rgba(0,0,0,.06); height: 100%; }
        .revenue-card .card-body { padding: 1.25rem 1.5rem; }
        .revenue-card .revenue-label { font-size: .85rem; color: #64748b; margin-bottom: .25rem; font-weight: 600; }
        .revenue-card .revenue-value { font-size: 1.75rem; font-weight: 700; color: #1b5e3f; margin-bottom: 0; }
        .revenue-card .revenue-meta { font-size: .8rem; color: #94a3b8; }
        .revenue-card.revenue-primary { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); }
        .revenue-card.revenue-profit { background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); }
        .revenue-card.revenue-filtered { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }
        .revenue-card.revenue-count { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); }
        @media print { .no-print { display: none; } .modal-content { box-shadow: none; border: none; } .modal-body { padding: 0; } }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2 class="page-title">Sales & Invoices</h2>
            <p class="page-subtitle">Manage your sales and view invoices</p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card revenue-card revenue-primary">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="revenue-label mb-1">Total Revenue</p>
                            <p class="revenue-value" id="sales-total-revenue">₱0.00</p>
                            <p class="revenue-meta mb-0">All-time earnings</p>
                        </div>
                        <i class="bi bi-cash-stack fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card revenue-card revenue-profit">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="revenue-label mb-1">Profit</p>
                            <p class="revenue-value" id="sales-total-profit">₱0.00</p>
                            <p class="revenue-meta mb-0">All-time profit</p>
                        </div>
                        <i class="bi bi-graph-up-arrow fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card revenue-card revenue-count">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="revenue-label mb-1">Total Sales</p>
                            <p class="revenue-value" id="sales-total-count">0</p>
                            <p class="revenue-meta mb-0" id="sales-filtered-count">0 shown</p>
                        </div>
                        <i class="bi bi-receipt-cutoff fs-1 text-secondary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="card-title mb-0">Sales List</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#createSaleModal">
                            <i class="bi bi-cart-plus me-1"></i> New Sale
                        </button>
                        <button class="btn btn-outline-primary action-btn">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <input type="text" id="search-input" class="form-control" placeholder="Search by invoice or customer...">
                    </div>
                    <div class="col-md-3">
                        <select id="filter-payment" class="form-select">
                            <option value="">All Payment Methods</option>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="gcash">GCash</option>
                            <option value="maya">Maya</option>
                            <option value="insurance">Insurance</option>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table table-hover" id="sales-table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Total</th>
                                <th>Cashier</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sales-table-body">
                            <tr><td colspan="8" class="text-center py-4">Loading sales...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="sup-table-footer">
                    <span class="sup-pagination-info" id="sales-pagination-info">Page 1 of 1 · 0 items</span>
                    <nav aria-label="Sales pagination">
                        <ul class="pagination justify-content-center mb-0" id="sales-pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Sale Modal -->
    <div class="modal fade" id="createSaleModal" tabindex="-1" aria-labelledby="createSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createSaleModalLabel"><i class="bi bi-cart-plus me-2"></i> New Sale</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="create-sale-form">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Customer/Order *</label>
                                <select class="form-select" id="order-dropdown" required>
                                    <option value="">Walk-in Customer</option>
                                </select>
                                <small class="text-muted">Select accepted order or walk-in</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Payment Method *</label>
                                <select class="form-select" id="sale-payment-method" name="payment_method" required>
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="gcash">GCash</option>
                                    <option value="maya">Maya</option>
                                    <option value="insurance">Insurance</option>
                                </select>
                            </div>
                        </div>

                        <!-- New: Order info display -->
                        <div id="order-info" class="alert alert-info" style="display: none; margin-bottom: 1rem;"></div>

                        <div id="sale-medicine-container" class="mb-4">
                            <!-- Medicine rows will be added here -->
                        </div>

                        <button type="button" id="add-sale-medicine-row-btn" class="btn btn-outline-primary action-btn mb-3">
                            <i class="bi bi-plus-circle me-1"></i> Add Medicine
                        </button>

                        <div class="totals-section">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Discount (%)</label>
                                    <input type="number" class="form-control" id="sale-discount" name="discount" min="0" value="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Tax (%)</label>
                                    <input type="number" class="form-control" id="sale-tax" name="tax" min="0" value="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Subtotal</label>
                                    <div class="input-group">
                                        <span class="input-group-text">&#8369;</span>
                                        <input type="number" class="form-control" id="sale-subtotal" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="summary-row">
                                <span class="summary-label">Total Amount:</span>
                                <span class="summary-value">&#8369;<span id="sale-total-amount-display">0.00</span></span>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Amount Paid <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">&#8369;</span>
                                        <input type="number" class="form-control" id="sale-amount-paid" name="amount_paid" min="0" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Change</label>
                                    <div class="input-group">
                                        <span class="input-group-text">&#8369;</span>
                                        <input type="number" class="form-control" id="sale-change-given" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="sale-total-amount" name="total_amount">

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success action-btn me-2" form="create-sale-form">
                        <i class="bi bi-check-circle me-1"></i> Complete Sale
                    </button>
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Invoice Modal -->
    <div class="modal fade" id="viewInvoiceModal" tabindex="-1" aria-labelledby="viewInvoiceModalLabel">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewInvoiceModalLabel"><i class="bi bi-file-text me-2"></i> Invoice Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="invoice-header">
                        <h3>SALES INVOICE</h3>
                        <p class="mb-0"><strong>Invoice #:</strong> <span id="view-invoice-number"></span></p>
                    </div>

                    <div class="invoice-details">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Customer:</strong> <span id="view-customer"></span></p>
                                <p><strong>Payment Method:</strong> <span id="view-payment-method"></span></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p><strong>Cashier:</strong> <span id="view-cashier"></span></p>
                                <p><strong>Date:</strong> <span id="view-date"></span></p>
                            </div>
                        </div>
                    </div>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="view-items-body"></tbody>
                    </table>

                    <div class="summary-section">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal:</span>
                            <span class="summary-value" id="view-subtotal"></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Discount:</span>
                            <span class="summary-value" id="view-discount"></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tax:</span>
                            <span class="summary-value" id="view-tax"></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Total Amount:</span>
                            <span class="summary-value" id="view-total"></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Amount Paid:</span>
                            <span class="summary-value" id="view-paid"></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Change Given:</span>
                            <span class="summary-value" id="view-change"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary action-btn" id="print-invoice-btn">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_sales.js"></script>
</body>
</html>
