<?php
session_start();
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/includes/supplier_auth.php";
requireSupplierPage($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Supplier Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");
        body { font-family: "Inter", sans-serif; background: #f5f7fa; overflow-x: hidden; }
        .main-content { margin-left: 280px; padding: 2rem; transition: margin-left 0.3s ease; max-height: 100vh; overflow-y: auto; }
        @media (max-width: 991.98px) { .main-content { margin-left: 0; padding: 5rem 1rem 1rem; } }
        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); background: #fff; border: none; }
        .table-container { max-height: calc(100vh - 340px); overflow-y: auto; overflow-x: auto; }
        .table { border-radius: 12px; margin-bottom: 0; white-space: nowrap; width: 100%; }
        .table th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; padding: 0.85rem 1rem; border: none; position: sticky; top: 0; z-index: 10; white-space: nowrap; }
        .table td { vertical-align: middle; color: #2d3748; padding: 0.8rem 1rem; border-color: #e2e8f0; white-space: nowrap; }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; }
        .action-btn { padding: 0.5rem 1rem; margin: 0 0.25rem; border-radius: 6px; font-weight: 500; }
        .btn-accept { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; }
        .btn-decline { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; }
        .btn-accept:hover, .btn-decline:hover { opacity: 0.9; transform: translateY(-1px); }
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .page-header { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 1.25rem; box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); }
        .page-header h2 { color: white; margin: 0; font-weight: 700; font-size: 1.8rem; }
        .page-header .btn { background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.3); color: white; backdrop-filter: blur(10px); transition: all 0.3s ease; }
        .page-header .btn:hover { background: rgba(255, 255, 255, 0.3); transform: translateY(-2px); }
        .status-badge { padding: 0.3rem 0.7rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.3px; display: inline-block; white-space: nowrap; }
        .s-pending { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
        .s-accepted { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
        .s-declined { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
        .s-shipped { background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; }
        .s-out_for_delivery { background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; }
        .s-delivered { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; }
        .s-fulfilled { background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; }
        .delivery-select { border: 2px solid #1b5e3f; border-radius: 8px; padding: 0.35rem 0.7rem; font-size: 0.85rem; font-weight: 600; color: #1b5e3f; background: #f0fdf4; cursor: pointer; min-width: 170px; }
        .delivery-select:focus { outline: none; box-shadow: 0 0 0 3px rgba(27,94,63,0.2); }
        .pay-badge { background: #e0f2fe; color: #0369a1; padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.78rem; font-weight: 600; }
        .orders-count { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); border-radius: 10px; padding: 0.5rem 1rem; font-size: 0.9rem; font-weight: 600; }
        .orders-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php include "includes/nav.php"; ?>

    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2><i class="bi bi-inbox-fill me-2"></i> Orders Management</h2>
                <p class="mb-0 mt-1" style="opacity:0.8;font-size:0.9rem;">Accept incoming orders and track delivery progress</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="orders-count"><i class="bi bi-box-seam me-1"></i><span id="orders-count-num">—</span> orders</span>
                <button class="btn btn-light action-btn" onclick="loadOrders()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
        </div>

        <div class="orders-toolbar">
            <div class="dropdown">
                <button class="btn btn-outline-success dropdown-toggle fw-semibold" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-funnel me-1"></i> <span id="filter-status-label">All Orders</span>
                </button>
                <ul class="dropdown-menu shadow" aria-labelledby="filterDropdown">
                    <li><a class="dropdown-item" href="#" data-order-filter="">All Orders</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="active">Active Orders</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="pending">Pending</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="accepted">Accepted</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="shipped">Shipped</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="out_for_delivery">Out for Delivery</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="delivered">Delivered (Awaiting Admin)</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="fulfilled">Completed</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="declined">Declined</a></li>
                    <li><a class="dropdown-item" href="#" data-order-filter="cancelled">Cancelled by Pharmacy</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-order-columns">
                <i class="bi bi-arrows-angle-expand me-1"></i> Show All Columns
            </button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive table-container sup-table-wrap" id="orders-table-wrap">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Medicine</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Progress</th>
                                <th>Placed On</th>
                                <th class="col-detail">Unit Price</th>
                                <th class="col-detail">Payment</th>
                                <th class="col-detail">Expected By</th>
                                <th class="col-detail">Completed On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="orders-table">
                            <tr><td colspan="11" class="text-center py-4">
                                <div class="spinner-border text-success" role="status"></div>
                                <p class="mt-2 text-muted">Loading orders...</p>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="sup-table-footer">
                    <span class="sup-pagination-info" id="orders-pagination-info">Page 1 of 1 · 0 items</span>
                    <nav aria-label="Orders pagination">
                        <ul class="pagination justify-content-center mb-0" id="orders-pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container"></div>

    <div class="modal fade" id="order-action-modal" tabindex="-1" aria-labelledby="order-action-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-2">
                    <h5 class="modal-title fw-bold" id="order-action-modal-title">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-secondary" id="order-action-modal-body">Are you sure?</p>
                </div>
                <div class="modal-footer border-0 pt-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="order-action-confirm-btn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_orders.js"></script>
</body>
</html>
