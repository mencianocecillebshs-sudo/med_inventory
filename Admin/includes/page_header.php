<?php
if (defined('ADMIN_PAGE_HEADER_RENDERED')) {
    return;
}
define('ADMIN_PAGE_HEADER_RENDERED', true);

$scriptFile = basename(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
$pageKey = preg_replace('/\.php$/', '', $scriptFile);

$pageMeta = [
    'dashboard' => ['Dashboard', 'bi-speedometer2'],
    'medicine' => ['Inventory', 'bi-box-seam'],
    'orders' => ['Orders', 'bi-box-seam'],
    'suppliers' => ['Suppliers', 'bi-truck'],
    'transactions' => ['Transactions', 'bi-receipt'],
    'analytics' => ['Analytics', 'bi-graph-up-arrow'],
    'reports' => ['Reports', 'bi-file-earmark-bar-graph'],
    'sales' => ['Sales', 'bi-cart-check'],
    'notifications' => ['Notifications', 'bi-bell'],
    'chatbot' => ['AI Assistant', 'bi-chat-dots'],
    'users' => ['Users', 'bi-people'],
    'user_activity' => ['User Activity', 'bi-activity'],
    'settings' => ['Settings', 'bi-gear'],
];

$navSections = [
    'Inventory' => [
        ['dashboard.php', 'dashboard', 'bi-speedometer2', 'Dashboard'],
        ['medicine.php', 'medicine', 'bi-box-seam', 'Inventory'],
        ['orders.php', 'orders', 'bi-box-seam', 'Orders'],
        ['suppliers.php', 'suppliers', 'bi-truck', 'Suppliers'],
        ['transactions.php', 'transactions', 'bi-receipt', 'Transactions'],
    ],
    'Analytics & Reports' => [
        ['analytics.php', 'analytics', 'bi-graph-up-arrow', 'Analytics'],
        ['reports.php', 'reports', 'bi-file-earmark-bar-graph', 'Reports'],
        ['sales.php', 'sales', 'bi-cart-check', 'Sales'],
    ],
    'System' => [
        ['notifications.php', 'notifications', 'bi-bell', 'Notifications'],
        ['chatbot.php', 'chatbot', 'bi-chat-dots', 'AI Assistant'],
        ['users.php', 'users', 'bi-people', 'Users', true],
        ['user_activity.php', 'user_activity', 'bi-activity', 'User Activity', true],
        ['settings.php', 'settings', 'bi-gear', 'Settings'],
    ],
];

[$pageTitle, $pageIcon] = $pageMeta[$pageKey] ?? [ucwords(str_replace('_', ' ', $pageKey)), 'bi-folder2-open'];
$isDashboard = $pageKey === 'dashboard';
$adminName = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$adminRole = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Guest';
?>

<div class="admin-breadcrumb-header" role="navigation" aria-label="Breadcrumb">
    <div class="admin-breadcrumb-shell">
        <div class="admin-breadcrumb-brand">
            <span class="admin-breadcrumb-icon"><i class="bi <?php echo htmlspecialchars($pageIcon); ?>"></i></span>
            <div>
                <div class="admin-breadcrumb-eyebrow">Admin Workspace</div>
                <ol class="breadcrumb admin-breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard.php">
                            <i class="bi bi-house-door"></i>
                            <span>Home</span>
                        </a>
                    </li>
                    <li class="breadcrumb-item">
                        <?php if (!$isDashboard) { ?>
                            <a href="dashboard.php">Admin</a>
                        <?php } else { ?>
                            <span>Admin</span>
                        <?php } ?>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($pageTitle); ?></li>
                </ol>
            </div>
        </div>
        <div class="admin-header-actions">
            <div class="admin-time-chip" title="Current time">
                <i class="bi bi-clock-history"></i>
                <span id="admin-live-time">--:--</span>
            </div>
            <div class="admin-page-actions" id="admin-page-actions" aria-label="Page actions"></div>
            <div class="dropdown">
                <button class="admin-nav-button" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <span>Navigate</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end admin-nav-menu">
                    <?php foreach ($navSections as $sectionTitle => $items) { ?>
                        <div class="admin-nav-section">
                            <div class="admin-nav-section-title"><?php echo htmlspecialchars($sectionTitle); ?></div>
                            <div class="admin-nav-grid">
                                <?php foreach ($items as $item) {
                                    $adminOnly = isset($item[4]) && $item[4];
                                    if ($adminOnly && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
                                        continue;
                                    }
                                    [$href, $key, $icon, $label] = $item;
                                    $activeClass = $key === $pageKey ? ' active' : '';
                                ?>
                                    <a class="admin-nav-item<?php echo $activeClass; ?>" href="<?php echo htmlspecialchars($href); ?>">
                                        <i class="bi <?php echo htmlspecialchars($icon); ?>"></i>
                                        <span><?php echo htmlspecialchars($label); ?></span>
                                        <?php if ($key === 'notifications') { ?>
                                            <span class="notification-badge admin-notification-badge" id="notif-badge">0</span>
                                        <?php } ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <button class="admin-icon-button" id="theme-toggle" type="button" title="Toggle dark mode" aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars"></i>
            </button>
            <div class="admin-user-chip" title="<?php echo htmlspecialchars($adminName . ' - ' . $adminRole); ?>">
                <i class="bi bi-person-circle"></i>
                <span><?php echo htmlspecialchars($adminName); ?></span>
            </div>
            <button class="admin-icon-button admin-logout-button" type="button" data-bs-toggle="modal" data-bs-target="#confirmLogoutModal" title="Logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<style>
    .sidebar,
    .sidebar-overlay,
    .sidebar-toggle-anchor,
    .btn-mobile-toggle,
    #mobile-sidebar-toggle,
    #toggle-sidebar,
    #toggle-sidebar-mobile {
        display: none !important;
    }

    .main-content,
    body.sidebar-collapsed .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .admin-breadcrumb-header {
        margin-left: auto;
        width: 100%;
        max-width: 1760px;
        margin-right: auto;
        padding: 1rem 1.5rem 0;
        transition: margin-left 0.35s ease, width 0.35s ease;
        box-sizing: border-box;
    }

    body.sidebar-collapsed .admin-breadcrumb-header {
        margin-left: auto;
        margin-right: auto;
        width: 100%;
    }

    .admin-breadcrumb-shell {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-height: 76px;
        padding: 1rem 1.25rem;
        border: 1px solid rgba(27, 94, 63, 0.14);
        border-radius: 14px;
        background:
            linear-gradient(135deg, rgba(27, 94, 63, 0.08), rgba(15, 63, 40, 0.03)),
            #ffffff;
        box-shadow: 0 12px 30px rgba(15, 63, 40, 0.08);
    }

    .admin-breadcrumb-brand {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        min-width: 0;
    }

    .admin-breadcrumb-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 46px;
        width: 46px;
        height: 46px;
        border-radius: 12px;
        color: #ffffff;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
        box-shadow: 0 10px 18px rgba(27, 94, 63, 0.22);
        font-size: 1.25rem;
    }

    .admin-breadcrumb-eyebrow {
        color: #64748b;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0;
        margin-bottom: 0.18rem;
    }

    .admin-breadcrumb {
        align-items: center;
        flex-wrap: wrap;
        row-gap: 0.25rem;
        font-size: 0.95rem;
        font-weight: 650;
    }

    .admin-breadcrumb .breadcrumb-item,
    .admin-breadcrumb .breadcrumb-item a,
    .admin-breadcrumb .breadcrumb-item span {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .admin-breadcrumb .breadcrumb-item a {
        color: #1b5e3f;
        text-decoration: none;
    }

    .admin-breadcrumb .breadcrumb-item a:hover {
        color: #0f3f28;
        text-decoration: underline;
    }

    .admin-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
        color: #94a3b8;
        content: "/";
        font-weight: 700;
    }

    .admin-breadcrumb .breadcrumb-item.active {
        color: #1e293b;
        min-width: 0;
    }

    .admin-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.6rem;
        flex: 0 0 auto;
    }

    .admin-page-actions {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .admin-page-actions:empty {
        display: none;
    }

    .admin-page-actions .btn,
    .admin-page-actions button,
    .admin-page-actions a {
        min-height: 38px;
        border-radius: 12px !important;
        font-weight: 800 !important;
        box-shadow: none !important;
    }

    .admin-page-actions .btn-primary,
    .admin-page-actions .btn-success,
    .admin-page-actions .btn-light {
        border: 0 !important;
        color: #ffffff !important;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%) !important;
    }

    .admin-page-actions .btn-outline-secondary,
    .admin-page-actions .btn-secondary {
        border: 1px solid rgba(27, 94, 63, 0.14) !important;
        color: #0f3f28 !important;
        background: rgba(27, 94, 63, 0.08) !important;
    }

    .admin-nav-button,
    .admin-icon-button,
    .admin-user-chip,
    .admin-time-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
        border: 1px solid rgba(27, 94, 63, 0.14);
        color: #0f3f28;
        background: rgba(27, 94, 63, 0.08);
        font-weight: 700;
    }

    .admin-nav-button {
        gap: 0.45rem;
        padding: 0.45rem 0.8rem;
        border-radius: 999px;
    }

    .admin-icon-button {
        width: 38px;
        border-radius: 12px;
    }

    .admin-user-chip {
        gap: 0.45rem;
        max-width: 180px;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        font-size: 0.86rem;
        white-space: nowrap;
    }

    .admin-time-chip {
        gap: 0.45rem;
        min-width: 92px;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        color: #1e293b;
        background: #ffffff;
        font-size: 0.86rem;
        white-space: nowrap;
        box-shadow: inset 0 0 0 1px rgba(27, 94, 63, 0.04);
    }

    .admin-time-chip i {
        color: #1b5e3f;
    }

    .admin-user-chip span {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .admin-nav-button:hover,
    .admin-icon-button:hover {
        border-color: rgba(27, 94, 63, 0.35);
        color: #ffffff;
        background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%);
    }

    .admin-nav-menu {
        width: min(640px, calc(100vw - 2rem));
        padding: 1rem;
        border: 1px solid rgba(27, 94, 63, 0.14);
        border-radius: 14px;
        box-shadow: 0 18px 48px rgba(15, 63, 40, 0.18);
    }

    .admin-nav-section + .admin-nav-section {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .admin-nav-section-title {
        margin-bottom: 0.55rem;
        color: #64748b;
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }

    .admin-nav-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.55rem;
    }

    .admin-nav-item {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.55rem;
        min-height: 42px;
        padding: 0.6rem 0.7rem;
        border: 1px solid transparent;
        border-radius: 10px;
        color: #1e293b;
        background: #f8fafc;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.88rem;
    }

    .admin-nav-item i {
        color: #1b5e3f;
        font-size: 1rem;
    }

    .admin-nav-item:hover,
    .admin-nav-item.active {
        border-color: rgba(27, 94, 63, 0.18);
        color: #0f3f28;
        background: rgba(27, 94, 63, 0.09);
    }

    .admin-notification-badge {
        position: static;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.35rem;
        height: 1.35rem;
        margin-left: auto;
        border-radius: 999px;
        color: #ffffff;
        background: #dc3545;
        font-size: 0.72rem;
        line-height: 1;
    }

    .dark-mode .admin-breadcrumb-shell,
    body.dark-mode .admin-breadcrumb-shell {
        border-color: rgba(46, 204, 113, 0.18);
        background:
            linear-gradient(135deg, rgba(46, 204, 113, 0.12), rgba(15, 63, 40, 0.12)),
            #111827;
        box-shadow: 0 16px 34px rgba(0, 0, 0, 0.3);
    }

    .dark-mode .admin-breadcrumb-eyebrow,
    body.dark-mode .admin-breadcrumb-eyebrow {
        color: #94a3b8;
    }

    .dark-mode .admin-breadcrumb .breadcrumb-item a,
    body.dark-mode .admin-breadcrumb .breadcrumb-item a {
        color: #6ee7b7 !important;
    }

    .dark-mode .admin-breadcrumb .breadcrumb-item.active,
    body.dark-mode .admin-breadcrumb .breadcrumb-item.active {
        color: #e2e8f0 !important;
    }

    .dark-mode .admin-nav-button,
    .dark-mode .admin-icon-button,
    .dark-mode .admin-user-chip,
    .dark-mode .admin-time-chip,
    body.dark-mode .admin-nav-button,
    body.dark-mode .admin-icon-button,
    body.dark-mode .admin-user-chip,
    body.dark-mode .admin-time-chip {
        border-color: rgba(46, 204, 113, 0.2);
        color: #6ee7b7;
        background: rgba(46, 204, 113, 0.12);
    }

    .dark-mode .admin-nav-menu,
    body.dark-mode .admin-nav-menu {
        border-color: rgba(46, 204, 113, 0.18);
        background: #111827;
        box-shadow: 0 18px 48px rgba(0, 0, 0, 0.35);
    }

    .dark-mode .admin-nav-section + .admin-nav-section,
    body.dark-mode .admin-nav-section + .admin-nav-section {
        border-top-color: #1f2937;
    }

    .dark-mode .admin-nav-section-title,
    body.dark-mode .admin-nav-section-title {
        color: #94a3b8;
    }

    .dark-mode .admin-nav-item,
    body.dark-mode .admin-nav-item {
        color: #e2e8f0;
        background: #0f172a;
    }

    .dark-mode .admin-nav-item:hover,
    .dark-mode .admin-nav-item.active,
    body.dark-mode .admin-nav-item:hover,
    body.dark-mode .admin-nav-item.active {
        border-color: rgba(46, 204, 113, 0.22);
        color: #6ee7b7;
        background: rgba(46, 204, 113, 0.12);
    }

    @media (max-width: 991.98px) {
        .admin-breadcrumb-header,
        body.sidebar-collapsed .admin-breadcrumb-header {
            margin-left: 0;
            margin-right: 0;
            max-width: none;
            width: 100%;
            padding: 1rem 1rem 0;
        }

        .admin-breadcrumb-shell {
            align-items: flex-start;
            flex-direction: column;
            min-height: auto;
            padding: 0.875rem 1rem;
        }

        .admin-header-actions {
            width: 100%;
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .admin-page-actions {
            order: 5;
            width: 100%;
        }

        .admin-user-chip {
            max-width: 100%;
        }
    }

    @media (max-width: 575.98px) {
        .admin-breadcrumb-header,
        body.sidebar-collapsed .admin-breadcrumb-header {
            padding: 0.75rem 0.75rem 0;
        }

        .admin-breadcrumb-icon {
            flex-basis: 40px;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            font-size: 1.1rem;
        }

        .admin-breadcrumb {
            font-size: 0.86rem;
        }

        .admin-nav-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
