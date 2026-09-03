<?php
// Supplier workspace header/nav wrapper.
if (defined('SUPPLIER_NAV_RENDERED')) {
    return;
}
define('SUPPLIER_NAV_RENDERED', true);

if (!isset($conn)) {
    $dbPath = __DIR__ . '/../config/db.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
    }
}

$helperPath = __DIR__ . '/../config/sup_settings_helper.php';
if (file_exists($helperPath)) {
    require_once $helperPath;
}

$records_per_page = (isset($conn) && $conn && function_exists('getRecordsPerPage')) ? getRecordsPerPage($conn) : 25;
$system_date_format = (isset($conn) && $conn && function_exists('getDateFormat')) ? getDateFormat($conn) : 'Y-m-d';
$currency_symbol = (isset($conn) && $conn && function_exists('getCurrencySymbol'))
    ? getCurrencySymbol($conn)
    : (function_exists('normalizeCurrencySymbol') ? normalizeCurrencySymbol('₱') : '₱');
$low_stock_threshold = (isset($conn) && $conn && function_exists('getLowStockThreshold')) ? getLowStockThreshold($conn) : 10;
$critical_stock_threshold = (isset($conn) && $conn && function_exists('getCriticalStockThreshold')) ? getCriticalStockThreshold($conn) : 5;
$expiry_alert_days = (isset($conn) && $conn && function_exists('getExpiryAlertDays')) ? getExpiryAlertDays($conn) : 30;
?>
<link rel="stylesheet" href="assets/css/supplier-shell.css">
<script>
    window.RECORDS_PER_PAGE = <?php echo (int)$records_per_page; ?>;
    window.SYSTEM_DATE_FORMAT = <?php echo json_encode($system_date_format); ?>;
    window.CURRENCY_SYMBOL = <?php echo json_encode($currency_symbol); ?>;
    window.LOW_STOCK_THRESHOLD = <?php echo (int)$low_stock_threshold; ?>;
    window.CRITICAL_STOCK_THRESHOLD = <?php echo (int)$critical_stock_threshold; ?>;
    window.EXPIRY_ALERT_DAYS = <?php echo (int)$expiry_alert_days; ?>;
</script>
<script src="assets/js/sup_utils.js"></script>
<?php require_once __DIR__ . '/page_header.php'; ?>

<div class="modal fade" id="confirmLogoutModal" tabindex="-1" aria-labelledby="confirmLogoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content logout-modal">
            <div class="logout-banner">
                <i class="bi bi-box-arrow-right"></i>
                <span>Ready to sign out</span>
            </div>
            <div class="modal-body logout-modal-body">
                <?php
                    $logoutName = isset($_SESSION['username']) ? $_SESSION['username'] : 'Supplier';
                    $logoutParts = array_filter(explode(' ', $logoutName));
                    $logoutInitials = '';
                    foreach ($logoutParts as $part) {
                        $logoutInitials .= strtoupper($part[0]);
                    }
                    if ($logoutInitials === '') { $logoutInitials = 'S'; }
                    $logoutRole = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Supplier';
                ?>
                <div class="logout-title">
                    <h5 id="confirmLogoutModalLabel">Confirm logout</h5>
                    <p class="logout-copy">The following account will be signed out of the supplier workspace.</p>
                </div>
                <div class="logout-user-card">
                    <div class="logout-user-avatar"><?php echo htmlspecialchars($logoutInitials); ?></div>
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
    :root {
        --primary-color: #1b5e3f;
        --primary-dark: #0f3f28;
        --secondary-color: #2ecc71;
        --card-bg: #ffffff;
        --card-border: #e9ecef;
        --table-bg: #ffffff;
        --table-border: #dee2e6;
        --table-stripe-bg: #f8f9fa;
        --table-hover-bg: #e6f7f0;
        --text-primary: #2c3e50;
        --text-secondary: #6c757d;
        --input-bg: #ffffff;
        --input-border: #ced4da;
        --modal-bg: #ffffff;
        --modal-border: #dee2e6;
        --modal-header-bg: var(--primary-color);
        --modal-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    }

    body {
        background: #f5f7fa;
        color: var(--text-primary);
    }

    .table-container,
    .table-responsive {
        border-radius: 15px;
        background: var(--table-bg);
        border: 1px solid var(--table-border);
    }

    .table {
        margin-bottom: 0;
        background-color: var(--table-bg);
        color: var(--text-primary);
    }

    .table th {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%) !important;
        color: #ffffff !important;
        border: 0;
    }

    .table td {
        color: var(--text-primary);
        border-color: var(--table-border);
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: var(--table-hover-bg);
    }

    .card {
        border: 1px solid rgba(27, 94, 63, 0.10) !important;
        border-radius: 14px !important;
        box-shadow: 0 12px 30px rgba(15, 63, 40, 0.08) !important;
    }

    .btn-primary,
    .btn-success {
        border: 0 !important;
        color: #ffffff !important;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
    }

    .action-btn,
    .table .btn-sm {
        border-radius: 8px !important;
        font-weight: 600 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 0.25rem !important;
        white-space: nowrap !important;
    }

    .modal-content {
        border-radius: 18px;
        border: 1px solid var(--modal-border);
        box-shadow: var(--modal-shadow);
        overflow: hidden;
        background-color: var(--modal-bg);
        color: var(--text-primary);
    }

    .logout-modal {
        max-width: 430px;
        margin: 0 auto;
    }

    .logout-banner {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 1rem 1.25rem;
        color: #ffffff;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        font-weight: 800;
    }

    .logout-modal-body {
        padding: 1.25rem;
    }

    .logout-title h5 {
        margin-bottom: 0.25rem;
        font-weight: 800;
    }

    .logout-copy {
        margin-bottom: 1rem;
        color: #64748b;
    }

    .logout-user-card {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.9rem;
        border: 1px solid rgba(27, 94, 63, 0.12);
        border-radius: 12px;
        background: rgba(27, 94, 63, 0.06);
    }

    .logout-user-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        color: #ffffff;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        font-weight: 800;
    }

    .logout-user-name {
        font-weight: 800;
    }

    .logout-user-role {
        color: #64748b;
        font-size: 0.88rem;
    }

    .logout-modal-footer {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        padding: 1rem 1.25rem 1.25rem;
        border-top: 1px solid #e2e8f0;
        background: #ffffff;
    }

    .btn-cancel {
        border: 1px solid rgba(27, 94, 63, 0.14);
        color: #0f3f28;
        background: rgba(27, 94, 63, 0.08);
        font-weight: 800;
    }

    .btn-logout-confirm {
        color: #ffffff;
        background: #dc3545;
        font-weight: 800;
    }

    .dark-mode,
    body.dark-mode {
        --card-bg: #111827;
        --card-border: #1f2937;
        --table-bg: #0f172a;
        --table-border: #334155;
        --table-stripe-bg: #111827;
        --table-hover-bg: rgba(46, 204, 113, 0.12);
        --text-primary: #e2e8f0;
        --text-secondary: #94a3b8;
        --input-bg: #0f172a;
        --input-border: #334155;
        --modal-bg: #111827;
        --modal-border: #1f2937;
        background: #0f172a;
        color: #e2e8f0;
    }

    body.dark-mode .card,
    body.dark-mode .table-container,
    body.dark-mode .table-responsive,
    body.dark-mode .modal-content {
        background: #111827 !important;
        border-color: #1f2937 !important;
        color: #e2e8f0 !important;
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.28) !important;
    }

    body.dark-mode .table,
    body.dark-mode .table td,
    body.dark-mode .table tbody th {
        background: #0f172a !important;
        color: #e2e8f0 !important;
        border-color: #334155 !important;
    }

    body.dark-mode .table tbody tr:hover td {
        background: rgba(46, 204, 113, 0.12) !important;
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .input-group-text {
        background: #0f172a !important;
        color: #e2e8f0 !important;
        border-color: #334155 !important;
    }

    body.dark-mode .modal-body,
    body.dark-mode .modal-footer,
    body.dark-mode .logout-modal-footer {
        background: #111827 !important;
        color: #e2e8f0 !important;
        border-color: #1f2937 !important;
    }

    /* Compact table: hide detail columns until expanded */
    .sup-table-wrap:not(.expanded) .col-detail {
        display: none;
    }

    .sup-table-wrap.expanded .col-detail {
        display: table-cell;
    }

    .sup-table-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--table-border);
        background: rgba(27, 94, 63, 0.04);
    }

    .sup-table-footer {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-top: 1px solid var(--table-border);
        background: rgba(27, 94, 63, 0.03);
    }

    .sup-pagination-info {
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 600;
    }

    body.dark-mode .sup-table-footer {
        background: rgba(46, 204, 113, 0.06) !important;
        border-color: #334155 !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggle = document.getElementById('theme-toggle');
        const confirmLogoutBtn = document.getElementById('confirm-logout-btn');
        const liveTime = document.getElementById('admin-live-time');
        const pageActions = document.getElementById('admin-page-actions');

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

        const moveLegacyActions = () => {
            if (!pageActions) return;
            const legacyHeaders = [
                ...document.querySelectorAll('.main-content > .page-header'),
                ...document.querySelectorAll('.main-content > .d-flex.justify-content-between.align-items-center.mb-4:first-child')
            ];

            legacyHeaders.forEach(header => {
                header.querySelectorAll(':scope > button, :scope > a, :scope > .btn, :scope > div > button, :scope > div > a').forEach(action => {
                    if (action.closest('.admin-page-actions')) return;
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

        if (themeToggle) {
            const currentTheme = localStorage.getItem('theme') || 'light';
            const setTheme = (isDark) => {
                document.documentElement.classList.toggle('dark-mode', isDark);
                document.body.classList.toggle('dark-mode', isDark);
                themeToggle.innerHTML = isDark
                    ? '<i class="bi bi-sun-fill"></i>'
                    : '<i class="bi bi-moon-stars"></i>';
                themeToggle.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            };
            setTheme(currentTheme === 'dark');
            themeToggle.addEventListener('click', () => setTheme(!document.body.classList.contains('dark-mode')));
        }

        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener('click', () => {
                confirmLogoutBtn.disabled = true;
                confirmLogoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';

                fetch('api/sup_logout.php', { method: 'POST', credentials: 'include' })
                    .then(r => r.json())
                    .then(data => {
                        window.location.href = data.redirect || '../index.php';
                    })
                    .catch(() => {
                        window.location.href = '../index.php';
                    });
            });
        }

        const loadNotificationCount = () => {
            fetch('api/sup_notifications.php?unread_count=true')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notif-badge');
                    if (!badge) return;
                    const count = parseInt(data.count || 0, 10);
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = count > 0 ? 'inline-flex' : 'none';
                })
                .catch(() => {});
        };
        loadNotificationCount();
        setInterval(loadNotificationCount, 30000);
    });
</script>
