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
    <title>Dashboard - GA² Pharmacy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
        .quick-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
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
        }

        .qa-btn i {
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .qa-btn:hover {
            border-color: var(--primary-color);
            background: rgba(46, 204, 113, 0.1);
            transform: translateY(-1px);
        }

        /* KPI row */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        @media (max-width: 1100px) {
            .kpi-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .kpi-row {
                grid-template-columns: 1fr;
            }
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

        .delta.up { color: #0f9d58; }
        .delta.down { color: #e11d48; }
        .delta.flat { color: var(--text-secondary); }

        .kpi-card.warn {
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .kpi-card.danger {
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .icon.green { background: rgba(15, 157, 88, 0.15); color: #0f9d58; }
        .icon.blue { background: rgba(37, 99, 235, 0.15); color: #2563eb; }
        .icon.amber { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .icon.red { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
        .icon.purple { background: rgba(109, 40, 217, 0.15); color: #6d28d9; }

        .card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
            padding: 1.1rem 1.2rem;
            margin-bottom: 1.25rem;
            border: 1px solid var(--border-color);
        }

        .card h5 {
            font-weight: 700;
            font-size: 0.98rem;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-primary);
        }

        .card h5 i {
            color: var(--secondary-color);
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.9rem;
        }

        .link-btn {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .link-btn:hover {
            color: var(--primary-color);
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        @media (max-width: 1000px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Alerts, severity grouped */
        .alert-row {
            display: flex;
            gap: 0.65rem;
            align-items: flex-start;
            padding: 0.6rem 0.75rem;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            font-size: 0.83rem;
            border-left: 4px solid transparent;
            transition: transform 0.2s ease;
        }

        .alert-row:hover {
            transform: translateX(2px);
        }

        .alert-row:last-child {
            margin-bottom: 0;
        }

        .alert-row.critical {
            background: rgba(239, 68, 68, 0.15);
            border-left-color: #ef4444;
        }

        .alert-row.warning {
            background: rgba(245, 158, 11, 0.15);
            border-left-color: #f59e0b;
        }

        .alert-row .a-title {
            font-weight: 600;
            color: var(--text-primary);
        }

        .alert-row .a-sub {
            color: var(--text-secondary);
            font-size: 0.76rem;
        }

        .badge-count {
            background: #ef4444;
            color: #ffffff;
            border-radius: 20px;
            font-size: 0.7rem;
            padding: 0.15rem 0.55rem;
            font-weight: 700;
        }

        .badge-count.warn {
            background: #f59e0b;
        }

        .med-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 0.1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.83rem;
        }

        .med-row:last-child {
            border-bottom: none;
        }

        .med-row .rank {
            width: 20px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .med-row .name {
            flex: 1;
            font-weight: 600;
            color: var(--text-primary);
        }

        .med-row .qty {
            color: var(--text-secondary);
            font-size: 0.78rem;
        }

        .activity-row {
            display: flex;
            gap: 0.65rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.82rem;
            align-items: flex-start;
        }

        .activity-row:last-child {
            border-bottom: none;
        }

        .activity-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--secondary-color);
            margin-top: 0.4rem;
            flex-shrink: 0;
        }

        .activity-row .a-user {
            font-weight: 600;
            color: var(--text-primary);
        }

        .activity-row .a-time {
            color: var(--text-secondary);
            font-size: 0.72rem;
        }

        .supplier-chip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0.7rem;
            background: var(--light-bg);
            border-left: 3px solid var(--primary-color);
            border-radius: 8px;
            font-size: 0.82rem;
            margin-bottom: 0.4rem;
            border-top: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .supplier-chip span {
            font-weight: 500;
            color: var(--text-primary);
        }

        .supplier-chip .status {
            font-size: 0.68rem;
            padding: 0.15rem 0.5rem;
            border-radius: 12px;
            font-weight: 700;
        }

        .status.ok {
            background: rgba(15, 157, 88, 0.15);
            color: #0f9d58;
        }

        .status.late {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        #toggle-sidebar-mobile {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loading-state {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 0;
            color: var(--text-secondary);
            font-size: 0.85rem;
            width: 100%;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            color: var(--text-secondary);
            text-align: center;
            font-size: 0.85rem;
            width: 100%;
        }

        .empty-state i {
            font-size: 1.5rem;
            margin-bottom: 0.4rem;
            color: var(--text-secondary);
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
                <div class="sub">Welcome back — here's today's snapshot</div>
            </div>
            <div class="header-datetime">
                <i class="bi bi-clock me-2"></i>
                <span id="current-date-time">Loading...</span>
            </div>
        </div>

        <div class="quick-actions">
            <button class="qa-btn" onclick="window.location.href='phar_medicine.php'"><i class="bi bi-plus-circle"></i> Add medicine</button>
            <button class="qa-btn" onclick="window.location.href='phar_purchases.php'"><i class="bi bi-cart-plus"></i> New purchase order</button>
            <button class="qa-btn" onclick="window.location.href='phar_purchases.php'"><i class="bi bi-truck"></i> Contact supplier</button>
            <button class="qa-btn" onclick="window.location.href='phar_reports.php'"><i class="bi bi-file-earmark-bar-graph"></i> Generate report</button>
        </div>

        <div class="kpi-row">
            <div class="kpi-card" id="kpi-sales">
                <div class="icon green"><i class="bi bi-cash-stack"></i></div>
                <div class="label">Today's sales</div>
                <div class="value" id="kpi-sales-value">₱0</div>
                <div class="delta" id="kpi-sales-delta"><i class="bi bi-dash"></i>steady</div>
            </div>
            <div class="kpi-card" id="kpi-inventory">
                <div class="icon blue"><i class="bi bi-boxes"></i></div>
                <div class="label">Inventory value</div>
                <div class="value" id="kpi-inventory-value">₱0</div>
                <div class="delta flat"><i class="bi bi-dash"></i>steady</div>
            </div>
            <div class="kpi-card" id="kpi-low-stock">
                <div class="icon red"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="label">Low / out of stock</div>
                <div class="value" id="kpi-low-stock-value">0</div>
                <div class="delta" id="kpi-low-stock-delta">at risk</div>
            </div>
            <div class="kpi-card" id="kpi-expiring">
                <div class="icon amber"><i class="bi bi-calendar-x"></i></div>
                <div class="label">Expiring in 30 days</div>
                <div class="value" id="kpi-expiring-value">0</div>
                <div class="delta" id="kpi-expiring-delta">across 0 suppliers</div>
            </div>
            <div class="kpi-card" id="kpi-pending">
                <div class="icon purple"><i class="bi bi-box-seam"></i></div>
                <div class="label">Pending orders</div>
                <div class="value" id="kpi-pending-value">0</div>
                <div class="delta" id="kpi-pending-delta">awaiting approval</div>
            </div>
        </div>

        <div class="main-grid">
            <div>
                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-graph-up"></i>Sales &amp; demand (14 days)</h5>
                        <a href="phar_analytics.php" class="link-btn">Open analytics <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div style="height:220px;"><canvas id="trendChart"></canvas></div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-trophy"></i>Top 10 medicines (30 days)</h5>
                        <a href="phar_reports.php" class="link-btn">View all</a>
                    </div>
                    <div id="topMeds">
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
                        <h5><i class="bi bi-truck"></i>Supplier status</h5>
                    </div>
                    <div id="supplier-companies">
                        <div class="loading-state">
                            <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                            <span class="ms-2">Loading suppliers...</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h5><i class="bi bi-activity"></i>Recent activity</h5>
                        <a href="#" class="link-btn" style="display:none;">View log</a>
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
    <script src="assets/js/phar_dashboard.js"></script>
</body>
</html>
