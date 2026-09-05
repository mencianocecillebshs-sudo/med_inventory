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
    <title>Reports - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f5f7fa; 
            overflow: hidden;
            height: 100%;
            margin: 0;

        }
        .main-content { 
            margin-left: 250px; 
            padding: 2rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        @media (max-width: 768px) { 
            .main-content { 
                margin-left: 0; 
                padding: 1rem; 
            } 
        }
        
        .page-header { 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: white; 
            padding: 1.5rem 2rem;
            border-radius: 15px; 
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2);
            flex-shrink: 0;
        }
        .page-header h2 { 
            color: white; 
            margin: 0; 
            font-size: 1.75rem;
        }
        .page-header p { 
            margin: 0.5rem 0 0; 
            opacity: 0.9; 
            font-size: 0.9rem;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            background: #fff;
            border: none;
            flex: 1 1 auto;
            height: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex: 1 1 auto;
            height: 0;
            min-height: 0;
        }

        .admin-table-card > .card-body {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            gap: 0;
            height: auto;
            flex: 1 1 auto;
        }

        .admin-table-card > .card-body.medicine-report-view {
            grid-template-rows: auto minmax(160px, 36vh) auto;
        }

        .medicine-report-view .table-container {
            border-radius: 15px;
            border-width: 1px;
            overflow-x: auto;
        }

        .medicine-report-view .table {
            font-size: 1rem;
        }

        .medicine-report-view .table th,
        .medicine-report-view .table td {
            padding: 0.75rem 0.85rem;
        }
        
        .filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid #1b5e3f;
            flex-shrink: 0;
            position: relative;
            overflow: visible;
        }

        .report-filter-grid { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(260px, 1.4fr) minmax(280px, 2fr); gap: 1rem; align-items: end; }
        .report-filter-grid .form-label { margin-bottom: 0.4rem; }
        .report-filter-grid .form-label i { color: #1b5e3f; }
        .report-filter-grid .input-group .form-control { min-width: 0; }
        .medicine-search-wrap .search-input-group { position: relative; }
        .medicine-search-wrap .search-input-group i.bi-search { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; z-index: 2; }
        .medicine-search-wrap .search-input-group .form-control { padding-left: 2.25rem; padding-right: 2.25rem; }
        .medicine-search-wrap .search-clear-btn { position: absolute; right: 0.4rem; top: 50%; transform: translateY(-50%); border: none; background: transparent; color: #94a3b8; padding: 0.25rem 0.4rem; line-height: 1; display: none; cursor: pointer; z-index: 2; }
        .medicine-search-wrap .search-clear-btn:hover { color: #64748b; }
        
        .form-control, .form-select { 
            border-radius: 10px;
            border: 2px solid #e2e8f0; 
            transition: all 0.3s ease; 
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus { 
            border-color: #1b5e3f; 
            box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.15);
        }
        .medicine-search-wrap { position: relative; }
        .medicine-search-suggestions {
            position: absolute;
            top: calc(100% + 0.35rem);
            left: 0;
            right: 0;
            z-index: 30;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
            display: none;
            max-height: 220px;
            overflow-y: auto;
        }
        .medicine-search-suggestions.show { display: block; }
        .medicine-search-suggestion {
            width: 100%;
            border: 0;
            background: transparent;
            text-align: left;
            padding: 0.75rem 0.9rem;
            font-size: 0.95rem;
            color: #1f2937;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .medicine-search-suggestion:hover,
        .medicine-search-suggestion:focus {
            background: #f1f5f9;
            outline: none;
        }
        .medicine-search-suggestion small {
            color: #64748b;
        }
        
        .form-label { 
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        .form-label i {
            margin-right: 0.5rem;
            color: #1b5e3f;
        }
        
        .table-container {
            flex: none;
            height: 100%;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        .table-container::-webkit-scrollbar { width: 10px; height: 10px; }
        .table-container::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-radius: 10px; 
        }
        .table-container::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        
        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }
        .table th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #fff;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
            border: none;
            padding: 0.6rem 0.75rem;
        }
        .table td {
            vertical-align: middle;
            color: #2d3748;
            padding: 0.6rem 0.75rem;
            border-color: #e2e8f0;
        }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); }
        @media (max-width: 768px) {
            .admin-table-card > .card-body {
                grid-template-rows: auto auto minmax(0, 1fr) auto;
            }

            .admin-table-card > .card-body.medicine-report-view {
                grid-template-rows: auto auto minmax(140px, 30vh) auto;
            }

            .table-container {
                height: 100%;
                min-height: 160px;
            }

        }
        
        .action-btn { 
            padding: 0.5rem 1rem; 
            font-size: 0.9rem; 
            border-radius: 8px; 
            transition: all 0.3s ease; 
            font-weight: 500;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-outline-secondary {
            border: 2px solid #64748b;
            color: #64748b;
            background: transparent;
        }
        .btn-outline-secondary:hover {
            background: #64748b;
            color: white;
        }

        .download-dropdown .dropdown-toggle {
            border: 2px solid #1b5e3f;
            color: #1b5e3f;
            background: transparent;
        }
        .download-dropdown .dropdown-toggle:hover,
        .download-dropdown .dropdown-toggle:focus,
        .download-dropdown.show .dropdown-toggle {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: white;
        }
        .download-dropdown .dropdown-menu {
            min-width: 190px;
            padding: 0.4rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        .download-dropdown .dropdown-item {
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .download-dropdown .dropdown-item:hover,
        .download-dropdown .dropdown-item:focus {
            background: #f0fdf4;
            color: #0f3f28;
        }
        
        .filter-indicator {
            background: #eff6ff;
            color: #1e3a8a;
            padding: 0.6rem 0.9rem;
            border-radius: 9px;
            font-size: 0.82rem;
            margin-bottom: 0.75rem;
            display: flex; 
            align-items: center;
            border: 1px solid #93c5fd;
            font-weight: 600;
        }

        .report-pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1rem;
            min-height: 3rem;
        }

        .report-pagination-bar .pagination {
            margin-bottom: 0;
        }

        .report-pagination-info {
            color: #64748b;
            font-size: 0.82rem;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .report-pagination-bar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
        
        .report-actions {
            margin: 0;
        }

        .report-toolbar {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            gap: 0.65rem;
        }

        .report-toolbar .report-actions,
        .report-toolbar .download-dropdown,
        .report-toolbar .download-dropdown .dropdown-toggle {
            flex: 1 1 0;
        }

        .report-toolbar .download-dropdown .dropdown-toggle {
            width: 100%;
        }

        .report-toolbar > * { flex: 1 1 0; }

        .medicine-search-wrap {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            height: 100%;
        }

        .medicine-search-wrap .form-label,
        .col-md-4 .form-label {
            margin-bottom: 0.35rem;
            display: block;
        }

        .medicine-search-wrap .input-group {
            display: flex;
            align-items: stretch;
            width: 100%;
            gap: 0.35rem;
        }

        .medicine-search-wrap .input-group .form-control {
            flex: 1 1 auto;
            min-width: 0;
            height: 2.25rem;
            align-self: stretch;
        }

        .medicine-search-wrap .input-group .btn {
            flex-shrink: 0;
            padding: 0.4rem 0.75rem;
            height: 2.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .medicine-search-wrap .status-row {
            min-height: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-top: 0.1rem;
        }

        .medicine-search-wrap .status-row small {
            font-size: 0.72rem;
            line-height: 1.1;
        }
        
        .no-print { 
            @media print { 
                display: none !important; 
            } 
        }
        
        .status-badge { 
            padding: 0.4rem 0.9rem; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }
        .status-remove { 
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
            color: white; 
        }
        
        @media print { 
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } 
            .table { font-size: 0.85rem; } 
            .page-header, .filter-section, .report-actions { display: none; } 
        }

        /* Keep the report workspace visible while allowing the table to scroll. */
        body { overflow-x: hidden; overflow-y: auto; }
        .main-content { height: auto; max-height: 100vh; overflow: visible; }
        .admin-table-card { height: auto; min-height: 0; overflow: visible; }
        .admin-table-card > .card-body { height: auto; min-height: 0; overflow: visible; }
        .admin-table-card .table-container { height: auto; max-height: calc(100vh - 285px); min-height: 180px; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-file-text me-2"></i> Reports</h2>
        </div>

        <div class="card admin-table-card">
            <div class="card-body">
                <div class="filter-section">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="bi bi-file-earmark-text"></i> Report Type
                            </label>
                            <select class="form-select" id="report-type">
                                <option value="inventory">Inventory Report</option>
                                <option value="transactions">Sales Transactions Report</option>
                            </select>
                        </div>
                        <div class="col-md-3 medicine-search-wrap">
                            <label class="form-label">
                                <i class="bi bi-funnel"></i> Filter by Item
                            </label>
                            <div class="search-input-group">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" id="medicine-search" placeholder="Search item..." autocomplete="off" aria-label="Search medicine">
                                <button type="button" class="search-clear-btn" id="clear-report-search" title="Clear">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <div id="medicine-search-suggestions" class="medicine-search-suggestions" role="listbox" aria-label="Medicine suggestions"></div>
                            <input type="hidden" id="medicine-filter" value="">
                        </div>
                        <div class="col-md-6 d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary action-btn no-print" id="print-report-btn" type="button" onclick="printReport()">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                            <div class="btn-group download-dropdown">
                                <button type="button" class="btn action-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-download me-1"></i> Download
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item pdf" href="#" onclick="generatePDF(); return false;"><i class="bi bi-file-earmark-pdf"></i> PDF (.pdf)</a></li>
                                    <li><a class="dropdown-item excel" href="#" onclick="generateExcel(); return false;"><i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)</a></li>
                                    <li><a class="dropdown-item csv" href="#" onclick="generateCSV(); return false;"><i class="bi bi-filetype-csv"></i> CSV (.csv)</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-container admin-table-scroll">
                    <table class="table table-hover mb-0">
                        <thead id="report-table-header"></thead>
                        <tbody id="report-table-body"></tbody>
                    </table>
                </div>

                <div class="report-pagination-bar" aria-label="Report pagination controls">
                    <span class="report-pagination-info" id="pagination-info">Page 1 of 1 · 0 items</span>
                    <nav aria-label="Report pages">
                        <ul class="pagination pagination-sm justify-content-center mb-0" id="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="reportPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header text-white bg-primary">
                    <h5 class="modal-title" id="preview-title">Report Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead id="preview-table-header"></thead>
                            <tbody id="preview-table-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_reports.js"></script>
</body>
</html>

