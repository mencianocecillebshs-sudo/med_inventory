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
    <title>Sales & Invoices - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; overflow: hidden; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; }
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
        .page-header h2 { color: white; margin: 0; }
        .date-info { font-size: 0.85rem; color: #475569; white-space: nowrap; }
        .text-muted { color: #94a3b8 !important; font-style: italic; }
        .actions-col { min-width: 120px; }
        .summary-section { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 2px solid #e2e8f0; border-radius: 15px; padding: 1.5rem; margin-top: 1rem; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; }
        .summary-row:last-child { border-bottom: none; font-weight: 700; font-size: 1.1rem; }
        .summary-label { font-weight: 600; color: #475569; }
        .summary-value { font-weight: 700; color: #1b5e3f; }
        .revenue-card { border: none; border-radius: 15px; box-shadow: 0 4px 16px rgba(0,0,0,.06); height: 100%; }
        .revenue-card .card-body { padding: 1.25rem 1.5rem; }
        .revenue-card .revenue-label { font-size: .85rem; color: #64748b; margin-bottom: .25rem; font-weight: 600; }
        .revenue-card .revenue-value { font-size: 1.75rem; font-weight: 700; color: #1b5e3f; margin-bottom: 0; }
        .revenue-card .revenue-meta { font-size: .8rem; color: #94a3b8; }
        .revenue-card.revenue-primary { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); }
        .revenue-card.revenue-profit { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); }
        .revenue-card.revenue-filtered { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); }
        .revenue-card.revenue-count { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); }
        .clickable-summary-card { cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; }
        .clickable-summary-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        .clickable-summary-card:focus-visible { outline: 2px solid #1b5e3f; outline-offset: 2px; }
        .sales-filter-panel { background: #ffffff; border-left: 5px solid #1b5e3f; border-radius: 15px; padding: 1rem; box-shadow: 0 4px 16px rgba(0,0,0,.06); margin-bottom: 1rem; }
        .sales-filter-grid { display: grid; grid-template-columns: minmax(320px, 1.1fr) minmax(320px, 1.4fr) auto auto; gap: .85rem; align-items: end; }
        .sales-filter-title { display: flex; align-items: center; gap: .45rem; color: #0f3f28; font-weight: 800; margin-bottom: .55rem; }
        .sales-period-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .45rem; }
        .sales-period-btn { min-height: 42px; border: 1px solid #b7e4ce; background: #f8fffb; color: #0f3f28; border-radius: 10px; font-weight: 800; transition: all .2s ease; }
        .sales-period-btn:hover, .sales-period-btn:focus-visible { background: #dff8ec; color: #062f1d; border-color: #1b5e3f; box-shadow: 0 8px 20px rgba(15,63,40,.12); }
        .sales-period-btn.active { background: linear-gradient(135deg, #1b5e3f, #0f3f28); color: #ffffff; border-color: transparent; box-shadow: 0 8px 18px rgba(27,94,63,.22); }
        .sales-date-range { display: grid; grid-template-columns: 1fr auto 1fr; gap: .6rem; align-items: end; padding: .65rem; border: 1px solid #d1fae5; border-radius: 14px; background: linear-gradient(135deg, #f8fafc, #ecfdf5); }
        .sales-date-field { min-width: 0; }
        .sales-date-field .form-label { display: flex; align-items: center; gap: .35rem; margin-bottom: .35rem; color: #335045; font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .sales-date-input { position: relative; }
        .sales-date-input i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: #1b5e3f; pointer-events: none; z-index: 1; }
        .sales-date-input .form-control { min-height: 42px; padding-left: 2.35rem; background: #fff; color: #1e293b; border-color: #b7e4ce; box-shadow: 0 6px 16px rgba(15,63,40,.06); }
        .sales-date-input .form-control:hover { background: #f8fffb; border-color: #1b5e3f; }
        .sales-date-input input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: .85; filter: sepia(70%) saturate(600%) hue-rotate(88deg) brightness(70%); }
        .sales-date-separator { display: grid; place-items: center; width: 34px; height: 34px; margin-bottom: .2rem; border-radius: 999px; background: #1b5e3f; color: #fff; }
        .sales-range-label { grid-column: 1 / -1; display: flex; align-items: center; gap: .4rem; min-height: 1rem; color: #64748b; font-size: .8rem; font-weight: 700; }
        .sales-range-label i { color: #1b5e3f; }
        .sales-filter-panel .action-btn { min-height: 42px; padding-inline: 1rem; }
        .stock-info { display: block; margin-top: 0.25rem; font-size: 0.75rem; color: #64748b; }
        .invoice-header { text-align: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 3px solid #1b5e3f; }
        .invoice-details { margin-bottom: 2rem; }
        .invoice-details table { width: 100%; }
        @media print {
            .btn, .modal-header, .modal-footer { display: none !important; }
            body { background: white; }
            .modal-body { max-height: none !important; overflow: visible !important; }
        }
        @media (max-width: 1200px) {
            .sales-filter-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .sales-filter-grid, .sales-date-range { grid-template-columns: 1fr; }
            .sales-period-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .sales-date-separator { width: 100%; height: 28px; margin: 0; }
            .sales-date-separator i { transform: rotate(90deg); }
        }
        body.dark-mode .sales-filter-panel {
            background: #111827 !important;
            border-left-color: #2ecc71 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,.45) !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .sales-filter-title,
        body.dark-mode .sales-date-field .form-label { color: #a7f3d0 !important; }
        body.dark-mode .sales-period-btn {
            background: rgba(46,204,113,.12) !important;
            border-color: rgba(46,204,113,.22) !important;
            color: #6ee7b7 !important;
        }
        body.dark-mode .sales-period-btn:hover,
        body.dark-mode .sales-period-btn:focus-visible {
            background: #163828 !important;
            border-color: #6ee7b7 !important;
            color: #ffffff !important;
        }
        body.dark-mode .sales-period-btn.active {
            background: linear-gradient(135deg, #2ecc71, #1b5e3f) !important;
            color: #062f1d !important;
        }
        body.dark-mode .sales-date-range {
            background: linear-gradient(135deg, rgba(46,204,113,.12), rgba(15,63,40,.12)), #0f172a !important;
            border-color: rgba(46,204,113,.24) !important;
        }
        body.dark-mode .sales-date-input i,
        body.dark-mode .sales-range-label i { color: #6ee7b7 !important; }
        body.dark-mode .sales-date-input .form-control {
            background: #111827 !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        body.dark-mode .sales-date-input .form-control:hover,
        body.dark-mode .sales-date-input .form-control:focus {
            background: #172033 !important;
            color: #ffffff !important;
            border-color: #6ee7b7 !important;
        }
        body.dark-mode .sales-date-input input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(88%) sepia(22%) saturate(798%) hue-rotate(92deg) brightness(98%);
        }
        body.dark-mode .sales-date-separator {
            background: #2ecc71 !important;
            color: #062f1d !important;
        }
        body.dark-mode .sales-range-label { color: #cbd5e1 !important; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>
    <div class="main-content admin-table-page">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2><i class="bi bi-receipt me-2"></i> Sales & Invoices</h2>
        </div>

        <div class="sales-filter-panel" aria-label="Sales date filters">
            <div class="sales-filter-grid">
                <div>
                    <div class="sales-filter-title"><i class="bi bi-calendar-range"></i> Sales Period</div>
                    <div class="sales-period-options" role="group" aria-label="Quick sales periods">
                        <button type="button" class="sales-period-btn active" data-sales-period="all">All Time</button>
                        <button type="button" class="sales-period-btn" data-sales-period="week">Weekly</button>
                        <button type="button" class="sales-period-btn" data-sales-period="month">Monthly</button>
                        <button type="button" class="sales-period-btn" data-sales-period="six-months">6 Months</button>
                    </div>
                </div>
                <div class="sales-date-range">
                    <div class="sales-date-field">
                        <label class="form-label" for="sales-start-date"><i class="bi bi-calendar-event"></i> Start</label>
                        <div class="sales-date-input">
                            <i class="bi bi-calendar3"></i>
                            <input type="date" class="form-control" id="sales-start-date" aria-describedby="sales-range-label">
                        </div>
                    </div>
                    <div class="sales-date-separator" aria-hidden="true"><i class="bi bi-arrow-right"></i></div>
                    <div class="sales-date-field">
                        <label class="form-label" for="sales-end-date"><i class="bi bi-calendar-check"></i> End</label>
                        <div class="sales-date-input">
                            <i class="bi bi-calendar3"></i>
                            <input type="date" class="form-control" id="sales-end-date" aria-describedby="sales-range-label">
                        </div>
                    </div>
                    <div class="sales-range-label" id="sales-range-label">
                        <i class="bi bi-info-circle"></i>
                        <span id="sales-range-text">Showing all customer sales.</span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary action-btn" id="apply-sales-range">
                    <i class="bi bi-check-circle me-1"></i> Apply
                </button>
                <button type="button" class="btn btn-outline-primary action-btn" id="reset-sales-range">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                </button>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-3 col-md-6">
                <div class="card revenue-card revenue-primary clickable-summary-card" id="revenue-card" role="button" tabindex="0">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="revenue-label mb-1">Total Revenue <i class="bi bi-eye ms-1 small text-muted"></i></p>
                            <p class="revenue-value" id="sales-total-revenue">₱0.00</p>
                            <p class="revenue-meta mb-0">All-time earnings — click to view</p>
                        </div>
                        <i class="bi bi-cash-stack fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card revenue-card revenue-profit clickable-summary-card" id="profit-card" role="button" tabindex="0">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="revenue-label mb-1">Net Profit <i class="bi bi-eye ms-1 small text-muted"></i></p>
                            <p class="revenue-value" id="sales-total-profit">₱0.00</p>
                            <p class="revenue-meta mb-0" id="sales-filtered-profit-meta">Matching current filters: ₱0.00</p>
                        </div>
                        <i class="bi bi-graph-up-arrow fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card revenue-card revenue-filtered">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="revenue-label mb-1">Filtered Revenue</p>
                            <p class="revenue-value" id="sales-filtered-revenue">₱0.00</p>
                            <p class="revenue-meta mb-0">Matching current filters</p>
                        </div>
                        <i class="bi bi-funnel fs-1 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
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

        <div class="d-flex mb-3 align-items-center gap-2 flex-wrap">
            <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#createSaleModal">
                <i class="bi bi-plus-circle me-1"></i> New Sale
            </button>
            <select class="form-select" id="filter-payment" style="width: 180px;">
                <option value="">All Payments</option>
                <option value="cash">Cash</option>
                <option value="credit_card">Credit Card</option>
                <option value="debit_card">Debit Card</option>
                <option value="gcash">GCash</option>
                <option value="maya">Maya</option>
                <option value="insurance">Insurance</option>
            </select>
            <input type="text" class="form-control" id="search-input" placeholder="Search invoices..." style="max-width: 300px;">
        </div>
        <div class="card admin-table-card">
            <div class="card-body p-0">
                <div class="table-container admin-table-scroll">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Purchase</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Cashier</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="sales-table"></tbody>
                    </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="sales-page-info" class="text-muted"></small>
                    <nav id="sales-pagination" aria-label="Sales pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Sale Modal -->
    <div class="modal fade" id="createSaleModal" tabindex="-1" aria-labelledby="createSaleModalLabel">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createSaleModalLabel"><i class="bi bi-cart-plus me-2"></i> New Sale</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="create-sale-form" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Purchase (Optional)</label>
                                <select class="form-select" id="sale-purchase">
                                    <option value="">Walk-in Customer</option>
                                </select>
                                <small class="text-muted">Select if this sale is for a specific purchase</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" id="sale-payment-method" required>
                                    <option value="cash">Cash</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="debit_card">Debit Card</option>
                                    <option value="gcash">GCash</option>
                                    <option value="maya">Maya</option>
                                    <option value="insurance">Insurance</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Items <span class="text-danger">*</span></label>
                            <div id="sale-medicine-container" class="mb-2"></div>
                            <button type="button" class="btn btn-outline-primary action-btn w-100" id="add-sale-medicine-row-btn">
                                <i class="bi bi-plus-circle me-1"></i> Add Medicine
                            </button>
                        </div>

                        <div class="summary-section">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Discount (%)</label>
                                    <input type="number" class="form-control" id="sale-discount" min="0" max="100" value="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Tax (%)</label>
                                    <input type="number" class="form-control" id="sale-tax" min="0" value="0" step="0.01">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Subtotal</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="sale-subtotal" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="summary-row">
                                <span class="summary-label">Total Amount:</span>
                                <span class="summary-value">₱<span id="sale-total-amount-display">0.00</span></span>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Amount Paid <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="sale-amount-paid" min="0" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Change</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="sale-change-given" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="sale-total-amount">

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-success action-btn me-2">
                                <i class="bi bi-check-circle me-1"></i> Complete Sale
                            </button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
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
                                <p><strong>Purchase:</strong> <span id="view-purchase"></span></p>
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

    <div class="modal fade" id="voidSaleModal" tabindex="-1" aria-labelledby="voidSaleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="voidSaleModalLabel"><i class="bi bi-x-circle me-2"></i>Void Sale</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">This will void the selected sale and restore medicine quantities.</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary action-btn" id="confirm-void-sale-btn">
                        <i class="bi bi-check-circle me-1"></i>Confirm Void
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update display when total changes
        document.addEventListener('DOMContentLoaded', () => {
            const totalInput = document.getElementById('sale-total-amount');
            const totalDisplay = document.getElementById('sale-total-amount-display');
            
            const observer = new MutationObserver(() => {
                totalDisplay.textContent = parseFloat(totalInput.value || 0).toFixed(2);
            });
            
            observer.observe(totalInput, { attributes: true, attributeFilter: ['value'] });
            
            totalInput.addEventListener('input', () => {
                totalDisplay.textContent = parseFloat(totalInput.value || 0).toFixed(2);
            });
        });
    </script>
    <!-- ================================================================
         REVENUE / PROFIT DRILL-DOWN MODAL
    ================================================================ -->
    <div class="modal fade" id="summaryDrilldownModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="summary-drilldown-title"><i class="bi bi-bar-chart-line me-2"></i>Breakdown</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Cashier</th>
                                    <th>Items</th>
                                    <th class="text-end">Revenue</th>
                                    <th class="text-end">Profit</th>
                                </tr>
                            </thead>
                            <tbody id="summary-drilldown-body"></tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="4">Total</td>
                                    <td class="text-end" id="summary-drilldown-total-revenue">₱0.00</td>
                                    <td class="text-end" id="summary-drilldown-total-profit">₱0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/phar_sales.js?v=20260823-1"></script>
</body>

</html>
