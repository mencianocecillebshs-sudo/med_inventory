<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>Transactions - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; overflow: hidden; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }
        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); background: #fff; border: none; }
        .table-container { overflow-y: auto; overflow-x: auto; }
        .table-container::-webkit-scrollbar { width: 10px; height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .table { border-radius: 12px; overflow: hidden; margin-bottom: 0; }
        .table th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; padding: 1rem; border: none; position: sticky; top: 0; z-index: 10; }
        .table td { vertical-align: middle; color: #2d3748; padding: 1rem; border-color: #e2e8f0; }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); }
        .status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .action-add { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); color: #064e3b; }
        .action-remove { background: linear-gradient(135deg, #f87171 0%, #ef4444 100%); color: #7f1d1d; }
        .action-update { background: linear-gradient(135deg, #93c5fd 0%, #3b82f6 100%); color: #1e3a5f; }
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .card-body { padding: 0; }
        .page-header { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); }
        .page-header h2 { color: white; margin: 0; }
        .loading-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); display: none; align-items: center; justify-content: center; z-index: 100; border-radius: 15px; }
        .loading-overlay.show { display: flex; }
        .table-wrapper {
            position: relative;
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #toggle-sidebar-mobile { position: fixed; top: 1rem; left: 1rem; z-index: 1100; background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; border-radius: 50%; width: 45px; height: 45px; border: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        .filters-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            border-left: 5px solid #1b5e3f;
        }
        .filters-section .form-control,
        .filters-section .form-select { border-radius: 10px; border: 2px solid #e2e8f0; font-size: 0.9rem; padding: 0.5rem 0.9rem; }
        .filters-section .form-control:focus,
        .filters-section .form-select:focus { border-color: #1b5e3f; box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.12); }
        select option { color: #000 !important; background: #fff !important; }

        /* Action buttons - exact match to inventory page */
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 0.125rem;
            border: none;
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(27, 94, 63, 0.3); }
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-view-txn {
            color: #1b5e3f;
            border: 1px solid rgba(27, 94, 63, 0.45);
            background: transparent;
        }
        .btn-view-txn:hover,
        .btn-view-txn:focus {
            color: #ffffff;
            border-color: #1b5e3f;
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        }

        /* Detail modal */
        .txn-modal .modal-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #fff;
            border-radius: 14px 14px 0 0;
            padding: 1.25rem 1.5rem;
        }
        .txn-modal .modal-header .btn-close { filter: invert(1) brightness(2); }
        .txn-modal .modal-content { border-radius: 15px; border: none; box-shadow: 0 16px 48px rgba(0,0,0,0.18); }
        .txn-modal .modal-title { font-weight: 700; font-size: 1.1rem; }
        .txn-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.85rem 1.25rem;
        }
        @media (max-width: 576px) { .txn-detail-grid { grid-template-columns: 1fr; } }
        .txn-detail-item { display: flex; flex-direction: column; gap: 0.2rem; }
        .txn-detail-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #718096;
        }
        .txn-detail-value {
            font-size: 0.95rem;
            color: #1a202c;
            font-weight: 500;
            word-break: break-word;
        }
        .txn-detail-value.empty { color: #a0aec0; font-style: italic; }
        .txn-detail-full {
            grid-column: 1 / -1;
        }
        .txn-divider {
            grid-column: 1 / -1;
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 0.25rem 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>
    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-arrow-left-right me-2"></i> Transaction History</h2>
        </div>

        <div class="filters-section">
            <div class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Search Transactions</label>
                    <input type="text" class="form-control" id="search" placeholder="Search item, user, pharmacist, purchase #, notes...">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold d-block">&nbsp;</label>
                    <button type="button" class="btn btn-primary action-btn" id="apply-transaction-filter">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>

        <div class="card flex-grow-1 overflow-hidden admin-table-card">
            <div class="card-body p-0">
                <div class="table-wrapper">
                    <div class="loading-overlay" id="table-loading">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                    <div class="table-container admin-table-scroll">
                        <table class="table table-hover table-striped" data-admin-no-pagination="true">
                            <thead>
                                <tr>
                                    <th id="item-page-header">Item</th>
                                    <th>Action</th>
                                    <th>Quantity</th>
                                    <th>User</th>
                                    <th>Pharmacist</th>
                                    <th>Purchase</th>
                                    <th>Notes</th>
                                    <th>Cost</th>
                                    <th>Date &amp; Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="transaction-table">
                                <tr id="no-data-row" class="d-none">
                                    <td colspan="10" class="text-center text-muted py-5">No transactions found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-2">
                    <small id="transaction-page-info" class="text-muted"></small>
                    <nav class="pagination-container" id="transaction-pagination" aria-label="Transaction pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div class="modal fade txn-modal" id="txnDetailModal" tabindex="-1" aria-labelledby="txnDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="txnDetailModalLabel">
                        <i class="bi bi-receipt me-2"></i>Transaction Detail
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="txn-detail-grid" id="txn-detail-content">
                        <!-- Populated by JS -->
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/transactions.js?v=<?= filemtime(__DIR__ . '/assets/js/transactions.js') ?>"></script>
</body>
</html>
