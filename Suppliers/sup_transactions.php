<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/includes/supplier_auth.php';
requireSupplierPage($conn);

$redirectFromAccept = isset($_GET['from_accept']) && $_GET['from_accept'] === '1';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Transactions - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); background: #fff; border: none; }        .table-container { max-height: calc(100vh - 320px); overflow-y: auto; overflow-x: auto; }
        .table-container::-webkit-scrollbar { width: 10px; height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .table { border-radius: 12px; overflow: hidden; margin-bottom: 0; }
        .table th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; padding: 1rem; border: none; position: sticky; top: 0; z-index: 10; }
        .table td { vertical-align: middle; color: #2d3748; padding: 1rem; border-bottom: 1px solid #e2e8f0; }
        .table tbody tr:hover { background: #f8fafc; }
        .status-badge { padding: 0.375rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .status-add { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .status-remove { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .page-header { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); display: flex; justify-content: space-between; align-items: center; }
        .page-header-content { flex: 1; }
        .info-badge { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 0.75rem 1.25rem; border-radius: 10px; font-size: 0.95rem; display: inline-flex; align-items: center; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .info-badge i { margin-right: 0.5rem; font-size: 1.2rem; }
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .refresh-btn { background: rgba(255, 255, 255, 0.2); border: 2px solid rgba(255, 255, 255, 0.5); color: white; padding: 0.5rem 1rem; border-radius: 10px; font-weight: 600; transition: all 0.3s; }
        .refresh-btn:hover { background: rgba(255, 255, 255, 0.3); border-color: white; transform: scale(1.05); }
        .refresh-btn i { margin-right: 0.5rem; }
        .success-alert { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">        <?php if ($redirectFromAccept): ?>
            <div class="success-alert">
                <i class="bi bi-check-circle me-2"></i>
                Order accepted successfully! Now proceed to fulfill sales in the Sales tab.
            </div>
        <?php endif; ?>
        <div class="page-header">
            <div class="page-header-content">
                <h1 class="mb-0"><i class="bi bi-receipt me-3"></i>Sales Transactions</h1>
                <p class="mb-0 opacity-75">View sales transactions from completed orders and walk-ins. Stock deductions are recorded here.</p>
            </div>
            <button class="btn refresh-btn" id="refresh-transactions-btn">
                <i class="bi bi-arrow-clockwise"></i>
                Refresh
            </button>
        </div>

        <div class="info-badge">
            <i class="bi bi-info-circle-fill"></i>
            These transactions show sold items. View-only access. Auto-refreshes every 10 seconds.
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive table-container">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Items</th>
                                <th>Action</th>
                                <th>Quantity Sold</th>
                                <th>Total Sold</th>
                                <th>Cashier</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody id="transaction-table">
                            <tr id="table-loading" class="text-center py-5">
                                <td colspan="7">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Loading sales transactions...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="sup-table-footer">
                    <span class="sup-pagination-info" id="transactions-pagination-info">Page 1 of 1 · 0 items</span>
                    <nav aria-label="Transactions pagination">
                        <ul class="pagination justify-content-center mb-0" id="transactions-pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_transactions.js"></script>
</body>
</html>
<?php if ($conn) $conn->close(); ?>
