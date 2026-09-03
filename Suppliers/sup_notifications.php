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
    <title>Notifications - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #f5f7fa; 
            overflow: hidden; /* Prevent page-level scrolling */
            height: 100%;
            margin: 0; /* Ensure no default margins */
        }
        .main-content { 
            margin-left: 250px; 
            padding: 2rem;
            height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Prevent main-content scrolling */
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                height: auto;
                min-height: 100vh;
            }
            .row.g-3 {
                flex-direction: column !important;
            }
            .col-md-6 {
                min-height: 280px;
                height: auto;
            }
            .notifications-container {
                max-height: clamp(220px, 42vh, 340px);
            }
            .pagination-bar {
                justify-content: center;
                text-align: center;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h2 { 
            color: white; 
            margin: 0; 
            font-size: 1.75rem; 
        }
        
        .notification-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        .card { 
            border-radius: 15px; 
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); 
            background: #fff; 
            border: none;
            display: flex;
            flex-direction: column;
            height: 100%; /* Card takes full height of parent */
        }
        
        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow: hidden; /* Contain scrolling to notifications-container */
        }
        
        .filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            border-left: 4px solid #1b5e3f;
            flex-shrink: 0;
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
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px;
            margin-bottom: 0.75rem;
            flex-shrink: 0;
        }
        
        .section-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .notifications-container {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
            max-height: clamp(220px, 38vh, 420px);
            padding-right: 0.25rem;
        }
        
        .pagination-bar {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.35rem 0.5rem;
            margin-top: 0.5rem !important;
            margin-bottom: 0;
            flex-wrap: wrap;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .main-content > .row.g-3 {
            flex: 1 1 auto !important;
            min-height: 0 !important;
        }

        .pagination-bar .sup-pagination-info {
            flex: 1 1 auto;
            min-width: 180px;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pagination-bar .pagination {
            margin: 0;
            flex: 0 0 auto;
            font-size: 0.85rem;
        }

        .pagination-bar .page-link {
            padding: 0.3rem 0.55rem;
            line-height: 1.2;
        }

        .notifications-container::-webkit-scrollbar {
            width: 8px;
        }
        .notifications-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .notifications-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            border-radius: 10px;
        }
        
        .list-group-item { 
            border: 1px solid #e2e8f0;
            padding: 1rem 1rem 0.9rem; 
            background: #ffffff;
            border-radius: 14px; 
            margin-bottom: 0.8rem;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.04);
            transition: all 0.25s ease;
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }
        
        .list-group-item:hover { 
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
            border-color: #bfd4c9;
        }
        
        .list-group-item.unread {
            background: linear-gradient(135deg, #fffaf0 0%, #fff7db 100%);
            border-left: 4px solid #f59e0b;
        }
        
        .list-group-item.read {
            background: #ffffff;
            opacity: 0.92;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.35);
        }
        
        .icon-warning {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #78350f;
        }
        
        .icon-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .icon-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.2rem;
            font-size: 0.9rem;
            letter-spacing: 0.02em;
        }
        
        .notification-message {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            line-height: 1.5;
        }
        
        .notification-time {
            color: #94a3b8;
            font-size: 0.75rem;
            font-style: italic;
        }
        
        .d-flex.gap-2.ms-3 {
            margin-left: auto !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
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
        
        .btn-outline-primary {
            border: 2px solid #1b5e3f;
            color: #1b5e3f;
            background: transparent;
        }
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
            color: white;
        }
        
        .btn-outline-light {
            border: 2px solid rgba(255, 255, 255, 0.5);
            color: white;
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
            color: white;
            transform: translateY(-2px);
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
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div id="toast-container"></div>

    <div class="main-content">
        <div class="page-header">
            <h2><i class="bi bi-bell me-2"></i> Notifications</h2>
            <div class="d-flex align-items-center gap-2">
                <span class="notification-badge" id="notification-count">0</span>
                <button class="btn btn-sm btn-outline-light" id="dismiss-all-badge" title="Clear all unread notifications">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>
        </div>

        <div class="filter-section">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label"><i class="bi bi-search me-1"></i> Search</label>
                    <input type="text" class="form-control" id="search" placeholder="Search notifications...">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="bi bi-calendar me-1"></i> From Date</label>
                    <input type="date" class="form-control" id="from-date">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><i class="bi bi-calendar me-1"></i> To Date</label>
                    <input type="date" class="form-control" id="to-date">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary action-btn w-100" id="clear-filters">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-3" style="flex: 1; min-height: 0;">
            <div class="col-md-6" style="display: flex; flex-direction: column; min-height: 0;">
                <div class="card" style="flex: 1; min-height: 0;">
                    <div class="card-body">
                        <div class="section-header">
                            <h4><i class="bi bi-bell-fill me-2"></i> Recent Notifications <span class="badge bg-primary ms-2" id="recent-count">0</span></h4>
                            <button class="btn btn-sm btn-outline-primary action-btn" id="mark-all-recent">
                                <i class="bi bi-check-all me-1"></i> Mark All as Read
                            </button>
                        </div>
                        <div class="notifications-container" id="recent-notifications"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6" style="display: flex; flex-direction: column; min-height: 0;">
                <div class="card" style="flex: 1; min-height: 0;">
                    <div class="card-body">
                        <div class="section-header">
                            <h4><i class="bi bi-archive me-2"></i> Old Notifications <span class="badge bg-secondary ms-2" id="old-count">0</span></h4>
                            <button class="btn btn-sm btn-outline-primary action-btn" id="mark-all-old">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Mark All as Unread
                            </button>
                        </div>
                        <div class="notifications-container" id="old-notifications"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pagination-bar mt-2 no-print">
            <span class="sup-pagination-info" id="notifications-pagination-info">Page 1 of 1 · 0 items</span>
            <nav aria-label="Notifications pagination">
                <ul class="pagination justify-content-center mb-0" id="notifications-pagination"></ul>
            </nav>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_notifications.js"></script>
</body>
</html>
