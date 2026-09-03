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
    <title>Reports - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.32/jspdf.plugin.autotable.min.js"></script>
    <style>
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
        .page-header h2 { color: white; margin: 0; font-size: 1.75rem; }
        
        .card { 
            border-radius: 15px; 
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); 
            background: #fff; 
            border: none;
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex: 1;
        }
        
        .filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid #1b5e3f;
            flex-shrink: 0;
            /* needed so the suggestion dropdown below the search box
               isn't clipped by overflow on this section */
            position: relative;
            overflow: visible;
        }
        
        .form-control, .form-select { 
            border-radius: 10px; 
            border: 2px solid #e2e8f0; 
            transition: all 0.3s ease; 
            padding: 0.5rem 0.75rem;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus { 
            border-color: #1b5e3f; 
            box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.15); 
        }
        
        .form-label { 
            font-weight: 600; 
            color: #1e293b; 
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
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
        
        .btn-outline-primary {
            border: 2px solid #1b5e3f;
            color: #1b5e3f;
            background: transparent;
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: white;
        }

        /* Download split-button dropdown */
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
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            padding: 0.4rem;
            min-width: 190px;
        }
        .download-dropdown .dropdown-item {
            border-radius: 8px;
            padding: 0.55rem 0.75rem;
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
        .download-dropdown .dropdown-item i { font-size: 1.05rem; }
        .download-dropdown .dropdown-item.pdf i { color: #dc2626; }
        .download-dropdown .dropdown-item.excel i { color: #10b981; }
        .download-dropdown .dropdown-item.csv i { color: #06b6d4; }

        /* ===== Medicine search bar (replaces the old <select>) ===== */
        .medicine-search-wrap {
            position: relative;
        }
        .medicine-search-wrap .search-input-group {
            position: relative;
        }
        .medicine-search-wrap .search-input-group i.bi-search {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }
        .medicine-search-wrap .search-input-group .form-control {
            padding-left: 2.25rem;
            padding-right: 2.25rem;
        }
        .medicine-search-wrap .search-clear-btn {
            position: absolute;
            right: 0.4rem;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: #94a3b8;
            padding: 0.25rem 0.4rem;
            line-height: 1;
            display: none;
            cursor: pointer;
        }
        .medicine-search-wrap .search-clear-btn:hover {
            color: #64748b;
        }
        
        .table-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        
        .table-container::-webkit-scrollbar {
            width: 10px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-radius: 10px;
        }
        
        .table { 
            margin-bottom: 0; 
        }
        .table-container .table th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); 
            color: #fff; 
            font-weight: 600; 
            padding: 1rem; 
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .table td { 
            vertical-align: middle; 
            color: #2d3748; 
            padding: 1rem; 
            border-color: #e2e8f0; 
        }
        .table tbody tr { 
            transition: all 0.2s ease; 
        }
        .table tbody tr:hover { 
            background: #f7fafc; 
            transform: translateX(2px); 
        }
        
        .filter-indicator { 
            font-size: 0.875em; 
            color: #64748b; 
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #1b5e3f;
            margin-bottom: 1rem;
            flex-shrink: 0;
        }
        
        .low-stock { 
            color: #dc3545; 
            font-weight: bold; 
        }
        .expiry-soon { 
            color: #ffc107; 
            font-weight: bold; 
        }
        
        #toggle-sidebar-mobile {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #fff;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .modal-content { 
            border-radius: 20px; 
            border: none; 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); 
        }
        .modal-header { 
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-bottom: none; 
            padding: 1.5rem 2rem; 
            border-radius: 20px 20px 0 0; 
            flex-shrink: 0;
        }

        /* Preview modal: constrain height and scroll the table area,
           independent of the page's own scroll/sticky context. */
        #reportPreviewModal .modal-dialog {
            max-width: 90%;
            max-height: 85vh;
            margin: 4rem auto;
        }
        #reportPreviewModal .modal-content {
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }
        #reportPreviewModal .modal-body {
            padding: 0;
            overflow: hidden;
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        #reportPreviewModal .preview-table-scroll {
            overflow-y: auto;
            overflow-x: auto;
            flex: 1 1 auto;
            min-height: 0;
        }
        #reportPreviewModal .preview-table-scroll::-webkit-scrollbar { width: 10px; height: 10px; }
        #reportPreviewModal .preview-table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        #reportPreviewModal .preview-table-scroll::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-radius: 10px;
        }
        #reportPreviewModal .table {
            margin-bottom: 0;
        }
        #reportPreviewModal .table th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: #fff;
            font-weight: 600;
            padding: 0.85rem 1rem;
            border: none;
            position: sticky;
            top: 0;
            z-index: 5;
            white-space: nowrap;
        }
        #reportPreviewModal .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
        }
        #reportPreviewModal .modal-footer {
            flex-shrink: 0;
        }
        
        select option {
            color: #000000 !important;
            background-color: #ffffff !important;
        }

        /* ===================================================================
           PRINT: only the report table should ever be printed.
           Strategy: hide the entire document by default when printing,
           then explicitly reveal only #print-area (which is populated with
           a clone of whichever table — main or preview — the user wants to
           print), regardless of where it sits in the DOM, including inside
           a Bootstrap modal that would otherwise be display:none.
           =================================================================== */
        #print-area { display: none; }

        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area {
                display: block !important;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            #print-area h4 {
                font-size: 16px;
                margin-bottom: 12px;
            }
            #print-area table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
            }
            #print-area th, #print-area td {
                border: 1px solid #ccc;
                padding: 6px 8px;
                text-align: left;
            }
            #print-area th {
                background: #1b5e3f !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* ===== DARK MODE ===== */
        body.dark-mode {
            background: #0f1419 !important;
            color: #f8fafc !important;
        }
        body.dark-mode .main-content {
            background: transparent !important;
        }
        body.dark-mode .card {
            background: #111827 !important;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35) !important;
        }
        body.dark-mode .card-body {
            background: transparent !important;
        }
        body.dark-mode .filter-section,
        body.dark-mode .filter-card {
            background: #111827 !important;
            border-color: #1f2937 !important;
            color: #e2e8f0 !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.45) !important;
        }
        body.dark-mode .form-label,
        body.dark-mode h2,
        body.dark-mode .text-muted {
            color: #e2e8f0 !important;
        }
        body.dark-mode .btn-outline-secondary {
            border-color: #64748b !important;
            color: #cbd5e1 !important;
        }
        body.dark-mode .btn-outline-secondary:hover {
            background: #64748b !important;
            color: #ffffff !important;
        }
        body.dark-mode .btn-outline-primary,
        body.dark-mode .btn-outline-success,
        body.dark-mode .btn-outline-info {
            color: #f8fafc !important;
        }
        body.dark-mode .download-dropdown .dropdown-toggle {
            color: #6ee7b7 !important;
            border-color: #1b5e3f !important;
        }
        body.dark-mode .download-dropdown .dropdown-menu {
            background: #111827 !important;
            border-color: #1f2937 !important;
        }
        body.dark-mode .download-dropdown .dropdown-item {
            color: #e2e8f0 !important;
        }
        body.dark-mode .download-dropdown .dropdown-item:hover,
        body.dark-mode .download-dropdown .dropdown-item:focus {
            background: #0d2b1a !important;
            color: #6ee7b7 !important;
        }
        body.dark-mode .table-container {
            background: #0f172a !important;
            border-color: #1f2937 !important;
        }
        body.dark-mode .table td {
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .table tbody tr:hover {
            background: #111827 !important;
        }
        body.dark-mode .filter-indicator,
        body.dark-mode .alert {
            background: #111827 !important;
            color: #e2e8f0 !important;
            border-color: #1f2937 !important;
            box-shadow: 0 4px 20px rgba(0,0,0,0.45) !important;
        }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            border-color: #2ecc71 !important;
            box-shadow: 0 0 0 0.2rem rgba(46,204,113,0.15) !important;
        }
        body.dark-mode select option {
            color: #e2e8f0 !important;
            background-color: #0f172a !important;
        }
        body.dark-mode .medicine-search-wrap .search-input-group i.bi-search,
        body.dark-mode .medicine-search-wrap .search-clear-btn {
            color: #64748b !important;
        }
        body.dark-mode #reportPreviewModal .preview-table-scroll::-webkit-scrollbar-track {
            background: #0f172a !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div id="toast-container"></div>

    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-file-text me-2"></i> Reports</h2>
        </div>

        <div class="card admin-table-card">
            <div class="card-body">
                <div class="filter-section">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label"><i class="bi bi-file-earmark-text me-1"></i>Specifically Inventory Report</label>
                            <select class="form-select" id="report-type">
                                <option value="inventory">Inventory Report</option>
                                <option value="transactions">Transactions Report</option>
                                <option value="sales">Sales Report</option>
                            </select>
                        </div>
                        <div class="col-md-3 medicine-search-wrap">
                            <label class="form-label"><i class="bi bi-funnel me-1"></i> Filter by Medicine</label>
                            <div class="search-input-group">
                                <i class="bi bi-search"></i>
                                <input type="text" class="form-control" id="medicine-search" placeholder="Search medicine..." autocomplete="off">
                                <button type="button" class="search-clear-btn" id="medicine-search-clear" title="Clear">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>
                            <!-- Holds the selected medicine's id; empty = "All Medicines" -->
                            <input type="hidden" id="medicine-filter-id" value="">
                        </div>
                        <div class="col-md-6 d-flex justify-content-end gap-2">
                            <!-- <button class="btn btn-outline-secondary action-btn" onclick="previewReport()">
                                <i class="bi bi-eye me-1"></i> Preview
                            </button> -->
                            <button class="btn btn-outline-secondary action-btn" id="print-report-btn" type="button">
                                <i class="bi bi-printer me-1"></i> Print
                            </button>
                            <div class="btn-group download-dropdown">
                                <button type="button" class="btn action-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-download me-1"></i> Download
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item pdf" href="#" id="download-pdf-btn">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF (.pdf)
                                    </a></li>
                                    <li><a class="dropdown-item excel" href="#" id="download-excel-btn">
                                        <i class="bi bi-file-earmark-excel"></i> Excel (.xlsx)
                                    </a></li>
                                    <li><a class="dropdown-item csv" href="#" id="download-csv-btn">
                                        <i class="bi bi-filetype-csv"></i> CSV (.csv)
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="filter-indicator" class="filter-indicator d-none">
                    <i class="bi bi-funnel-fill me-2"></i>
                    <span id="filter-text"></span>
                </div>

                <div class="table-container admin-table-scroll">
                    <table class="table table-hover">
                        <thead id="report-table-header"></thead>
                        <tbody id="report-table-body"></tbody>
                    </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="report-page-info" class="text-muted"></small>
                    <nav id="report-pagination" aria-label="Report pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="reportPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title" id="preview-title">Report Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="preview-table-scroll">
                        <table class="table table-striped mb-0">
                            <thead id="preview-table-header"></thead>
                            <tbody id="preview-table-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary action-btn" id="print-preview-btn">
                        <i class="bi bi-printer me-1"></i> Print this
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden print target: populated just-in-time by printReport() with
         whichever table (main report or preview) the user wants printed. -->
    <div id="print-area">
        <h4 id="print-area-title"></h4>
        <table>
            <thead id="print-area-header"></thead>
            <tbody id="print-area-body"></tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/phar_reports.js"></script>
</body>
</html>