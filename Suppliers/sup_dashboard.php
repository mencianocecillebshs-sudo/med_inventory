<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/includes/supplier_auth.php';

if (!$conn) {
    die('Database connection failed. Please try again later.');
}

$supplier_profile = requireSupplierPage($conn);
$supplier_id = (int)$supplier_profile['id'];
$supplier_name = $supplier_profile['name'] ?? 'Supplier';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GA² Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #1b5e3f;
            --secondary-color: #2ecc71;
            --primary-dark: #0f3f28;
            --light-bg: #f5f7fa;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e5e7eb;
        }

        /* Dark Mode Support */
        .dark-mode {
            --light-bg: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --border-color: #334155;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .main-content {
            margin-left: 250px;
            padding: 1.5rem 2rem 2rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            padding: 1.25rem 1.75rem;
            border-radius: 15px;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .page-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
        }

        .page-header .sub {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-top: 0.15rem;
        }

        .header-datetime {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Quick actions */
        .quick-action-row {
            display: flex;
            align-items: stretch;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .quick-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            align-content: flex-start;
            flex: 1 1 auto;
            min-width: 0;
        }

        .qa-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.55rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .qa-btn i {
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .qa-btn:hover {
            border-color: var(--primary-color);
            background: rgba(46, 204, 113, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(27, 94, 63, 0.1);
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .quick-action-row {
                flex-direction: column;
            }
        }

        /* KPI Cards */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .kpi-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 1rem 1.15rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.09);
        }

        .kpi-card.dashboard-link {
            cursor: pointer;
        }

        .kpi-card.dashboard-link:focus-visible {
            outline: 3px solid rgba(46, 204, 113, 0.45);
            outline-offset: 3px;
        }

        .kpi-card .icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 0.6rem;
        }

        .kpi-card .icon.green { background: rgba(15, 157, 88, 0.15); color: #0f9d58; }
        .kpi-card .icon.blue { background: rgba(37, 99, 235, 0.15); color: #2563eb; }
        .kpi-card .icon.red { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .kpi-card .icon.purple { background: rgba(109, 40, 217, 0.15); color: #6d28d9; }

        .kpi-card .label {
            font-size: 0.78rem;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 0.15rem;
        }

        .kpi-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .kpi-card .delta {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.3rem;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            color: var(--text-secondary);
        }

        .kpi-card.danger { box-shadow: 0 8px 24px rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }

        /* Main Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.25rem;
        }

        .card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            padding: 0.9rem 1rem;
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
        }

        .card-head h5 {
            margin: 0;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .link-btn {
            color: var(--secondary-color);
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .link-btn:hover {
            color: var(--primary-color);
        }

        .badge-count {
            background: var(--secondary-color);
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .loading-state {
            padding: 2rem 1.25rem;
            text-align: center;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        /* Top Medicines List */
        .top-med-item {
            padding: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease;
        }

        .top-med-item:last-child {
            border-bottom: none;
        }

        .top-med-item:hover {
            background: rgba(46, 204, 113, 0.05);
        }

        .top-med-item .name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            flex: 1;
        }

        .top-med-item .stock {
            background: rgba(46, 204, 113, 0.2);
            color: var(--secondary-color);
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            white-space: nowrap;
        }

        /* Alerts List */
        .alert-item {
            padding: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            border-left: 3px solid var(--secondary-color);
        }

        .alert-item:last-child {
            border-bottom: none;
        }

        .alert-item .alert-type {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--secondary-color);
            margin-bottom: 0.25rem;
        }

        .alert-item .alert-message {
            font-size: 0.925rem;
            color: var(--text-primary);
            font-weight: 500;
            word-break: break-word;
        }

        .alert-item .alert-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .alert-row {
            display: flex;
            gap: 0.45rem;
            align-items: flex-start;
            padding: 0.3rem 0.45rem;
            border-radius: 8px;
            margin-bottom: 0.3rem;
            font-size: 0.76rem;
            border-left: 3px solid transparent;
            transition: transform 0.2s ease;
        }

        .alert-row:hover { transform: translateX(2px); }
        .alert-row:last-child { margin-bottom: 0; }
        .alert-row.critical { background: rgba(239, 68, 68, 0.12); border-left-color: #ef4444; }
        .alert-row.warning { background: rgba(245, 158, 11, 0.12); border-left-color: #f59e0b; }
        .alert-row .a-title { font-weight: 600; color: var(--text-primary); line-height: 1.3; }
        .alert-row .a-sub { color: var(--text-secondary); font-size: 0.7rem; line-height: 1.2; }

        #alerts {
            max-height: 240px;
            overflow-y: auto;
            padding-right: 0.4rem;
        }

        #alerts::-webkit-scrollbar { width: 5px; }
        #alerts::-webkit-scrollbar-track { background: transparent; }
        #alerts::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 10px;
        }
        #alerts::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .kpi-row {
                grid-template-columns: 1fr;
            }

            .card-head {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h2><i class="bi bi-speedometer2 me-2"></i>Dashboard</h2>
                <div class="sub">Welcome back - here's your performance snapshot</div>
            </div>
            <div class="header-datetime">
                <i class="bi bi-clock me-2"></i>
                <span id="current-date-time">Loading...</span>
            </div>
        </div>

        <div class="quick-action-row">
            <div class="quick-actions">
                <button class="qa-btn" onclick="window.location.href='sup_medicine.php'"><i class="bi bi-plus-circle"></i> Add product</button>
                <button class="qa-btn" onclick="window.location.href='sup_orders.php'"><i class="bi bi-inbox"></i> View orders</button>
                <button class="qa-btn" onclick="window.location.href='sup_transactions.php'"><i class="bi bi-cash-stack"></i> Transaction history</button>
                <button class="qa-btn" onclick="window.location.href='sup_reports.php'"><i class="bi bi-file-earmark-bar-graph"></i> Generate report</button>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card dashboard-link" id="kpi-inventory" data-href="sup_medicine.php" role="link" tabindex="0" aria-label="Open inventory">
                <div class="icon blue"><i class="bi bi-boxes"></i></div>
                <div class="label">Total Products</div>
                <div class="value" id="kpi-inventory-value">0</div>
                <div class="delta" id="kpi-inventory-delta"><i class="bi bi-dash"></i>in stock</div>
            </div>

            <div class="kpi-card dashboard-link" id="kpi-revenue" data-href="sup_transactions.php" role="link" tabindex="0" aria-label="Open transactions">
                <div class="icon green"><i class="bi bi-cash-stack"></i></div>
                <div class="label">Total Revenue</div>
                <div class="value" id="kpi-revenue-value">₱0</div>
                <div class="delta" id="kpi-revenue-delta"><i class="bi bi-arrow-up-right"></i>all time</div>
            </div>

            <div class="kpi-card dashboard-link" id="kpi-low-stock" data-href="sup_medicine.php?stock_status=low" role="link" tabindex="0" aria-label="Open low stock">
                <div class="icon red"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="label">Low Stock Items</div>
                <div class="value" id="kpi-low-stock-value">0</div>
                <div class="delta" id="kpi-low-stock-delta">needs attention</div>
            </div>

            <div class="kpi-card dashboard-link" id="kpi-pending" data-href="sup_orders.php?status=pending" role="link" tabindex="0" aria-label="Open pending orders">
                <div class="icon purple"><i class="bi bi-box-seam"></i></div>
                <div class="label">Pending Orders</div>
                <div class="value" id="kpi-pending-value">0</div>
                <div class="delta" id="kpi-pending-delta">awaiting delivery</div>
            </div>
        </div>

        <div class="main-grid">
            <div>
                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-graph-up"></i>Sales Trend (this month)</h5>
                        <a href="sup_analytics.php" class="link-btn">View analytics <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div style="padding: 1.25rem; height:220px;"><canvas id="trendChart"></canvas></div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-trophy"></i>Top 10 Products (this month)</h5>
                        <a href="sup_reports.php" class="link-btn">View all</a>
                    </div>
                    <div id="topProducts">
                        <div class="loading-state">
                            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                            <span class="ms-2">Loading data...</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-bell"></i>Alerts</h5>
                        <span class="badge-count" id="alerts-count">0</span>
                    </div>
                    <div id="alerts">
                        <div class="loading-state">
                            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                            <span class="ms-2">Loading alerts...</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-activity"></i>Recent Activity</h5>
                        <a href="sup_analytics.php" class="link-btn">View log</a>
                    </div>
                    <div id="activity-list">
                        <div class="loading-state">
                            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                            <span class="ms-2">Loading activity...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/sup_dashboard.js') ?>"></script>
</body>
</html>
<?php $conn->close(); ?>