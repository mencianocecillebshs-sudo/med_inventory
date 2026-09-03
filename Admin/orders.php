<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Robust DB include
$dbPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../Config/db.php'
];
$conn = null;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
if (!$conn) {
    error_log("DB connection failed in orders.php - paths tried: " . implode(', ', $dbPaths));
    $conn = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        /* Modal scroll fix */
        #addOrderModal .modal-dialog {
            max-height: 90vh;
            margin: 1.75rem auto;
        }
        #addOrderModal .modal-content {
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        #addOrderModal .modal-header,
        #addOrderModal .modal-footer {
            flex-shrink: 0;
        }
        #addOrderModal .modal-body {
            overflow-y: auto;
            overflow-x: hidden;
            flex: 1 1 auto;
        }
        #addOrderModal .modal-body::-webkit-scrollbar { width: 8px; }
        #addOrderModal .modal-body::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        #addOrderModal .modal-body::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-radius: 10px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fa;
            overflow: hidden;
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .page-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2);
            flex-shrink: 0;
        }
        .page-header h2 { color: white; margin: 0; font-weight: 700; font-size: 1.9rem; }
        .page-header .btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(10px);
        }
        .page-header .btn:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }

        .card {
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            background: #fff;
            border: none;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .card-body {
            padding: 0;
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow: hidden;
        }

        .filters-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border-left: 5px solid #1b5e3f;
            flex-shrink: 0;
        }

        .table-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: auto;
            background: white;
            border-radius: 12px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
            min-height: 0;
        }
        .table-container::-webkit-scrollbar { width: 10px; height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            border-radius: 10px;
        }

        .table { margin-bottom: 0; min-width: 1180px; }
        .orders-table {
            table-layout: fixed;
            min-width: 1480px;
        }
        .orders-table th:nth-child(1),
        .orders-table td:nth-child(1) { width: 70px; }
        .orders-table th:nth-child(2),
        .orders-table td:nth-child(2) { width: 270px; }
        .orders-table th:nth-child(3),
        .orders-table td:nth-child(3) { width: 450px; }
        .orders-table th:nth-child(4),
        .orders-table td:nth-child(4) { width: 70px; text-align: center; }
        .orders-table th:nth-child(5),
        .orders-table td:nth-child(5) { width: 140px; }
        .orders-table th:nth-child(6),
        .orders-table td:nth-child(6) { width: 110px; }
        .orders-table th:nth-child(7),
        .orders-table td:nth-child(7),
        .orders-table th:nth-child(8),
        .orders-table td:nth-child(8) { width: 145px; }
        .orders-table th:nth-child(9),
        .orders-table td:nth-child(9),
        .orders-table th:nth-child(10),
        .orders-table td:nth-child(10),
        .orders-table th:nth-child(11),
        .orders-table td:nth-child(11) { width: 120px; }
        .orders-table th:nth-child(12),
        .orders-table td:nth-child(12) { width: 190px; }
        .table th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #fff; font-weight: 600; padding: 1rem;
            border: none; position: sticky; top: 0; z-index: 10;
        }
        .table td { vertical-align: middle; padding: 0.9rem 1rem; border-color: #e2e8f0; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); transition: all 0.2s ease; }


        .status-badge {
            padding: 0.4rem 0.8rem; border-radius: 20px;
            font-size: 0.85rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .status-pending         { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .status-accepted        { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .status-declined        { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .status-shipped         { background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; }
        .status-out_for_delivery{ background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; }
        .status-delivered       { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; }
        .status-fulfilled       { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; }
        .status-cancelled       { background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); color: white; }
        .date-note { display: block; margin-top: 0.25rem; color: #64748b; font-size: 0.78rem; }
        .btn-mark-delivered {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: white; border: none; border-radius: 8px;
            padding: 0.3rem 0.7rem; font-size: 0.78rem; font-weight: 600;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-mark-delivered:hover { opacity: 0.85; transform: translateY(-1px); }

        /* Highlight the Actions column so it's easy to spot at a glance */
        .actions-cell {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-left: 3px solid #1b5e3f;
            white-space: nowrap;
        }
        .actions-cell .btn-outline-primary {
            color: #1b5e3f; border-color: #1b5e3f;
        }
        .actions-cell .btn-outline-primary:hover {
            background: #1b5e3f; border-color: #1b5e3f; color: #fff;
        }

        #toast-container {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 1095;
            display: grid;
            gap: 0.75rem;
            width: min(380px, calc(100vw - 2rem));
            pointer-events: none;
        }
        .system-toast {
            display: grid;
            grid-template-columns: 42px 1fr auto;
            gap: 0.85rem;
            align-items: start;
            padding: 0.95rem 1rem;
            border: 1px solid rgba(27, 94, 63, 0.16);
            border-left: 5px solid #1b5e3f;
            border-radius: 14px;
            background: #ffffff;
            color: #1e293b;
            box-shadow: 0 18px 44px rgba(15, 63, 40, 0.18);
            pointer-events: auto;
        }
        .system-toast-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            color: #ffffff;
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        }
        .system-toast-title {
            margin-bottom: 0.15rem;
            font-weight: 800;
            color: #0f172a;
        }
        .system-toast-message {
            color: #475569;
            font-size: 0.88rem;
            line-height: 1.35;
        }
        .system-toast .btn-close {
            margin-top: 0.25rem;
            box-shadow: none;
        }
        .system-toast.toast-success { border-left-color: #16a34a; }
        .system-toast.toast-danger { border-left-color: #dc2626; }
        .system-toast.toast-warning { border-left-color: #f59e0b; }
        .system-toast.toast-info { border-left-color: #2563eb; }
        .system-toast.toast-danger .system-toast-icon { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .system-toast.toast-warning .system-toast-icon { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #78350f; }
        .system-toast.toast-info .system-toast-icon { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }

        .order-line-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.85rem;
            padding: 0.85rem;
            border: 1px solid rgba(27, 94, 63, 0.14);
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(27, 94, 63, 0.08), rgba(46, 204, 113, 0.05));
        }
        .order-line-toolbar-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #0f3f28;
            font-size: 0.88rem;
            font-weight: 800;
        }
        .order-line-toolbar-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-add-line {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            min-height: 38px;
            padding: 0.45rem 0.8rem;
            border-radius: 10px;
            border: 1px solid rgba(27, 94, 63, 0.22);
            color: #0f3f28;
            background: #ffffff;
            font-size: 0.86rem;
            font-weight: 800;
            box-shadow: 0 6px 18px rgba(15, 63, 40, 0.08);
        }
        .btn-add-line:hover {
            color: #ffffff;
            border-color: #1b5e3f;
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            transform: translateY(-1px);
        }
        .btn-add-line.product {
            border-color: rgba(37, 99, 235, 0.22);
            color: #1d4ed8;
        }
        .btn-add-line.product:hover {
            border-color: #2563eb;
            color: #ffffff;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }
        .preferred-supplier-callout {
            display: none;
            margin-top: 0.65rem;
            padding: 0.75rem 0.9rem;
            border: 1px solid rgba(22, 163, 74, 0.28);
            border-left: 5px solid #16a34a;
            border-radius: 10px;
            background: linear-gradient(135deg, #ecfdf5 0%, #dcfce7 100%);
            color: #14532d;
            font-size: 0.86rem;
            font-weight: 750;
            box-shadow: 0 8px 22px rgba(22, 163, 74, 0.12);
        }
        .preferred-supplier-callout.show {
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }
        .preferred-supplier-callout i {
            font-size: 1.15rem;
        }
        .order-supplier-name {
            display: inline-block;
            width: 100%;
            color: #1e293b;
            font-size: 0.78rem;
            font-weight: 650;
            line-height: 1.3;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        .order-summary-inline {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.35rem 0.45rem;
            width: 100%;
        }
        .order-summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            flex: 0 0 auto;
            max-width: 100%;
            padding: 0.32rem 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #f8fafc;
        }
        .order-summary-name {
            font-size: 0.76rem;
            font-weight: 650;
            line-height: 1.2;
            color: #1e293b;
            white-space: normal;
            overflow-wrap: break-word;
        }
        .order-summary-meta {
            flex-shrink: 0;
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 700;
        }
        .view-order-details {
            font-size: 0.76rem;
            font-weight: 700;
        }
        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .order-detail-chip {
            padding: 0.85rem;
            border: 1px solid rgba(27, 94, 63, 0.14);
            border-radius: 12px;
            background: #f8fafc;
        }
        .order-detail-chip span {
            display: block;
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 800;
        }
        .order-detail-chip strong {
            display: block;
            margin-top: 0.2rem;
            color: #0f172a;
            font-size: 0.95rem;
            overflow-wrap: anywhere;
        }
        .order-detail-table {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .order-detail-total {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            color: #0f172a;
            background: #f8fafc;
            font-weight: 800;
        }
        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 576px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Remove item row modal styling */
        #removeMedicineRowModal .modal-content { border-radius: 20px; overflow: hidden; }
        #removeMedicineRowModal .modal-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 4px 20px rgba(239,68,68,0.3);
        }

        /* ===== DARK MODE ===== */
        body.dark-mode { background: #0f1419 !important; color: #f8fafc !important; }
        body.dark-mode .main-content { background: transparent !important; }
        body.dark-mode .card { background: #111827 !important; box-shadow: 0 12px 36px rgba(0,0,0,0.35) !important; }
        body.dark-mode .filters-section { background: #111827 !important; border-color: #1f2937 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.45) !important; }
        body.dark-mode .table-container { background: #0f172a !important; border-color: #1f2937 !important; }
        body.dark-mode .table td { color: #e2e8f0 !important; border-color: #334155 !important; }
        body.dark-mode .table tbody tr:hover { background: #111827 !important; }
        body.dark-mode .form-control,
        body.dark-mode .form-select { background: #0f172a !important; color: #e2e8f0 !important; border-color: #334155 !important; }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus { border-color: #2ecc71 !important; box-shadow: 0 0 0 0.2rem rgba(46,204,113,0.15) !important; }
        body.dark-mode #orders-count { background: #0f172a !important; color: #e2e8f0 !important; }
        body.dark-mode select option { color: #e2e8f0 !important; background-color: #0f172a !important; }
        body.dark-mode #removeMedicineRowModal .modal-body,
        body.dark-mode #removeMedicineRowModal .modal-footer { background: #111827 !important; color: #e2e8f0 !important; }
        body.dark-mode .system-toast {
            border-color: rgba(46, 204, 113, 0.2);
            background: #111827;
            color: #e2e8f0;
            box-shadow: 0 18px 44px rgba(0, 0, 0, 0.35);
        }
        body.dark-mode .system-toast-title { color: #f8fafc; }
        body.dark-mode .system-toast-message { color: #cbd5e1; }
        body.dark-mode .order-line-toolbar {
            border-color: rgba(46, 204, 113, 0.16);
            background: rgba(46, 204, 113, 0.1);
        }
        body.dark-mode .order-line-toolbar-label { color: #6ee7b7; }
        body.dark-mode .btn-add-line {
            border-color: rgba(46, 204, 113, 0.2);
            color: #6ee7b7;
            background: #0f172a;
        }
        body.dark-mode .preferred-supplier-callout {
            border-color: rgba(46, 204, 113, 0.24);
            background: rgba(22, 163, 74, 0.14);
            color: #bbf7d0;
        }
        body.dark-mode .order-summary-pill,
        body.dark-mode .order-detail-chip,
        body.dark-mode .order-detail-total {
            border-color: #334155;
            background: #0f172a;
        }
        body.dark-mode .order-supplier-name,
        body.dark-mode .order-summary-name,
        body.dark-mode .order-detail-chip strong,
        body.dark-mode .order-detail-total {
            color: #f8fafc;
        }
        body.dark-mode .order-summary-meta,
        body.dark-mode .order-detail-chip span {
            color: #94a3b8;
        }
        body.dark-mode .order-detail-table {
            border-color: #334155;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content admin-table-page">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <h2><i class="bi bi-box-seam-fill me-2"></i> Orders Management</h2>
            <button type="button" class="btn btn-light action-btn" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                <i class="bi bi-plus-circle me-1"></i> New Order
            </button>
        </div>

        <div class="filters-section">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Search Orders</label>
                    <input type="text" id="filter-search" class="form-control" placeholder="Supplier, medicine, ID...">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter by Status</label>
                    <select id="filter-status" class="form-select">
                        <option value="">All Orders</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="declined">Declined</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="shipped">Shipped</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="fulfilled">Fulfilled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Expected By</label>
                    <input type="date" id="filter-expected" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Total Orders</label>
                    <div id="orders-count" class="form-control bg-light text-center fw-bold">Loading...</div>
                </div>
            </div>
        </div>

        <div class="card admin-table-card">
            <div class="card-body">
                <div class="table-container admin-table-scroll">
                    <table class="table table-hover mb-0 orders-table" data-admin-no-pagination="true">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Supplier</th>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Total (Supplier Price)</th>
                                <th>Payment</th>
                                <th>Order Status</th>
                                <th>Delivery Status</th>
                                <th>Date</th>
                                <th>Expected</th>
                                <th>Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orders-table">
                            <?php if (!$conn): ?>
                                <tr><td colspan="12" class="text-center py-5 text-warning fs-5">
                                    Database connection failed
                                </td></tr>
                            <?php else: ?>
                                <tr><td colspan="12" class="text-center py-5 text-muted">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <p class="mt-3">Loading orders...</p>
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="orders-page-info" class="text-muted">Loading...</small>
                    <nav id="orders-pagination" aria-label="Orders pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         ADD ORDER MODAL
    ================================================================ -->
    <div class="modal fade" id="addOrderModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-cart-plus me-2"></i>New Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="add-order-form">
                    <div class="modal-body">
                        <?php if (!$conn): ?>
                            <div class="alert alert-warning">Cannot create orders without DB connection.</div>
                        <?php else: ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                                    <select id="order-supplier" class="form-select" name="supplier_id" required disabled>
                                        <option value="">Select Supplier First</option>
                                    </select>
                                    <div id="preferred-supplier-callout" class="preferred-supplier-callout">
                                        <i class="bi bi-patch-check-fill"></i>
                                        <span>This is a preferred supplier. Auto-orders will prioritize them when they have the selected item in stock.</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Expected Delivery <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="expected-delivery" name="expected_delivery" min="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mode of Payment <span class="text-danger">*</span></label>
                                    <select class="form-select" id="payment-method" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <div class="col-md-6 d-none" id="payment-reference-wrapper">
                                    <label class="form-label" id="payment-reference-label">Reference / Account Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="payment-reference" name="payment_reference" placeholder="e.g. GCash number or account number">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any special instructions..."></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Items <span class="text-danger">*</span></label>
                                    <p class="text-muted small">Select from supplier's available stock only.</p>
                                    <div id="medicine-container"></div>
                                    <div class="order-line-toolbar">
                                        <div class="order-line-toolbar-label">
                                            <i class="bi bi-plus-circle"></i>
                                            <span>Add another order line</span>
                                        </div>
                                        <div class="order-line-toolbar-actions">
                                            <button type="button" id="add-medicine-row-btn" class="btn-add-line">
                                                <i class="bi bi-capsule"></i>
                                                <span>Medicine</span>
                                            </button>
                                            <button type="button" id="add-product-row-btn" class="btn-add-line product">
                                                <i class="bi bi-box-seam"></i>
                                                <span>Other Product</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Total Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">₱</span>
                                                <input type="number" class="form-control" id="total-cost" readonly value="0.00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <?php if ($conn): ?>
                            <button type="submit" class="btn btn-primary">Place Order</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================================
         REMOVE ITEM ROW MODAL
         Placed last — highest z-index so it renders above addOrderModal.
    ================================================================ -->
    <div class="modal fade" id="removeMedicineRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);">
                    <h5 class="modal-title">
                        <i class="bi bi-trash me-2"></i> Remove Item
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Are you sure you want to remove this item row?</p>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" style="border-radius:10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Any details entered for this row will be lost.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirm-remove-medicine-btn">
                        <i class="bi bi-trash me-1"></i> Remove
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
         EDIT ORDER MODAL
    ================================================================ -->
    <div class="modal fade" id="editOrderModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Order #<span id="edit-order-id"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="edit-order-form">
                    <div class="modal-body">
                        <input type="hidden" id="edit-order-id-input">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Expected Delivery <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit-expected-delivery" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mode of Payment <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-payment-method" required>
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-none" id="edit-payment-reference-wrapper">
                                <label class="form-label" id="edit-payment-reference-label">Reference / Account Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit-payment-reference" placeholder="e.g. GCash number or account number">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="edit-notes" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Items</label>
                                <p class="text-muted small">Adjust quantities or remove a line. Prices reflect the supplier's current pricing.</p>
                                <div id="edit-medicine-container"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Total Amount</label>
                                <div class="input-group" style="max-width: 250px;">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="edit-total-cost" readonly value="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ================================================================
         CANCEL ORDER CONFIRMATION MODAL
    ================================================================ -->
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
            <div class="modal-content">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i> Cancel Order</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Are you sure you want to cancel Order #<span id="cancel-order-id"></span>?</p>
                    <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" style="border-radius:10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>This can only be done before the supplier responds, and cannot be undone.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Order</button>
                    <button type="button" class="btn btn-danger" id="confirm-cancel-order-btn">
                        <i class="bi bi-x-circle me-1"></i> Cancel Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirmReceivedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:460px;">
            <div class="modal-content">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#1b5e3f 0%,#0f3f28 100%);">
                    <h5 class="modal-title"><i class="bi bi-check2-circle me-2"></i> Confirm Received</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-3">Mark Order #<span id="received-order-id"></span> as received?</p>
                    <div class="alert alert-info d-flex align-items-center gap-2 mb-0" style="border-radius:10px;">
                        <i class="bi bi-info-circle-fill"></i>
                        <span>This will update inventory and record the related transactions.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Pending</button>
                    <button type="button" class="btn btn-primary" id="confirm-received-order-btn">
                        <i class="bi bi-check2-circle me-1"></i> Confirm Received
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Order #<span id="details-order-id"></span> Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="order-details-grid" id="order-details-summary"></div>
                    <div class="order-detail-table">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Qty</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="order-details-items"></tbody>
                            </table>
                        </div>
                        <div class="order-detail-total">
                            <span>Total</span>
                            <strong id="order-details-total">₱0.00</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/orders.js?v=<?= filemtime(__DIR__ . '/assets/js/orders.js') ?>"></script>
</body>
</html>
<?php if ($conn) $conn->close(); ?>
