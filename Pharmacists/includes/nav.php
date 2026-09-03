<?php
// includes/nav.php
if (!isset($conn)) {
    $db_path_nav = __DIR__ . '/../config/db.php';
    if (file_exists($db_path_nav)) {
        require_once $db_path_nav;
    }
}
$records_per_page = (isset($conn) && $conn && function_exists('getRecordsPerPage')) ? getRecordsPerPage($conn) : 10;
$system_date_format = (isset($conn) && $conn && function_exists('getDateFormat')) ? getDateFormat($conn) : 'Y-m-d';
?>
<script>
    window.RECORDS_PER_PAGE = <?php echo $records_per_page; ?>;
    window.SYSTEM_DATE_FORMAT = <?php echo json_encode($system_date_format); ?>;
</script>
<?php require_once __DIR__ . '/page_header.php'; ?>

<!-- Logout Confirmation Modal -->
<div class="modal fade" id="confirmLogoutModal" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content logout-modal">
            <div class="logout-banner">
                <i class="bi bi-box-arrow-right"></i>
                <span>Ready to sign out</span>
            </div>
            <div class="modal-body logout-modal-body">
                <?php
                    $logoutName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
                    $logoutParts = array_filter(explode(' ', $logoutName));
                    $logoutInitials = '';
                    foreach ($logoutParts as $part) {
                        $logoutInitials .= strtoupper($part[0]);
                    }
                    if ($logoutInitials === '') { $logoutInitials = 'U'; }
                    $logoutRole = isset($_SESSION['role']) ? ($_SESSION['role'] === 'admin' ? 'Administrator' : ucfirst($_SESSION['role'])) : 'Guest';
                ?>
                <div class="logout-title">
                    <h5 id="confirmLogoutModalLabel">Confirm logout</h5>
                    <p class="logout-copy">The following account will be signed out of the system.</p>
                </div>
                <div class="logout-user-card">
                    <div class="logout-user-avatar"><?php echo $logoutInitials; ?></div>
                    <div class="logout-user-info">
                        <div class="logout-user-name"><?php echo htmlspecialchars($logoutName); ?></div>
                        <div class="logout-user-role"><?php echo htmlspecialchars($logoutRole); ?></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer logout-modal-footer">
                <button type="button" class="btn btn-cancel w-100" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-logout-confirm w-100" id="confirm-logout-btn">Confirm logout</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* ========================================
   UNIVERSAL DARK MODE - FULL COVERAGE
   ======================================== */

/* Force dark mode on ALL elements */
.dark-mode,
.dark-mode * {
    transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease !important;
}

/* Root Variables - Dark Mode */
.dark-mode {
    --sidebar-bg: #1e1e2e;
    --sidebar-border: #33334d;
    --sidebar-text: #e0e0e0;
    --sidebar-text-muted: #a0a0b0;
    --sidebar-hover: #2a2a3e;
    --sidebar-active: #343a66;
    --sidebar-accent: #1b5e3f;
    --sidebar-gradient: linear-gradient(135deg, #1b5e3f 0%, #3d4b8e 100%);

    --card-bg: #16213e;
    --card-border: #0f3460;
    --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);

    --table-bg: #16213e;
    --table-header-bg: #0f3460;
    --table-border: #0f3460;
    --table-stripe-bg: #1a2942;
    --table-hover-bg: #0f3460;

    --text-primary: #e0e0e0;
    --text-secondary: #a0a0b0;

    --input-bg: #0f3460;
    --input-border: #1b5e3f;

    --badge-bg: #0f3460;
    --badge-text: #e0e0e0;

    --navbar-bg: #1e1e2e;
    --navbar-border: #33334d;

    --tooltip-bg: #2a2a3e;
    --tooltip-text: #e0e0e0;

    --popover-bg: #1e1e2e;
    --popover-border: #0f3460;

    --offcanvas-bg: #1e1e2e;
    --offcanvas-border: #0f3460;
    
    --modal-bg: #16213e;
    --modal-border: #0f3460;
    --modal-header-bg: #1b5e3f;
    --modal-shadow: 0 25px 80px rgba(0, 0, 0, 0.6);
    
    --welcome-bg: linear-gradient(135deg, #16213e 0%, #1a2942 100%);
    --stock-ticker-bg: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
    --stock-ticker-text: #e0e0e0;
}

/* Navbar */
.dark-mode .navbar {
    background-color: var(--navbar-bg) !important;
    border-color: var(--navbar-border) !important;
}
.dark-mode .navbar .navbar-brand,
.dark-mode .navbar .nav-link {
    color: #e0e0e0 !important;
}
.dark-mode .navbar .nav-link:hover {
    color: #c3c8ff !important;
}

/* Cards - Enhanced */
.dark-mode .card {
    background-color: var(--card-bg) !important;
    border-color: var(--card-border) !important;
    box-shadow: var(--card-shadow) !important;
    color: var(--text-primary) !important;
}
.dark-mode .card-header,
.dark-mode .card-footer {
    background-color: var(--table-header-bg) !important;
    border-color: var(--card-border) !important;
    color: var(--text-primary) !important;
}

/* Tables - Consistent Design */
.table-container {
    max-height: 450px;
    overflow-y: auto;
    border-radius: 15px;
    background: var(--table-bg);
    border: 1px solid var(--table-border);
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
.table-container::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%);
}

.dark-mode .table-container::-webkit-scrollbar-track {
    background: #1a2942;
}
.dark-mode .table-container::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
}

.table {
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 0;
    table-layout: auto;
    width: 100%;
    background-color: var(--table-bg);
    color: var(--text-primary);
}

.table th,
.table td {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    padding: 1rem;
    border-color: var(--table-border);
    color: var(--text-primary);
}

.table th {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: #fff;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 10;
    border: none;
}

.table td {
    vertical-align: middle;
    color: var(--text-primary);
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: var(--table-hover-bg);
    transform: translateX(2px);
}

.table-striped > tbody > tr:nth-of-type(odd) > * {
    background-color: var(--table-stripe-bg);
}

/* Action Buttons in Tables */
.action-btn {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    border-radius: 8px;
    transition: all 0.3s ease;
    margin: 0.125rem;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(27, 94, 63, 0.3);
}

/* Forms */
.dark-mode .form-control,
.dark-mode .form-select,
.dark-mode .form-check-input {
    background-color: var(--input-bg) !important;
    border-color: var(--input-border) !important;
    color: var(--text-primary) !important;
}
.dark-mode .form-control:focus,
.dark-mode .form-select:focus {
    background-color: var(--input-bg) !important;
    border-color: #1b5e3f !important;
    box-shadow: 0 0 0 0.25rem rgba(83, 98, 158, 0.25) !important;
    color: var(--text-primary) !important;
}
.dark-mode .form-label,
.dark-mode .form-text {
    color: var(--text-primary) !important;
}
.dark-mode .form-check-label {
    color: var(--text-primary) !important;
}

/* Buttons */
.dark-mode .btn {
    transition: all 0.3s ease !important;
}
.dark-mode .btn-primary {
    background-color: #1b5e3f !important;
    border-color: #1b5e3f !important;
}
.dark-mode .btn-primary:hover {
    background-color: #3d4b8e !important;
    border-color: #3d4b8e !important;
}
.dark-mode .btn-outline-primary {
    color: #1b5e3f !important;
    border-color: #1b5e3f !important;
}
.dark-mode .btn-outline-primary:hover {
    background-color: #1b5e3f !important;
    color: white !important;
}
.dark-mode .btn-light {
    background-color: #2a2a3e !important;
    border-color: #444 !important;
    color: #e0e0e0 !important;
}
.dark-mode .btn-light:hover {
    background-color: #363636 !important;
}

/* Badges */
.dark-mode .badge {
    background-color: var(--badge-bg) !important;
    color: var(--badge-text) !important;
}

/* Modals - Purchase Style */
.modal-content {
    border-radius: 25px;
    border: none;
    box-shadow: var(--modal-shadow);
    overflow: hidden;
    background-color: var(--modal-bg);
    color: var(--text-primary);
    border: 1px solid var(--modal-border);
}

.modal-dialog {
    max-width: 900px;
    max-height: 95vh;
    display: flex;
    align-items: center;
}

.modal-dialog-scrollable .modal-body {
    max-height: calc(95vh - 200px);
    overflow-y: auto;
}

.modal-header {
    background: linear-gradient(135deg, var(--modal-header-bg) 0%, var(--primary-dark) 100%);
    box-shadow: 0 4px 20px rgba(27, 94, 63, 0.3);
    border-bottom: none;
    padding: 1.75rem 2.5rem;
    border-radius: 25px 25px 0 0;
}

.modal-header.bg-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
}

.modal-header.bg-warning {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    box-shadow: 0 4px 20px rgba(251, 191, 36, 0.3);
}

.modal-header.bg-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.3);
}

.modal-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative;
    z-index: 1;
    margin: 0;
}

.modal-title i {
    font-size: 2rem;
    vertical-align: middle;
    margin-right: 0.75rem;
}

.modal-body {
    padding: 2.5rem;
    background: var(--card-bg);
}

.modal-footer {
    padding: 1.75rem 2.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-top: 2px solid #e2e8f0;
    border-radius: 0 0 25px 25px;
}

.dark-mode .modal-footer {
    background: linear-gradient(135deg, #1a2942 0%, #16213e 100%);
    border-top-color: var(--card-border);
}

.btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

.dark-mode .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

/* Dropdowns */
.dark-mode .dropdown-menu {
    background-color: var(--card-bg) !important;
    border-color: var(--card-border) !important;
    box-shadow: var(--card-shadow) !important;
}
.dark-mode .dropdown-item {
    color: var(--text-primary) !important;
}
.dark-mode .dropdown-item:hover {
    background-color: var(--table-hover-bg) !important;
    color: #c3c8ff !important;
}

/* Tooltip & Popover */
.dark-mode .tooltip-inner {
    background-color: var(--tooltip-bg) !important;
    color: var(--tooltip-text) !important;
}
.dark-mode .tooltip .tooltip-arrow::before {
    border-color: var(--tooltip-bg) !important;
}
.dark-mode .popover {
    background-color: var(--popover-bg) !important;
    border-color: var(--popover-border) !important;
}
.dark-mode .popover-header {
    background-color: var(--table-header-bg) !important;
    color: var(--text-primary) !important;
    border-color: var(--popover-border) !important;
}
.dark-mode .popover-body {
    color: var(--text-primary) !important;
}

/* Offcanvas */
.dark-mode .offcanvas {
    background-color: var(--offcanvas-bg) !important;
    border-color: var(--offcanvas-border) !important;
    color: var(--text-primary) !important;
}

/* Alerts */
.dark-mode .alert {
    border-color: transparent !important;
}
.dark-mode .alert-primary { background-color: rgba(83, 98, 158, 0.3) !important; color: #c3c8ff !important; }
.dark-mode .alert-success { background-color: rgba(25, 135, 84, 0.3) !important; color: #75f0b0 !important; }
.dark-mode .alert-danger { background-color: rgba(220, 53, 69, 0.3) !important; color: #f5a1aa !important; }
.dark-mode .alert-warning { background-color: rgba(255, 193, 7, 0.3) !important; color: #ffe69c !important; }
.dark-mode .alert-info { background-color: rgba(13, 202, 240, 0.3) !important; color: #a0e9ff !important; }

/* Pagination */
.dark-mode .page-link {
    background-color: var(--input-bg) !important;
    border-color: var(--input-border) !important;
    color: var(--text-primary) !important;
}
.dark-mode .page-item.active .page-link {
    background-color: #1b5e3f !important;
    border-color: #1b5e3f !important;
    color: white !important;
}

/* Breadcrumbs */
.dark-mode .breadcrumb {
    background-color: var(--card-bg) !important;
}
.dark-mode .breadcrumb-item a { color: #1b5e3f !important; }
.dark-mode .breadcrumb-item.active { color: var(--text-secondary) !important; }

/* Scrollbars (Webkit) */
.dark-mode ::-webkit-scrollbar {
    width: 8px;
}
.dark-mode ::-webkit-scrollbar-track {
    background: #16213e;
}
.dark-mode ::-webkit-scrollbar-thumb {
    background: #444;
    border-radius: 4px;
}
.dark-mode ::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Text */
.dark-mode .text-muted { color: var(--text-secondary) !important; }
.dark-mode h1, .dark-mode h2, .dark-mode h3,
.dark-mode h4, .dark-mode h5, .dark-mode h6,
.dark-mode p, .dark-mode span, .dark-mode div,
.dark-mode label, .dark-mode small {
    color: var(--text-primary) !important;
}

/* Borders */
.dark-mode .border { border-color: var(--card-border) !important; }

/* Backgrounds */
.dark-mode .bg-light { background-color: #2a2a3e !important; }
.dark-mode .bg-white { background-color: #1e1e2e !important; }
.dark-mode .bg-dark { background-color: #12121e !important; }

.dark-mode .filters-section,
.dark-mode .filter-card,
.dark-mode .filter-section,
.dark-mode .table-container,
.dark-mode .company-item,
.dark-mode .low-stock-item,
.dark-mode .welcome-banner,
.dark-mode .filter-indicator {
    background-color: var(--card-bg) !important;
    border-color: var(--card-border) !important;
    color: var(--text-primary) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,0.35) !important;
}

.dark-mode .filters-section .form-label,
.dark-mode .filter-card .form-label,
.dark-mode .filter-section .form-label,
.dark-mode .form-label {
    color: var(--text-primary) !important;
}

.dark-mode select option,
.dark-mode .form-select option {
    color: var(--text-primary) !important;
    background-color: var(--input-bg) !important;
}

.dark-mode .table-container {
    background-color: var(--table-bg) !important;
}

/* Progress */
.dark-mode .progress {
    background-color: #0f3460 !important;
}
.dark-mode .progress-bar {
    background-color: #1b5e3f !important;
}

/* Welcome Banner & Stock Ticker */
.welcome-banner {
    background: var(--welcome-bg);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    border-left: 5px solid var(--primary-color);
    color: var(--text-primary);
}

.dark-mode .welcome-banner {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    border-left-color: var(--secondary-color);
}

.stock-ticker {
    background: var(--stock-ticker-bg);
    color: var(--stock-ticker-text);
    padding: 0.75rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    overflow: hidden;
    white-space: nowrap;
    position: relative;
    min-width: 300px;
    max-width: 600px;
    height: 45px;
}

.stock-ticker i {
    flex-shrink: 0;
    font-size: 1.1rem;
}

.stock-ticker-wrapper {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.stock-ticker-content {
    display: inline-block;
    padding-left: 100%;
    animation: marquee 120s linear infinite;
}

@keyframes marquee {
    0% { transform: translateX(0); }
    100% { transform: translateX(-100%); }
}

.stock-ticker:hover .stock-ticker-content {
    animation-play-state: paused;
}

@media (max-width: 768px) {
    .stock-ticker {
        min-width: 250px;
        max-width: 100%;
    }
}
    :root {
        /* PRIMARY COLORS - FOREST GREEN & MINT */
        --primary-color: #1b5e3f;           /* Forest Green */
        --primary-dark: #0f3f28;            /* Darker Forest Green */
        --secondary-color: #2ecc71;         /* Mint Accent */
        --secondary-dark: #27ae5c;          /* Darker Mint */
        
        /* SIDEBAR VARIABLES - LIGHT MODE */
        --sidebar-width: 280px;
        --sidebar-bg: #ffffff;
        --sidebar-border: #e9ecef;
        --sidebar-text: #2c3e50;
        --sidebar-text-muted: #6c757d;
        --sidebar-hover: #f0f7f4;
        --sidebar-active: #e6f7f0;
        --sidebar-accent: var(--primary-color);
        --sidebar-gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
        
        /* CARD & TABLE VARIABLES - LIGHT MODE */
        --card-bg: #ffffff;
        --card-border: #e9ecef;
        --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        --table-bg: #ffffff;
        --table-header-bg: #f8f9fa;
        --table-border: #dee2e6;
        --table-stripe-bg: #f8f9fa;
        --table-hover-bg: #e6f7f0;
        --text-primary: #2c3e50;
        --text-secondary: #6c757d;
        --input-bg: #ffffff;
        --input-border: #ced4da;
        --badge-bg: #e9ecef;
        --badge-text: #495057;
        
        /* MODAL VARIABLES - LIGHT MODE */
        --modal-bg: #ffffff;
        --modal-border: #dee2e6;
        --modal-header-bg: var(--primary-color);
        --modal-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
        
        /* WELCOME BANNER & STOCK TICKER */
        --welcome-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        --stock-ticker-bg: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        --stock-ticker-text: #ffffff;
    }

    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background: var(--sidebar-bg);
        padding: 0;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1040;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 15px rgba(46, 204, 113, 0.1);
        border-right: 1px solid var(--sidebar-border);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        background: var(--sidebar-gradient);
        position: relative;
        overflow: hidden;
        border-bottom: none;
    }

    .sidebar-header::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%);
        pointer-events: none;
    }

    .brand-container {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .brand-icon {
        font-size: 2rem;
        color: #ffffff;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .navbar-brand {
        color: #ffffff !important;
        font-family: 'Playfair Display', serif;
        font-size: 1.55rem;
        text-decoration: none;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.8px;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .navbar-brand:hover {
        color: #ffffff !important;
    }

    .dark-mode .navbar-brand {
        color: #ffffff !important;
    }

    .dark-mode .navbar-brand:hover {
        color: #ffffff !important;
    }

    .user-profile {
        display: flex;
        align-items: center;
        padding: 1.5rem;
        gap: 1rem;
        border-bottom: 1px solid var(--sidebar-border);
        background: linear-gradient(135deg, rgba(83, 98, 158, 0.12) 0%, rgba(61, 75, 142, 0.08) 100%);
    }

    .user-avatar {
        font-size: 3rem;
        color: var(--sidebar-accent);
    }

    .user-info {
        flex: 1;
    }

    .user-name {
        color: var(--sidebar-text);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: 1rem;
        font-weight: 700;
        margin: 0 0 0.25rem 0;
        text-shadow: 0 1px 2px rgba(0,0,0,0.08);
        background: transparent;
        padding: 0;
    }

    .dark-mode .user-name {
        color: #e8eef2 !important;
        text-shadow: 0 1px 2px rgba(0,0,0,0.45);
        background: transparent !important;
    }

    .user-role {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        background: #1b5e3f !important;
        color: #ffffff !important;
        font-weight: 700;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        border-radius: 4px;
        display: inline-block;
    }

    /* ===== COLLAPSIBLE SIDEBAR ===== */
    .sidebar.collapsed {
        width: 70px;
    }

    .sidebar.collapsed .sidebar-header {
        padding: 1rem 0.75rem;
    }

    .sidebar.collapsed .brand-container {
        gap: 0;
    }

    .sidebar.collapsed .navbar-brand {
        display: none;
    }

    .sidebar.collapsed .brand-icon {
        font-size: 1.75rem;
        margin: 0 auto;
    }

    .sidebar.collapsed .user-profile {
        padding: 1rem 0.75rem;
        gap: 0;
        justify-content: center;
    }

    .sidebar.collapsed .user-avatar {
        font-size: 2rem;
    }

    .sidebar.collapsed .user-info {
        display: none;
    }

    .sidebar.collapsed .sidebar-menu {
        padding: 0.5rem 0;
    }

    .sidebar.collapsed .nav-link {
        width: 48px;
        min-height: 48px;
        margin: 0.35rem auto;
        padding: 0.75rem 0;
        justify-content: center;
        border-left: none;
        position: relative;
        border-radius: 999px;
    }

    .sidebar.collapsed .nav-link span {
        display: none;
    }

    .sidebar.collapsed .nav-link i {
        margin: 0;
        font-size: 1.2rem;
    }

    .sidebar.collapsed .nav-link::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 70px;
        background: var(--sidebar-text-muted);
        color: var(--sidebar-bg);
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
        z-index: 1050;
    }

    .sidebar.collapsed .nav-link.active {
        width: 48px;
        background-color: rgba(27, 94, 63, 0.18);
        color: var(--primary-color) !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    }

    .sidebar.collapsed .nav-link.active i {
        color: var(--primary-color) !important;
    }

    .sidebar.collapsed .nav-link:hover::after {
        opacity: 1;
    }

    .sidebar.collapsed .nav-divider {
        margin: 0.5rem 0;
    }

    .sidebar.collapsed .nav-section-title {
        display: none;
    }

    .sidebar.collapsed .sidebar-footer {
        padding: 1rem 0.75rem;
    }

    .sidebar.collapsed .sidebar-footer button {
        padding: 0.5rem;
        font-size: 0.9rem;
    }

    .sidebar.collapsed .sidebar-footer button span {
        display: none;
    }

    .sidebar.collapsed .sidebar-footer button i {
        margin: 0;
    }

    .sidebar-toggle-anchor {
        position: absolute;
        top: 50%;
        right: -26px;
        transform: translateY(-50%);
        z-index: 1051;
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .sidebar-toggle-anchor .sidebar-collapse-button {
        pointer-events: auto;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        transition: transform 0.25s ease, background-color 0.25s ease, border-color 0.25s ease;
        background: #1b5e3f;
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #ffffff;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.16);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .sidebar-toggle-anchor .sidebar-collapse-button:hover {
        background: #166242;
    }

    .dark-mode .sidebar-toggle-anchor .sidebar-collapse-button {
        background: rgba(255, 255, 255, 0.92);
        border-color: rgba(255, 255, 255, 0.6);
        color: #1b5e3f;
    }

    .dark-mode .sidebar-toggle-anchor .sidebar-collapse-button:hover {
        background: rgba(255, 255, 255, 1);
    }

    .sidebar.collapsed .sidebar-toggle-anchor {
        right: -26px;
    }

    .sidebar.collapsed .sidebar-collapse-button i {
        transform: rotate(180deg);
    }

    .sidebar-menu {

        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 1rem 0;
    }

    .sidebar-menu::-webkit-scrollbar {
        width: 4px;
    }

    .sidebar-menu::-webkit-scrollbar-track {
        background: var(--sidebar-hover);
    }

    .sidebar-menu::-webkit-scrollbar-thumb {
        background: var(--sidebar-text-muted);
        border-radius: 4px;
    }

    .nav-divider {
        height: 1px;
        background: var(--sidebar-border);
        margin: 0.5rem 1.5rem;
    }

    .nav-section-title {
        color: var(--sidebar-text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 0.5rem 1.5rem;
        margin-top: 0.5rem;
    }

    .nav-link {
        color: var(--sidebar-text);
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        transition: all 0.25s ease;
        border-left: 3px solid transparent;
        position: relative;
        text-decoration: none;
    }

    .nav-link:hover {
        color: var(--sidebar-accent) !important;
        background-color: var(--sidebar-hover);
        border-left-color: transparent;
        transform: none;
    }

    .nav-link.active {
        background-color: rgba(27, 94, 63, 0.14);
        color: var(--primary-color) !important;
        border-left-color: var(--primary-color);
        border-left-width: 4px;
        border-left-style: solid;
        font-weight: 700;
        border-radius: 999px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }

    .nav-link.active i {
        color: var(--primary-color) !important;
    }

    .nav-link i {
        font-size: 1.25rem;
        width: 1.5rem;
        margin-right: 0.75rem;
    }

    .nav-link span {
        font-size: 0.95rem;
        font-weight: 500;
    }

    .notification-badge {
        margin-left: auto;
        background: #dc3545;
        color: white;
        font-size: 0.7rem;
        padding: 0.15rem 0.4rem;
        border-radius: 10px;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }

    .sidebar-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--sidebar-border);
    }

    .btn-theme-toggle,
    .btn-logout {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border: 2px solid var(--sidebar-border);
        background: var(--sidebar-hover);
        color: var(--sidebar-text);
        transition: all 0.25s ease;
        font-weight: 600;
        border-radius: 8px;
    }

    .btn-theme-toggle.light-theme {
        background: #1b5e3f;
        border-color: #154a35;
        color: #ffffff;
    }

    .btn-theme-toggle.dark-theme {
        background: rgba(255, 255, 255, 0.92);
        border-color: rgba(255, 255, 255, 0.6);
        color: var(--primary-color);
    }

    .btn-theme-toggle:hover,
    .btn-logout:hover {
        background: var(--sidebar-active);
        color: var(--sidebar-accent);
        border-color: var(--sidebar-accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(46, 204, 113, 0.2);
    }

    .btn-logout {
        background: rgba(220, 53, 69, 0.1);
        border-color: rgba(220, 53, 69, 0.3);
        color: #dc3545;
    }

    .btn-logout:hover {
        background: rgba(220, 53, 69, 0.2);
        border-color: #dc3545;
        color: #dc3545;
    }

    .btn-mobile-toggle {
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1030;
        background: var(--sidebar-gradient);
        border: none;
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 10px;
        box-shadow: 0 6px 20px rgba(46, 204, 113, 0.3);
        font-size: 1.5rem;
        transition: all 0.3s ease;
    }

    .btn-mobile-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(46, 204, 113, 0.4);
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1035;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        display: block;
        opacity: 1;
    }

    .main-content {
        margin-left: var(--sidebar-width);
        padding: 1.25rem 1.5rem 1.5rem;
        transition: margin-left 0.35s ease;
        min-height: 100vh;
        width: calc(100% - var(--sidebar-width));
        max-width: 100%;
        box-sizing: border-box;
        background: transparent;
    }

    body.sidebar-collapsed .main-content {
        margin-left: 70px;
        width: calc(100% - 70px);
    }

    .page-header {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: #ffffff;
        padding: 1.5rem 1.5rem;
        border-radius: 15px;
        margin: 0 0 1.5rem;
        box-shadow: 0 12px 28px rgba(27,94,63,0.18);
    }

    .page-header h1,
    .page-header h2 {
        margin: 0;
        color: #ffffff;
    }

    .page-header .btn {
        color: #ffffff;
        border-color: rgba(255,255,255,0.22);
    }

    .dark-mode body {
        background-color: #0f1419 !important;
    }

    .dark-mode .main-content {
        background: transparent !important;
    }

    .dark-mode .page-header {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)) !important;
        color: #ffffff !important;
    }

    .dark-mode .page-header h1,
    .dark-mode .page-header h2 {
        color: #ffffff !important;
    }

    .dark-mode .page-header .btn {
        color: #ffffff !important;
        border-color: rgba(255,255,255,0.24) !important;
    }

    .dark-mode .card {
        background-color: var(--card-bg) !important;
        border-color: var(--card-border) !important;
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .card h5,
    .dark-mode .card p,
    .dark-mode .card .card-text,
    .dark-mode .card .card-title {
        color: var(--text-primary) !important;
    }

    .dark-mode .bg-white,
    .dark-mode .bg-light {
        background-color: #1a1f2e !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .form-control,
    .dark-mode .form-select {
        background-color: var(--input-bg) !important;
        border-color: var(--input-border) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .table,
    .dark-mode .table thead th,
    .dark-mode .table tbody td {
        background-color: var(--table-bg) !important;
        color: var(--text-primary) !important;
        border-color: var(--table-border) !important;
    }

    .dark-mode .navbar,
    .dark-mode .navbar-brand,
    .dark-mode .nav-link {
        color: #e0e0e0 !important;
    }

    /* ========================================
       DARK MODE STYLES FOR ALL ELEMENTS
       ======================================== */
    .dark-mode {
        background-color: #0f1419;
        color: #f8fafc;
        
        /* Dark Mode Variables - Forest Green Theme */
        --primary-color: #2ecc71;           /* Bright Mint in dark mode */
        --primary-dark: #27ae5c;
        --card-bg: #1a1f2e;
        --card-border: #2d3436;
        --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        --table-bg: #1a1f2e;
        --table-header-bg: #1b5e3f;
        --table-border: #2d3436;
        --table-stripe-bg: #151a23;
        --table-hover-bg: #254d38;
        --text-primary: #f8fafc;
        --text-secondary: #d0d8e0;
        --input-bg: #1a1f2e;
        --input-border: #2ecc71;
        --badge-bg: #1b5e3f;
        --badge-text: #2ecc71;
    }

    .dark-mode .sidebar {
        --sidebar-bg: #1a1f2e;
        --sidebar-border: #2d3436;
        --sidebar-text: #e8eef2;
        --sidebar-text-muted: #7c8491;
        --sidebar-hover: #254d38;
        --sidebar-active: #1b5e3f;
        --sidebar-gradient: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
    }

    .dark-mode .sidebar-header {
        background: var(--sidebar-gradient);
        box-shadow: 0 4px 20px rgba(46, 204, 113, 0.2);
    }

    .dark-mode .user-profile {
        background: linear-gradient(135deg, rgba(46, 204, 113, 0.15) 0%, rgba(27, 94, 63, 0.1) 100%);
        border-bottom: 1px solid rgba(46, 204, 113, 0.2);
    }

    .dark-mode .nav-link:hover,
    .dark-mode .nav-link.active {
        background-color: rgba(46, 204, 113, 0.2);
        color: #2ecc71 !important;
        border-left-color: #2ecc71;
    }

    /* Cards in Dark Mode */
    .dark-mode .card {
        background-color: var(--card-bg) !important;
        border-color: var(--card-border) !important;
        box-shadow: var(--card-shadow) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .card-header {
        background-color: var(--table-header-bg) !important;
        border-color: var(--card-border) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .card-body {
        color: var(--text-primary) !important;
    }

    .dark-mode .card-title,
    .dark-mode .card-text {
        color: var(--text-primary) !important;
    }

    /* Tables in Dark Mode */
    .dark-mode .table {
        background-color: var(--table-bg) !important;
        color: var(--text-primary) !important;
        border-color: var(--table-border) !important;
    }

    .dark-mode .table thead th {
        background-color: var(--table-header-bg) !important;
        color: var(--text-primary) !important;
        border-color: var(--table-border) !important;
        font-weight: 600 !important;
    }

    .dark-mode .table tbody td {
        background-color: var(--table-bg) !important;
        color: var(--text-primary) !important;
        border-color: var(--table-border) !important;
    }

    .dark-mode .table-striped tbody tr:nth-of-type(odd) {
        background-color: var(--table-stripe-bg) !important;
    }

    .dark-mode .table-hover tbody tr:hover {
        background-color: var(--table-hover-bg) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .table-bordered {
        border-color: var(--table-border) !important;
    }

    .dark-mode .table-bordered td,
    .dark-mode .table-bordered th {
        border-color: var(--table-border) !important;
    }

    /* Form Controls in Dark Mode */
    .dark-mode .form-control,
    .dark-mode .form-select {
        background-color: var(--input-bg) !important;
        border-color: var(--input-border) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .form-control:focus,
    .dark-mode .form-select:focus {
        background-color: var(--input-bg) !important;
        border-color: #2ecc71 !important;
        color: var(--text-primary) !important;
        box-shadow: 0 0 0 0.25rem rgba(46, 204, 113, 0.25) !important;
    }

    .dark-mode .form-control::placeholder {
        color: var(--text-secondary) !important;
        opacity: 0.7;
    }

    .dark-mode .form-label {
        color: var(--text-primary) !important;
    }

    /* Badges in Dark Mode */
    .dark-mode .badge {
        background-color: var(--badge-bg) !important;
        color: var(--badge-text) !important;
    }

    .dark-mode .badge.bg-primary {
        background-color: #1b5e3f !important;
        color: #2ecc71 !important;
    }

    .dark-mode .badge.bg-success {
        background-color: #1b5e3f !important;
        color: #2ecc71 !important;
    }

    .dark-mode .badge.bg-danger {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }

    .dark-mode .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #000000 !important;
    }

    .dark-mode .badge.bg-info {
        background-color: #0dcaf0 !important;
        color: #000000 !important;
    }

    /* Buttons in Dark Mode */
    .dark-mode .btn-light {
        background-color: var(--input-bg) !important;
        border-color: var(--input-border) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .btn-light:hover {
        background-color: var(--table-hover-bg) !important;
        border-color: #1b5e3f !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .btn-outline-secondary {
        color: var(--text-primary) !important;
        border-color: var(--input-border) !important;
    }

    .dark-mode .btn-outline-secondary:hover {
        background-color: var(--input-bg) !important;
        color: var(--text-primary) !important;
    }

    /* Modals in Dark Mode */
    .dark-mode .modal-content {
        background-color: #16213e !important;
        color: #e0e0e0 !important;
        border: 1px solid #0f3460 !important;
    }

    .dark-mode .modal-header {
        border-bottom-color: #0f3460 !important;
    }

    .dark-mode .modal-footer {
        border-top-color: #0f3460 !important;
    }

    .dark-mode .modal-body {
        color: #e0e0e0 !important;
    }

    .dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    /* Dropdown Menus in Dark Mode */
    .dark-mode .dropdown-menu {
        background-color: var(--card-bg) !important;
        border-color: var(--card-border) !important;
    }

    .dark-mode .dropdown-item {
        color: var(--text-primary) !important;
    }

    .dark-mode .dropdown-item:hover,
    .dark-mode .dropdown-item:focus {
        background-color: var(--table-hover-bg) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .dropdown-divider {
        border-color: var(--card-border) !important;
    }

    /* Pagination in Dark Mode */
    .dark-mode .pagination .page-link {
        background-color: var(--input-bg) !important;
        border-color: var(--input-border) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .pagination .page-link:hover {
        background-color: var(--table-hover-bg) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .pagination .page-item.active .page-link {
        background-color: #1b5e3f !important;
        border-color: #2ecc71 !important;
        color: #2ecc71 !important;
    }

    /* Alerts in Dark Mode */
    .dark-mode .alert {
        border-color: var(--card-border) !important;
    }

    .dark-mode .alert-primary {
        background-color: rgba(46, 204, 113, 0.2) !important;
        border-color: #2ecc71 !important;
        color: #2ecc71 !important;
    }
        color: #c3c8ff !important;
    }

    .dark-mode .alert-success {
        background-color: rgba(25, 135, 84, 0.2) !important;
        border-color: #198754 !important;
        color: #75f0b0 !important;
    }

    .dark-mode .alert-danger {
        background-color: rgba(220, 53, 69, 0.2) !important;
        border-color: #dc3545 !important;
        color: #f5a1aa !important;
    }

    .dark-mode .alert-warning {
        background-color: rgba(255, 193, 7, 0.2) !important;
        border-color: #ffc107 !important;
        color: #ffe69c !important;
    }

    /* List Groups in Dark Mode */
    .dark-mode .list-group-item {
        background-color: var(--card-bg) !important;
        border-color: var(--card-border) !important;
        color: var(--text-primary) !important;
    }

    .dark-mode .list-group-item:hover {
        background-color: var(--table-hover-bg) !important;
    }

    /* Progress Bars in Dark Mode */
    .dark-mode .progress {
        background-color: var(--input-bg) !important;
    }

    /* Breadcrumbs in Dark Mode */
    .dark-mode .breadcrumb {
        background-color: var(--card-bg) !important;
    }

    .dark-mode .breadcrumb-item a {
        color: #2ecc71 !important;
    }

    .dark-mode .breadcrumb-item.active {
        color: var(--text-secondary) !important;
    }

    /* Text Colors in Dark Mode */
    .dark-mode .text-muted {
        color: var(--text-secondary) !important;
    }

    .dark-mode .text-dark {
        color: var(--text-primary) !important;
    }

    .dark-mode h1, .dark-mode h2, .dark-mode h3, 
    .dark-mode h4, .dark-mode h5, .dark-mode h6 {
        color: var(--text-primary) !important;
    }

    .dark-mode p, .dark-mode span, .dark-mode div {
        color: inherit;
    }

    /* Border Colors in Dark Mode */
    .dark-mode .border {
        border-color: var(--card-border) !important;
    }

    .dark-mode .border-top {
        border-top-color: var(--card-border) !important;
    }

    .dark-mode .border-bottom {
        border-bottom-color: var(--card-border) !important;
    }

    .dark-mode .border-start {
        border-left-color: var(--card-border) !important;
    }

    .dark-mode .border-end {
        border-right-color: var(--card-border) !important;
    }

    /* Mobile Responsive */
    @media (max-width: 991.98px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
            padding: 5rem 1rem 1rem;
        }

        .btn-mobile-toggle {
            display: block;
        }
    }

    @media (min-width: 992px) {
        .btn-mobile-toggle {
            display: none;
        }

        .sidebar-overlay {
            display: none !important;
        }
    }

    /* Modal Enhancements */
    .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        border-bottom: none;
        padding: 1.5rem;
        border-radius: 15px 15px 0 0;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        border-top: none;
        padding: 1rem 1.5rem 1.5rem;
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(27, 94, 63, 0.06), transparent 34rem),
            #f5f7f7 !important;
    }

    body.dark-mode,
    .dark-mode body {
        background:
            radial-gradient(circle at top left, rgba(46, 204, 113, 0.08), transparent 34rem),
            #0f1419 !important;
    }

    .main-content,
    body.sidebar-collapsed .main-content {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-height: calc(100vh - 108px);
        padding: 1rem 1.5rem 1.5rem !important;
        margin-left: auto !important;
        width: 100% !important;
        max-width: 1760px !important;
        margin-right: auto !important;
        box-sizing: border-box;
    }

    .admin-legacy-header-hidden,
    .main-content > .page-header.admin-legacy-header-hidden,
    .main-content > h2.admin-legacy-header-hidden {
        display: none !important;
    }

    .main-content > .page-header,
    .main-content > h2:first-child,
    .main-content > .d-flex.justify-content-between.align-items-center.mb-4:first-child {
        display: none !important;
    }

    .main-content > .card,
    .main-content .filter-card,
    .main-content .settings-section,
    .main-content .filters-section,
    .main-content .chat-container {
        border: 1px solid rgba(27, 94, 63, 0.12) !important;
        border-radius: 14px !important;
        background:
            linear-gradient(135deg, rgba(27, 94, 63, 0.04), rgba(15, 63, 40, 0.015)),
            #ffffff !important;
        box-shadow: 0 12px 30px rgba(15, 63, 40, 0.08) !important;
    }

    .main-content > .row {
        row-gap: 1rem;
    }

    .main-content .card-body {
        color: #334155;
    }

    .main-content .btn,
    .modal .btn,
    .dropdown-menu .btn {
        border-radius: 12px !important;
        font-weight: 800 !important;
    }

    .main-content .btn-primary,
    .main-content .btn-success,
    .modal .btn-primary,
    .modal .btn-success {
        border: 0 !important;
        color: #ffffff !important;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
    }

    .main-content .btn-warning,
    .main-content .btn-info,
    .main-content .btn-danger,
    .modal .btn-warning,
    .modal .btn-info,
    .modal .btn-danger {
        border: 1px solid rgba(27, 94, 63, 0.14) !important;
        color: #0f3f28 !important;
        background: rgba(27, 94, 63, 0.08) !important;
    }

    .main-content .btn-outline-primary,
    .main-content .btn-outline-success,
    .main-content .btn-outline-info,
    .main-content .btn-outline-warning,
    .main-content .btn-outline-danger,
    .main-content .btn-outline-secondary,
    .modal .btn-outline-primary,
    .modal .btn-outline-success,
    .modal .btn-outline-info,
    .modal .btn-outline-warning,
    .modal .btn-outline-danger,
    .modal .btn-outline-secondary,
    .modal .btn-secondary {
        border: 1px solid rgba(27, 94, 63, 0.16) !important;
        color: #0f3f28 !important;
        background: rgba(27, 94, 63, 0.06) !important;
    }

    .main-content .btn:hover,
    .modal .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(15, 63, 40, 0.12) !important;
    }

    .dropdown-toggle::after,
    .admin-nav-button::after {
        color: currentColor;
        opacity: 0.8;
    }

    .dropdown-menu {
        border: 1px solid rgba(27, 94, 63, 0.14) !important;
        border-radius: 14px !important;
        box-shadow: 0 18px 48px rgba(15, 63, 40, 0.18) !important;
    }

    .dropdown-item {
        border-radius: 10px;
        color: #334155 !important;
        font-weight: 700;
    }

    .dropdown-item:hover,
    .dropdown-item:focus {
        color: #0f3f28 !important;
        background: rgba(27, 94, 63, 0.08) !important;
    }

    .form-control,
    .form-select,
    .input-group-text {
        border-color: rgba(27, 94, 63, 0.16) !important;
        border-radius: 12px !important;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #1b5e3f !important;
        box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.14) !important;
    }

    .main-content .filter-section,
    .main-content .filters-section,
    .main-content .filter-card {
        margin-bottom: 1rem !important;
    }

    .main-content .stats-card,
    .main-content .card {
        height: auto;
    }

    .main-content .table-container,
    .main-content .table-responsive {
        max-height: clamp(360px, calc(100vh - 330px), 680px) !important;
    }

    .main-content .chat-container {
        min-height: clamp(440px, calc(100vh - 260px), 760px);
    }

    .main-content .accordion,
    .main-content .settings-section {
        width: 100%;
    }

    .main-content > *:last-child {
        margin-bottom: 0 !important;
    }

    .main-content .card:has(.table),
    .main-content .table-container,
    .main-content .table-responsive {
        border: 1px solid rgba(27, 94, 63, 0.12) !important;
        border-radius: 14px !important;
        background:
            linear-gradient(135deg, rgba(27, 94, 63, 0.045), rgba(15, 63, 40, 0.015)),
            #ffffff !important;
        box-shadow: 0 12px 30px rgba(15, 63, 40, 0.08) !important;
        overflow: auto;
    }

    .main-content .card:has(.table) {
        overflow: hidden;
    }

    .main-content .table {
        margin-bottom: 0 !important;
        border-collapse: separate;
        border-spacing: 0;
        background: transparent !important;
    }

    .main-content .table thead th {
        padding: 0.95rem 1rem !important;
        border: 0 !important;
        color: #ffffff !important;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
        font-size: 0.78rem;
        font-weight: 800;
        letter-spacing: 0;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .main-content .table thead th:first-child {
        border-top-left-radius: 12px;
    }

    .main-content .table thead th:last-child {
        border-top-right-radius: 12px;
    }

    .main-content .table tbody td {
        padding: 0.9rem 1rem !important;
        border-color: rgba(15, 63, 40, 0.08) !important;
        color: #334155 !important;
        background: rgba(255, 255, 255, 0.74) !important;
        vertical-align: middle;
    }

    .main-content .table tbody tr:nth-child(even) td {
        background: rgba(248, 250, 252, 0.86) !important;
    }

    .main-content .table tbody tr:hover td {
        color: #0f3f28 !important;
        background: rgba(27, 94, 63, 0.08) !important;
    }

    .main-content .table .btn {
        padding: 0.38rem 0.58rem !important;
        font-size: 0.82rem !important;
    }

    .status-badge,
    .role-badge,
    .payment-badge,
    .badge {
        border-radius: 999px !important;
        padding: 0.38rem 0.68rem !important;
        font-weight: 800 !important;
        letter-spacing: 0 !important;
    }

    .admin-table-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.85rem;
        flex-wrap: wrap;
        margin-top: 0.9rem;
        padding: 0.85rem 1rem;
        border: 1px solid rgba(27, 94, 63, 0.12);
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 10px 26px rgba(15, 63, 40, 0.07);
    }

    .admin-table-pagination.is-empty {
        display: none;
    }

    .admin-page-size {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        color: #64748b;
        font-size: 0.86rem;
        font-weight: 700;
    }

    .admin-page-size select {
        min-width: 78px;
        border: 1px solid rgba(27, 94, 63, 0.16);
        border-radius: 10px;
        color: #0f3f28;
        background: rgba(27, 94, 63, 0.06);
        font-weight: 800;
    }

    .admin-page-summary {
        color: #64748b;
        font-size: 0.86rem;
        font-weight: 700;
    }

    .admin-page-buttons {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .admin-page-btn {
        min-width: 36px;
        height: 36px;
        border: 1px solid rgba(27, 94, 63, 0.14);
        border-radius: 10px;
        color: #0f3f28;
        background: rgba(27, 94, 63, 0.07);
        font-weight: 800;
    }

    .admin-page-btn:hover:not(:disabled),
    .admin-page-btn.active {
        color: #ffffff;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
    }

    .admin-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .logout-modal {
        border: 1px solid rgba(27, 94, 63, 0.14);
        border-radius: 18px;
        overflow: hidden;
        background:
            linear-gradient(135deg, rgba(27, 94, 63, 0.06), rgba(15, 63, 40, 0.02)),
            #ffffff;
        max-width: 420px;
        margin: 0 auto;
        box-shadow: 0 24px 60px rgba(15, 63, 40, 0.18);
    }

    .modal-content:not(.logout-modal) {
        border: 1px solid rgba(27, 94, 63, 0.14) !important;
        border-radius: 18px !important;
        overflow: hidden;
        background:
            linear-gradient(135deg, rgba(27, 94, 63, 0.045), rgba(15, 63, 40, 0.015)),
            #ffffff !important;
        box-shadow: 0 24px 60px rgba(15, 63, 40, 0.18) !important;
    }

    .modal-header,
    .modal-header.bg-primary,
    .modal-header.bg-warning,
    .modal-header.bg-info,
    .modal-header.bg-danger,
    .modal-header.bg-success {
        border: 0 !important;
        color: #ffffff !important;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
        border-radius: 18px 18px 0 0 !important;
        box-shadow: none !important;
    }

    .modal-title,
    .modal-header .modal-title,
    .modal-header h5 {
        color: #ffffff !important;
        font-weight: 800 !important;
        letter-spacing: 0 !important;
        text-shadow: none !important;
    }

    .modal-body {
        background: transparent !important;
    }

    .modal-footer {
        border-top: 1px solid rgba(27, 94, 63, 0.10) !important;
        background: rgba(248, 250, 252, 0.72) !important;
    }

    .logout-banner {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        color: #ffffff;
        font-weight: 700;
        border-bottom: 0;
    }

    .logout-banner i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.15);
        font-size: 1.05rem;
    }

    .logout-modal-body {
        padding: 1.35rem 1.35rem 0.75rem;
    }

    .logout-title h5 {
        margin: 0;
        color: #0f172a;
        font-size: 1.2rem;
        font-weight: 800;
    }

    .logout-copy {
        margin: 0.45rem 0 0;
        color: #64748b;
        line-height: 1.55;
    }

    .logout-user-card {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        margin-top: 1.15rem;
        padding: 1rem;
        border-radius: 14px;
        background: #ffffff;
        border: 1px solid rgba(27, 94, 63, 0.12);
        box-shadow: inset 0 0 0 1px rgba(27, 94, 63, 0.03);
    }

    .logout-user-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        color: #ffffff;
        font-weight: 800;
        font-size: 1rem;
    }

    .logout-user-name {
        color: #1f2937;
        font-weight: 700;
        font-size: 1rem;
    }

    .logout-user-role {
        color: #6b7280;
        font-size: 0.9rem;
    }

    .logout-modal-footer {
        display: flex;
        gap: 0.75rem;
        justify-content: space-between;
        padding: 0.9rem 1.35rem 1.35rem;
        background: transparent;
        border-top: none;
    }

    .btn-cancel {
        border: 1px solid rgba(27, 94, 63, 0.14);
        border-radius: 12px;
        background: #ffffff;
        color: #0f3f28;
        font-weight: 800;
    }

    .btn-cancel:hover {
        background: #f9fafb;
    }

    .btn-logout-confirm {
        border-radius: 12px;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        border: none;
        color: #ffffff;
        font-weight: 800;
    }

    .btn-logout-confirm:hover {
        background: #17664d;
    }

    .dark-mode .logout-modal {
        border-color: rgba(46, 204, 113, 0.18);
        background:
            linear-gradient(135deg, rgba(46, 204, 113, 0.10), rgba(15, 63, 40, 0.10)),
            #111827;
        color: #d7e0f5;
    }

    .dark-mode .logout-banner {
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        color: #ffffff;
    }

    .dark-mode .logout-title h5 {
        color: #ffffff;
    }

    .dark-mode .logout-copy {
        color: #cbd5e1;
    }

    .dark-mode .logout-user-card {
        background: #0f172a;
        border-color: rgba(46, 204, 113, 0.16);
        color: #d7e0f5;
    }

    .dark-mode .logout-user-name {
        color: #ffffff;
    }

    .dark-mode .logout-user-role {
        color: #a8b6d8;
    }

    .dark-mode .btn-cancel {
        border-color: rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.04);
        color: #f8fafc;
    }

    .dark-mode .btn-cancel:hover {
        background: rgba(255,255,255,0.08);
    }

    .dark-mode .btn-logout-confirm {
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
    }

    .dark-mode .main-content > .card,
    .dark-mode .main-content .filter-card,
    .dark-mode .main-content .settings-section,
    .dark-mode .main-content .filters-section,
    .dark-mode .main-content .chat-container {
        border-color: rgba(46, 204, 113, 0.16) !important;
        background:
            linear-gradient(135deg, rgba(46, 204, 113, 0.09), rgba(15, 63, 40, 0.08)),
            #111827 !important;
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.28) !important;
        color: #e2e8f0 !important;
    }

    .dark-mode .main-content .card-body,
    .dark-mode .main-content .settings-section h5,
    .dark-mode .main-content .filters-section,
    .dark-mode .main-content .filter-card {
        color: #e2e8f0 !important;
    }

    .dark-mode .form-control,
    .dark-mode .form-select,
    .dark-mode .input-group-text {
        border-color: rgba(46, 204, 113, 0.22) !important;
        color: #e2e8f0 !important;
        background: #0f172a !important;
    }

    .dark-mode .dropdown-menu,
    .dark-mode .modal-content:not(.logout-modal) {
        border-color: rgba(46, 204, 113, 0.18) !important;
        background:
            linear-gradient(135deg, rgba(46, 204, 113, 0.09), rgba(15, 63, 40, 0.08)),
            #111827 !important;
        color: #e2e8f0 !important;
    }

    .dark-mode .dropdown-item {
        color: #e2e8f0 !important;
    }

    .dark-mode .dropdown-item:hover,
    .dark-mode .dropdown-item:focus {
        color: #6ee7b7 !important;
        background: rgba(46, 204, 113, 0.12) !important;
    }

    .dark-mode .modal-footer {
        border-top-color: rgba(46, 204, 113, 0.14) !important;
        background: #0f172a !important;
    }

    .dark-mode .main-content .btn-warning,
    .dark-mode .main-content .btn-info,
    .dark-mode .main-content .btn-danger,
    .dark-mode .main-content .btn-outline-primary,
    .dark-mode .main-content .btn-outline-success,
    .dark-mode .main-content .btn-outline-info,
    .dark-mode .main-content .btn-outline-warning,
    .dark-mode .main-content .btn-outline-danger,
    .dark-mode .main-content .btn-outline-secondary,
    .dark-mode .modal .btn-warning,
    .dark-mode .modal .btn-info,
    .dark-mode .modal .btn-danger,
    .dark-mode .modal .btn-secondary,
    .dark-mode .modal .btn-outline-primary,
    .dark-mode .modal .btn-outline-success,
    .dark-mode .modal .btn-outline-info,
    .dark-mode .modal .btn-outline-warning,
    .dark-mode .modal .btn-outline-danger,
    .dark-mode .modal .btn-outline-secondary {
        border-color: rgba(46, 204, 113, 0.22) !important;
        color: #6ee7b7 !important;
        background: rgba(46, 204, 113, 0.12) !important;
    }

    .dark-mode .main-content .card:has(.table),
    .dark-mode .main-content .table-container,
    .dark-mode .main-content .table-responsive {
        border-color: rgba(46, 204, 113, 0.16) !important;
        background:
            linear-gradient(135deg, rgba(46, 204, 113, 0.10), rgba(15, 63, 40, 0.10)),
            #111827 !important;
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.28) !important;
    }

    .dark-mode .main-content .table tbody td {
        color: #e2e8f0 !important;
        background: #111827 !important;
        border-color: #1f2937 !important;
    }

    .dark-mode .main-content .table tbody tr:nth-child(even) td {
        background: #0f172a !important;
    }

    .dark-mode .main-content .table tbody tr:hover td {
        color: #6ee7b7 !important;
        background: rgba(46, 204, 113, 0.12) !important;
    }

    .dark-mode .admin-table-pagination {
        border-color: rgba(46, 204, 113, 0.16);
        background: #111827;
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.28);
    }

    .dark-mode .admin-page-size,
    .dark-mode .admin-page-summary {
        color: #94a3b8;
    }

    .dark-mode .admin-page-size select,
    .dark-mode .admin-page-btn {
        border-color: rgba(46, 204, 113, 0.2);
        color: #6ee7b7;
        background: rgba(46, 204, 113, 0.12);
    }

    @media (max-width: 991.98px) {
        .main-content,
        body.sidebar-collapsed .main-content {
            min-height: calc(100vh - 160px);
            padding: 1rem !important;
        }

        .main-content .table-container,
        .main-content .table-responsive {
            max-height: clamp(320px, calc(100vh - 360px), 620px) !important;
        }
    }

    @media (max-width: 575.98px) {
        .main-content,
        body.sidebar-collapsed .main-content {
            padding: 0.85rem 0.75rem 1rem !important;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const themeToggle = document.getElementById('theme-toggle');
        const confirmLogoutBtn = document.getElementById('confirm-logout-btn');
        const liveTime = document.getElementById('admin-live-time');
        const pageActions = document.getElementById('admin-page-actions');

        // === HEADER CLOCK ===
        const updateHeaderTime = () => {
            if (!liveTime) return;
            liveTime.textContent = new Intl.DateTimeFormat('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            }).format(new Date());
        };
        updateHeaderTime();
        setInterval(updateHeaderTime, 1000);

        // === LEGACY PAGE HEADER CLEANUP ===
        const moveLegacyActions = () => {
            if (!pageActions) return;
            const legacyHeaders = [
                ...document.querySelectorAll('.main-content > .page-header'),
                ...document.querySelectorAll('.main-content > .d-flex.justify-content-between.align-items-center.mb-4:first-child')
            ];

            legacyHeaders.forEach(header => {
                header.querySelectorAll(':scope > button, :scope > a, :scope > .btn, :scope > div > button, :scope > div > a').forEach(action => {
                    if (action.closest('.admin-page-actions')) return;
                    action.classList.add('admin-moved-action');
                    pageActions.appendChild(action);
                });
                header.classList.add('admin-legacy-header-hidden');
                header.setAttribute('aria-hidden', 'true');
            });

            const firstHeading = document.querySelector('.main-content > h2:first-child');
            if (firstHeading) {
                firstHeading.classList.add('admin-legacy-header-hidden');
                firstHeading.setAttribute('aria-hidden', 'true');
            }
        };
        moveLegacyActions();

        // === ACTIVE PAGE HIGHLIGHT ===
        const currentPage = window.location.pathname.split('/').pop();
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });

        // === MOBILE SIDEBAR TOGGLE ===
        const toggleSidebar = () => {
            if (!sidebar || !sidebarOverlay) return;
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        };

        [mobileSidebarToggle, sidebarToggle, sidebarOverlay].forEach(el => {
            if (el) el.addEventListener('click', toggleSidebar);
        });

        // Close on nav click (mobile)
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) toggleSidebar();
            });
        });

        // === DESKTOP SIDEBAR COLLAPSE ===
        const desktopSidebarToggle = document.getElementById('desktop-sidebar-toggle');
        if (desktopSidebarToggle) {
            // Restore sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }

            desktopSidebarToggle.addEventListener('click', (e) => {
                e.preventDefault();
                sidebar.classList.toggle('collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('sidebar-collapsed', isCollapsed);
                document.body.classList.toggle('sidebar-collapsed', isCollapsed);
            });
        }

        // === CURRENCY FORMATTING ===
        window.formatCurrency = function(amount) {
            if (amount === null || amount === undefined) return '₱0.00';
            const num = parseFloat(amount);
            if (isNaN(num)) return '₱0.00';
            return '₱' + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        };

        // Auto-format currency elements
        document.querySelectorAll('[data-currency]').forEach(el => {
            const amount = el.getAttribute('data-currency');
            el.textContent = window.formatCurrency(amount);
            el.classList.add('currency');
        });

        // === THEME TOGGLE - ENHANCED ===
        if (themeToggle) {
            const currentTheme = localStorage.getItem('theme') || 'light';
            if (currentTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.body.classList.add('dark-mode');
                themeToggle.innerHTML = '<i class="bi bi-sun-fill"></i>';
                themeToggle.setAttribute('title', 'Switch to light mode');
                themeToggle.setAttribute('aria-label', 'Switch to light mode');
                themeToggle.classList.add('dark-theme');
                themeToggle.classList.remove('light-theme');
            } else {
                themeToggle.classList.add('light-theme');
                themeToggle.classList.remove('dark-theme');
            }

            themeToggle.addEventListener('click', (event) => {
                event.stopImmediatePropagation();
                const isDark = document.documentElement.classList.toggle('dark-mode');
                document.body.classList.toggle('dark-mode', isDark);

                themeToggle.innerHTML = isDark
                    ? '<i class="bi bi-sun-fill"></i>'
                    : '<i class="bi bi-moon-stars"></i>';
                themeToggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');

                themeToggle.classList.toggle('dark-theme', isDark);
                themeToggle.classList.toggle('light-theme', !isDark);

                localStorage.setItem('theme', isDark ? 'dark' : 'light');

                // Trigger reflow for transition
                void document.body.offsetHeight;
            }, true);
        }

        // === LOGOUT ===
        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener('click', () => {
                confirmLogoutBtn.disabled = true;
                confirmLogoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';

                fetch('api/phar_logout.php', { method: 'POST', credentials: 'include' })
                    .then(r => r.json())
                    .then(data => {
                        window.location.href = data.redirect || '../index.php';
                    })
                    .catch(() => {
                        window.location.href = '../index.php';
                    });
            });
        }

        // === NOTIFICATIONS ===
        const loadNotificationCount = () => {
            fetch('api/phar_notifications.php?unread_count=true')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notif-badge');
                    if (badge) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = data.count > 0 ? 'inline-block' : 'none';
                    }
                })
                .catch(() => {});
        };
        loadNotificationCount();
        setInterval(loadNotificationCount, 30000);

        // === SHARED TABLE PAGINATION ===
        const pageSizeOptions = [10, 15, 20];
        const tableStates = new WeakMap();

        const getTableKey = table => {
            if (!table.dataset.adminPagerKey) {
                table.dataset.adminPagerKey = table.id || `admin-table-${Math.random().toString(36).slice(2)}`;
            }
            return table.dataset.adminPagerKey;
        };

        const shouldPaginateTable = table => {
            if (!table || table.closest('.modal')) return false;
            if (!table.closest('.main-content')) return false;
            if (table.dataset.adminNoPagination === 'true') return false;
            if (table.closest('.admin-table-card')?.querySelector('.admin-table-footer nav[id]')) return false;
            return Boolean(table.tBodies && table.tBodies[0]);
        };

        const resetPagerHiddenRows = rows => {
            rows.forEach(row => {
                if (row.dataset.adminPagerHidden === 'true') {
                    row.style.display = row.dataset.adminPagerPreviousDisplay || '';
                    delete row.dataset.adminPagerHidden;
                    delete row.dataset.adminPagerPreviousDisplay;
                }
            });
        };

        const getExternallyVisibleRows = tbody => {
            const rows = Array.from(tbody.rows);
            resetPagerHiddenRows(rows);
            return rows.filter(row => row.style.display !== 'none' && !row.classList.contains('d-none'));
        };

        const createPagination = table => {
            const existing = table.parentElement?.nextElementSibling;
            if (existing?.classList.contains('admin-table-pagination')) return existing;

            const pager = document.createElement('div');
            pager.className = 'admin-table-pagination';
            pager.innerHTML = `
                <label class="admin-page-size">
                    <span>Rows</span>
                    <select class="form-select form-select-sm" aria-label="Rows per page">
                        ${pageSizeOptions.map(size => `<option value="${size}">${size}</option>`).join('')}
                    </select>
                </label>
                <div class="admin-page-summary" aria-live="polite"></div>
                <div class="admin-page-buttons"></div>
            `;

            const tableWrapper = table.closest('.table-container, .table-responsive') || table;
            tableWrapper.insertAdjacentElement('afterend', pager);
            return pager;
        };

        const renderPagination = (table) => {
            if (!shouldPaginateTable(table)) return;

            const tbody = table.tBodies[0];
            const pager = createPagination(table);
            const key = getTableKey(table);
            const savedSize = parseInt(localStorage.getItem(`${key}-page-size`), 10);
            const state = tableStates.get(table) || {
                page: 1,
                pageSize: pageSizeOptions.includes(savedSize) ? savedSize : 10
            };

            const rows = getExternallyVisibleRows(tbody);
            const totalRows = rows.length;
            const totalPages = Math.max(1, Math.ceil(totalRows / state.pageSize));
            state.page = Math.min(Math.max(1, state.page), totalPages);
            tableStates.set(table, state);

            pager.classList.toggle('is-empty', totalRows === 0);
            const select = pager.querySelector('select');
            const summary = pager.querySelector('.admin-page-summary');
            const buttons = pager.querySelector('.admin-page-buttons');
            select.value = String(state.pageSize);

            rows.forEach((row, index) => {
                const start = (state.page - 1) * state.pageSize;
                const end = start + state.pageSize;
                const shouldShow = index >= start && index < end;
                if (!shouldShow) {
                    row.dataset.adminPagerPreviousDisplay = row.style.display || '';
                    row.dataset.adminPagerHidden = 'true';
                    row.style.display = 'none';
                }
            });

            const startRow = totalRows === 0 ? 0 : ((state.page - 1) * state.pageSize) + 1;
            const endRow = Math.min(totalRows, state.page * state.pageSize);
            summary.textContent = totalRows ? `Showing ${startRow}-${endRow} of ${totalRows}` : 'No rows to show';

            buttons.innerHTML = '';
            const makeButton = (label, page, disabled = false, active = false) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = `admin-page-btn${active ? ' active' : ''}`;
                button.textContent = label;
                button.disabled = disabled;
                button.addEventListener('click', () => {
                    state.page = page;
                    renderPagination(table);
                });
                buttons.appendChild(button);
            };

            makeButton('‹', state.page - 1, state.page <= 1);
            for (let page = 1; page <= totalPages; page++) {
                if (totalPages > 5 && page !== 1 && page !== totalPages && Math.abs(page - state.page) > 1) {
                    if (page === 2 || page === totalPages - 1) {
                        const dots = document.createElement('span');
                        dots.className = 'admin-page-summary';
                        dots.textContent = '...';
                        buttons.appendChild(dots);
                    }
                    continue;
                }
                makeButton(String(page), page, false, page === state.page);
            }
            makeButton('›', state.page + 1, state.page >= totalPages);

            select.onchange = () => {
                state.pageSize = parseInt(select.value, 10);
                state.page = 1;
                localStorage.setItem(`${key}-page-size`, state.pageSize);
                renderPagination(table);
            };
        };

        const refreshAllPaginators = () => {
            document.querySelectorAll('.main-content table').forEach(table => {
                if (shouldPaginateTable(table)) renderPagination(table);
            });
        };

        const schedulePaginatorRefresh = (() => {
            let refreshTimer = null;
            return () => {
                clearTimeout(refreshTimer);
                refreshTimer = setTimeout(refreshAllPaginators, 120);
            };
        })();

        refreshAllPaginators();
        const tableObserver = new MutationObserver(schedulePaginatorRefresh);
        document.querySelectorAll('.main-content table tbody').forEach(tbody => {
            tableObserver.observe(tbody, { childList: true, subtree: false });
        });
        document.querySelector('.main-content')?.addEventListener('input', schedulePaginatorRefresh);
        document.querySelector('.main-content')?.addEventListener('change', schedulePaginatorRefresh);
        setTimeout(refreshAllPaginators, 500);

        // === SIDEBAR SCROLL POSITION PERSISTENCE ===
        const sidebarMenuElement = document.querySelector('.sidebar-menu');
        if (sidebarMenuElement) {
            // Restore scroll position on page load
            const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
            if (savedScrollPos !== null) {
                setTimeout(() => {
                    sidebarMenuElement.scrollTop = parseInt(savedScrollPos);
                }, 100);
            }

            // Save scroll position when user scrolls
            sidebarMenuElement.addEventListener('scroll', () => {
                sessionStorage.setItem('sidebarScrollPos', sidebarMenuElement.scrollTop);
            });

            // Update scroll position on nav link click
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    sessionStorage.setItem('sidebarScrollPos', sidebarMenuElement.scrollTop);
                });
            });
        }
    });
</script>
