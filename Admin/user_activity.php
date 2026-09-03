<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity - MedInventory</title>

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary: #1b5e3f;
            --primary-dark: #0f3f28;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #fbbf24;
            --info: #3b82f6;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f5f7fa;
            overflow: auto;
            min-height: 100vh;
        }

        /* Sidebar offset */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            transition: margin-left .3s ease;
            min-height: 100vh;
            overflow: visible;
        }
        @media (max-width: 992px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(44,82,130,.2);
        }
        .page-header h2 { margin: 0; font-weight: 700; color: white; }

        /* Cards */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0,0,0,.08);
            background: #fff;
            overflow: hidden;
        }

        /* Table Container - Fixed height, internal scroll */
        .table-container {
            height: 400px; /* Fixed height for ~5-6 rows */
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .table {
            margin-bottom: 0;
            white-space: nowrap;
            border-radius: 12px;
            width: 100%;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            font-weight: 600;
            padding: .75rem;
            position: sticky;
            top: 0;
            z-index: 10;
            border: none;
            white-space: nowrap;
        }

        .table tbody td {
            vertical-align: middle;
            padding: .5rem;
            font-size: 0.9rem;
            color: #2d3748;
            border-color: #e2e8f0;
            white-space: nowrap;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: #f7fafc;
        }

        /* Status Badges */
        .status-badge {
            padding: .3rem .6rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: inline-block;
            white-space: nowrap;
        }
        .status-login      { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; }
        .status-logout     { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color:#fff; }
        .status-failed     { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color:#fff; }
        .status-pwdchange  { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color:#78350f; }
        .status-delivered  { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; }
        .status-ordered    { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color:#fff; }
        .status-pending    { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color:#78350f; }
        .status-cancelled  { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color:#fff; }

        /* Buttons */
        .action-btn {
            padding: .5rem 1rem;
            font-size: .9rem;
            border-radius: 8px;
            transition: all .3s ease;
            font-weight: 500;
            margin: 0 .2rem;
            white-space: nowrap;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }
        .btn-success  { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border:none; }
        .btn-success:hover { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .btn-info     { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border:none; }
        .btn-info:hover { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); }
        .btn-warning  { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border:none; color:#78350f; }
        .btn-warning:hover { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .btn-secondary { background: #6c757d; border:none; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-outline-primary { border: 2px solid #1b5e3f; color: #1b5e3f; background: transparent; font-weight: 600; }
        .btn-outline-primary:hover { background: #1b5e3f; color: white; }

        /* Toast */
        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 400px;
        }
        .toast-notification {
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn .3s ease-out;
            min-width: 300px;
            font-weight: 500;
            font-size: 14px;
            color: #fff;
        }
        .toast-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .toast-error   { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .toast-info    { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .toast-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .toast-close {
            background: rgba(255,255,255,.2);
            border: none;
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background .2s;
            margin-left: auto;
        }
        .toast-close:hover { background: rgba(255,255,255,.3); }
        @keyframes slideIn { from { transform: translateX(400px); opacity:0; } to { transform: translateX(0); opacity:1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity:1; } to { transform: translateX(400px); opacity:0; } }

        /* Scrollbar for table */
        .table-container::-webkit-scrollbar {
            width: 8px;
        }
        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
        }
        .table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }

        /* Form controls */
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            padding: .85rem 1.25rem;
            transition: all .3s ease;
            background: white;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 .3rem rgba(44,82,130,.12);
            transform: translateY(-2px);
            background: #ffffff;
        }

        /* Stats cards */
        .stats-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
            transition: transform .2s;
        }
        .stats-card:hover { transform: translateY(-4px); }

        /* Modal */
        .modal-content { 
            border-radius: 25px; 
            box-shadow: 0 25px 80px rgba(0,0,0,.4); 
            overflow: hidden;
            border: none;
        }
        .modal-header { 
            padding: 1.75rem 2.5rem; 
            border-radius: 25px 25px 0 0; 
        }
        .modal-header.bg-primary { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            box-shadow: 0 4px 20px rgba(27, 94, 63, 0.3);
        }
        .modal-header.bg-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            box-shadow: 0 4px 20px rgba(6, 182, 212, 0.3);
        }
        .modal-title { 
            font-size: 1.75rem; 
            font-weight: 700; 
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .modal-title i { 
            font-size: 2rem; 
            vertical-align: middle; 
            margin-right: .75rem; 
        }
        .modal-body {
            padding: 2.5rem;
        }
        .modal-footer {
            padding: 1.75rem 2.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-top: 2px solid #e2e8f0;
            border-radius: 0 0 25px 25px;
        }
        .modal-dialog {
            max-width: 900px;
        }
        .modal-dialog-scrollable .modal-body {
            max-height: calc(95vh - 200px);
            overflow-y: auto;
        }
        #activityDetailModal .modal-dialog {
            max-width: min(960px, calc(100vw - 2rem));
            max-height: calc(100vh - 2rem);
            margin: 1rem auto;
        }
        #activityDetailModal .modal-content {
            max-height: calc(100vh - 2rem);
        }
        #activityDetailModal .modal-body {
            overflow-y: auto;
        }

        .form-label {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.75rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }
        .form-label i {
            margin-right: 0.5rem;
            color: #1b5e3f;
            font-size: 1.1rem;
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1b5e3f;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
        }
        .form-section-title i {
            margin-right: 0.5rem;
            font-size: 1.3rem;
        }

        .info-badge {
            background: #e6fffa;
            border: 1px solid #0f766e;
            color: #065f46;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        .info-badge i {
            margin-right: 0.5rem;
            font-size: 1.1rem;
        }

        .form-group-icon {
            position: relative;
        }
        .form-group-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.1rem;
            pointer-events: none;
        }
        .form-group-icon .form-control,
        .form-group-icon .form-select {
            padding-left: 3rem;
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

        select option {
            color: #000000 !important;
            background-color: #ffffff !important;
        }

        .card-body {
            padding: 0;
        }

        @media (max-width: 768px) {
            #toast-container {
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
            }
            .toast-notification {
                min-width: unset;
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
        body.dark-mode .page-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
            color: white !important;
        }
        body.dark-mode .page-header h2,
        body.dark-mode .page-header p {
            color: white !important;
        }
        body.dark-mode .card {
            background: #111827 !important;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.35) !important;
        }
        body.dark-mode .card-body {
            background: transparent !important;
        }
        body.dark-mode .table-container {
            background: #0f172a !important;
            border-color: #1f2937 !important;
        }
        body.dark-mode .table {
            background: #0f172a !important;
        }
        body.dark-mode .table thead th {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
            color: white !important;
        }
        body.dark-mode .table tbody td {
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }
        body.dark-mode .table tbody tr:hover {
            background: #111827 !important;
        }
        body.dark-mode .stats-card {
            background: #111827 !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25) !important;
        }
        body.dark-mode .stats-card h6 {
            color: #a8b5cc !important;
        }
        body.dark-mode .stats-card h3 {
            color: #e2e8f0 !important;
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
        body.dark-mode .form-control::placeholder {
            color: #6b7280 !important;
        }
        body.dark-mode .form-label {
            color: #e2e8f0 !important;
            font-weight: 600 !important;
        }
        body.dark-mode .form-label i {
            color: #2ecc71 !important;
        }
        body.dark-mode .btn-outline-primary {
            border-color: #1b5e3f !important;
            color: #2ecc71 !important;
        }
        body.dark-mode .btn-outline-primary:hover {
            background: #1b5e3f !important;
            color: white !important;
        }
        body.dark-mode .btn-outline-success {
            border-color: #10b981 !important;
            color: #6ee7b7 !important;
        }
        body.dark-mode .btn-outline-success:hover {
            background: #10b981 !important;
            color: white !important;
        }
        body.dark-mode .btn-outline-info {
            border-color: #06b6d4 !important;
            color: #67e8f9 !important;
        }
        body.dark-mode .btn-outline-info:hover {
            background: #06b6d4 !important;
            color: white !important;
        }
        body.dark-mode .btn-outline-danger {
            border-color: #ef4444 !important;
            color: #fca5a5 !important;
        }
        body.dark-mode .btn-outline-danger:hover {
            background: #ef4444 !important;
            color: white !important;
        }
        body.dark-mode .btn-outline-warning {
            border-color: #f59e0b !important;
            color: #fbbf24 !important;
        }
        body.dark-mode .btn-outline-warning:hover {
            background: #f59e0b !important;
            color: #78350f !important;
        }
        body.dark-mode .btn-primary,
        body.dark-mode .btn-success,
        body.dark-mode .btn-info,
        body.dark-mode .btn-secondary {
            color: white !important;
        }
        body.dark-mode .status-badge {
            color: white !important;
        }
        body.dark-mode select option {
            color: #e2e8f0 !important;
            background-color: #0f172a !important;
        }
        body.dark-mode .form-section {
            background: #111827 !important;
            border-color: #1f2937 !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .form-section-title {
            color: #2ecc71 !important;
            border-bottom-color: #1f2937 !important;
        }
        body.dark-mode .info-badge {
            background: rgba(46, 204, 113, 0.1) !important;
            border-color: #2ecc71 !important;
            color: #6ee7b7 !important;
        }
        body.dark-mode .modal-content {
            background: #111827 !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .modal-body {
            background: #0f172a !important;
        }
        body.dark-mode .modal-header {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
            border-color: #1f2937 !important;
        }
        body.dark-mode .modal-header.bg-primary {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
        }
        body.dark-mode .modal-header.bg-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
        }
        body.dark-mode .modal-title {
            color: white !important;
        }
        body.dark-mode .table-container::-webkit-scrollbar-track {
            background: #0f172a !important;
        }
        body.dark-mode .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div class="main-content admin-table-page" id="mainContent">
        <div class="page-header">
            <h2><i class="bi bi-activity me-2"></i> User Activity</h2>
            <p class="mb-0 text-light">Monitor and track user actions</p>
        </div>

        <!-- Controls -->
        <div class="d-flex mb-3 align-items-center gap-2 flex-wrap">
            <input id="search-input" type="text" class="form-control" placeholder="Search activities..." style="max-width:250px; min-width:250px;">
            <button id="filter-all-btn" class="btn btn-outline-primary action-btn">
                All
            </button>
            <button id="filter-login-btn" class="btn btn-outline-success action-btn">
                <i class="bi bi-box-arrow-in-right me-1"></i> Login
            </button>
            <button id="filter-logout-btn" class="btn btn-outline-info action-btn">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </button>
            <button id="filter-failed-btn" class="btn btn-outline-danger action-btn">
                <i class="bi bi-x-circle me-1"></i> Failed
            </button>
            <button id="filter-pwd-btn" class="btn btn-outline-warning action-btn">
                <i class="bi bi-key me-1"></i> Pwd Change
            </button>
            <button id="reset-filter-btn" class="btn btn-secondary action-btn">
                <i class="bi bi-arrow-clockwise me-1"></i> Reset
            </button>
            <button id="export-btn" class="btn btn-success action-btn">
                <i class="bi bi-download me-1"></i> Export CSV
            </button>
            <button id="refresh-btn" class="btn btn-info action-btn">
                <i class="bi bi-arrow-repeat me-1"></i> Refresh
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-sm-6">
                <div class="stats-card">
                    <i class="bi bi-graph-up fs-4 text-primary"></i>
                    <h6 class="text-muted mt-2 mb-1">Total Activities</h6>
                    <h3 class="mb-0 fw-bold" id="total-activities">0</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="stats-card">
                    <i class="bi bi-box-arrow-in-right fs-4 text-success"></i>
                    <h6 class="text-muted mt-2 mb-1">Today's Logins</h6>
                    <h3 class="mb-0 fw-bold text-success" id="today-logins">0</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="stats-card">
                    <i class="bi bi-people fs-4 text-primary"></i>
                    <h6 class="text-muted mt-2 mb-1">Active Users Now</h6>
                    <h3 class="mb-0 fw-bold text-primary" id="active-users">0</h3>
                </div>
            </div>
        </div>

        <!-- Activities Table -->
        <div class="card admin-table-card">
            <div class="card-body p-0">
                <div class="table-container admin-table-scroll">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                                <th>Duration</th>
                                <th>Date/Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="activities-table">
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-3 text-muted">Loading activities...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="activity-page-info" class="text-muted"></small>
                    <nav id="activity-pagination" aria-label="Activity pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Detail Modal -->
    <div class="modal fade" id="activityDetailModal" tabindex="-1" aria-labelledby="activityDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="activityDetailModalLabel">
                        <i class="bi bi-info-circle-fill"></i> Activity Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="bi bi-person"></i> User Information
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <strong class="text-muted d-block mb-1">Username:</strong>
                                <p class="mb-0 fw-bold" id="detail-username">-</p>
                            </div>
                            <div class="col-6">
                                <strong class="text-muted d-block mb-1">Full Name:</strong>
                                <p class="mb-0 fw-bold" id="detail-name">-</p>
                            </div>
                            <div class="col-6">
                                <strong class="text-muted d-block mb-1">Role:</strong>
                                <p class="mb-0 fw-bold" id="detail-role">-</p>
                            </div>
                            <div class="col-6">
                                <strong class="text-muted d-block mb-1">Action:</strong>
                                <p class="mb-0 fw-bold" id="detail-action">-</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="bi bi-globe"></i> Connection Details
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <strong class="text-muted d-block mb-1">IP Address:</strong>
                                <p class="mb-0 fw-bold" id="detail-ip">-</p>
                            </div>
                            <div class="col-6">
                                <strong class="text-muted d-block mb-1">Session Duration:</strong>
                                <p class="mb-0 fw-bold" id="detail-duration">-</p>
                            </div>
                            <div class="col-12">
                                <strong class="text-muted d-block mb-1">User Agent:</strong>
                                <p class="mb-0 text-break small" id="detail-user-agent">-</p>
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="bi bi-clock"></i> Timestamp
                        </div>
                        <div class="row g-3">
                            <div class="col-12">
                                <strong class="text-muted d-block mb-1">Date & Time:</strong>
                                <p class="mb-0 fw-bold" id="detail-datetime">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/user_activity.js"></script>
</body>
</html>
