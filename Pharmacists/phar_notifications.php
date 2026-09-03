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
            }
            .row.g-3 {
                flex-direction: column !important;
            }
            .col-md-6 {
                height: 50vh; /* Each card takes half the viewport height */
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
            flex: 1; /* Take remaining space */
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden;
            max-height: 420px;
            min-height: 0; /* Allow shrinking */
            padding-bottom: 0.75rem;
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
            border: 2px solid #e2e8f0;
            padding: 1rem; 
            background: #ffffff;
            border-radius: 12px; 
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .list-group-item:hover { 
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #1b5e3f;
        }
        
        .list-group-item.unread {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 4px solid #f59e0b;
        }
        
        .list-group-item.read {
            background: #ffffff;
            opacity: 0.8;
        }
        
        .notification-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-right: 0.75rem;
            flex-shrink: 0;
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
        }
        
        .notification-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.15rem;
            font-size: 0.9rem;
        }
        
        .notification-message {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
        }
        
        .notification-time {
            color: #94a3b8;
            font-size: 0.75rem;
            font-style: italic;
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

        /* ===== DARK MODE ===== */
        body.dark-mode {
            background: #0f1419 !important;
            color: #f8fafc !important;
        }
        body.dark-mode .main-content {
            background: transparent !important;
        }
        body.dark-mode .page-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
        }
        body.dark-mode .page-header h2 {
            color: white !important;
        }
        body.dark-mode .notification-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
            color: white !important;
        }
        body.dark-mode .card {
            background: #111827 !important;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35) !important;
        }
        body.dark-mode .card-body {
            background: transparent !important;
        }
        body.dark-mode .filter-section {
            background: linear-gradient(135deg, #0f172a 0%, #1a2332 100%) !important;
            border-left-color: #2ecc71 !important;
        }
        body.dark-mode .section-header {
            background: linear-gradient(135deg, #1a2332 0%, #0f172a 100%) !important;
            border-color: #334155 !important;
        }
        body.dark-mode .section-header h4 {
            color: #e2e8f0 !important;
        }
        body.dark-mode .notifications-container::-webkit-scrollbar-track {
            background: #0f172a !important;
        }
        body.dark-mode .notifications-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
        }
        body.dark-mode .list-group-item {
            background: #111827 !important;
            border-color: #334155 !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
        }
        body.dark-mode .list-group-item:hover {
            background: #1a2332 !important;
            border-color: #2ecc71 !important;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.15) !important;
        }
        body.dark-mode .list-group-item.unread {
            background: linear-gradient(135deg, #1e3a2f 0%, #0f2818 100%) !important;
            border-left: 4px solid #2ecc71 !important;
            border-color: #2ecc71 !important;
        }
        body.dark-mode .list-group-item.read {
            background: #0f172a !important;
            opacity: 1 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .notification-title {
            color: #e2e8f0 !important;
            font-weight: 700 !important;
        }
        body.dark-mode .notification-message {
            color: #a8b5cc !important;
        }
        body.dark-mode .notification-time {
            color: #6b7280 !important;
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
            box-shadow: 0 0 0 0.2rem rgba(46, 204, 113, 0.15) !important;
        }
        body.dark-mode .form-label {
            color: #e2e8f0 !important;
        }
        body.dark-mode .btn-outline-primary {
            border-color: #2ecc71 !important;
            color: #6ee7b7 !important;
        }
        body.dark-mode .btn-outline-primary:hover {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
            color: white !important;
            border-color: #2ecc71 !important;
        }
        body.dark-mode .btn-outline-light {
            border-color: rgba(46, 204, 113, 0.5) !important;
            color: #2ecc71 !important;
        }
        body.dark-mode .btn-outline-light:hover {
            background: rgba(46, 204, 113, 0.2) !important;
            border-color: #2ecc71 !important;
            color: white !important;
        }
        body.dark-mode .empty-state {
            color: #6b7280 !important;
        }
        body.dark-mode .icon-warning {
            background: linear-gradient(135deg, #92400e 0%, #78350f 100%) !important;
            color: #fbbf24 !important;
        }
        body.dark-mode .icon-danger {
            background: linear-gradient(135deg, #7f1d1d 0%, #6f0e0e 100%) !important;
            color: #fca5a5 !important;
        }
        body.dark-mode .icon-info {
            background: linear-gradient(135deg, #0a3f4b 0%, #0e5a6f 100%) !important;
            color: #67e8f9 !important;
        }
        body.dark-mode .badge {
            background: #1b5e3f !important;
            color: white !important;
        }
        body.dark-mode .badge.bg-primary {
            background: #1b5e3f !important;
            font-size: 0.75rem;
            font-style: italic;
        }
        body.dark-mode .badge.bg-secondary {
            background: #334155 !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

  
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

        <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 mb-0 pt-3 pb-3 border-top w-100" style="margin-left:0; margin-right:0; padding: 0.9rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
            <small id="notifications-page-info" class="text-muted fw-semibold"></small>
            <nav id="notifications-pagination" aria-label="Notifications pages"></nav>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/phar_notifications.js"></script>
</body>
</html>
