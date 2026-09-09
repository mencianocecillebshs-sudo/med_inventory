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
    <title>Forecasting &amp; Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {font-family: 'Inter',sans-serif;background:#f8fafc;}
        .main-content {margin-left:250px;padding:2rem;transition:margin-left .3s;}
        @media(max-width:768px){.main-content{margin-left:0;padding:1rem;}}
        .page-header{background:linear-gradient(135deg,#1b5e3f,#0f3f28);color:#fff;padding:2rem;border-radius:15px;margin-bottom:2rem;box-shadow:0 8px 24px rgba(27,94,63,.2);}
        .page-header h2{font-size:1.75rem;font-weight:700;}
        .card{border-radius:15px;box-shadow:0 8px 24px rgba(0,0,0,.08);background:#fff;padding:1.5rem;margin-bottom:1.5rem;}
        .card h5{font-weight:700;font-size:1.1rem;display:flex;align-items:center;}
        .card h5 i{margin-right:.5rem;color:#1b5e3f;}
        .filter-card{background:#fff;padding:1.5rem;margin-bottom:2rem;border-radius:15px;box-shadow:0 4px 16px rgba(0,0,0,.06);border-left:5px solid #1b5e3f;}
        .filter-card h6{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;color:#0f3f28;font-weight:800;}
        .analytics-filter-grid{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:end;}
        .analytics-date-grid{display:grid;grid-template-columns:minmax(320px,1fr) auto auto;gap:.85rem;align-items:end;}
        .date-range-panel{display:grid;grid-template-columns:1fr auto 1fr;gap:.75rem;align-items:end;padding:.85rem;border:1px solid #d1fae5;border-radius:14px;background:linear-gradient(135deg,#f8fafc,#ecfdf5);box-shadow:inset 0 1px 0 rgba(255,255,255,.85);}
        .date-field{min-width:0;}
        .date-field .form-label{display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;color:#335045;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;}
        .date-input-shell{position:relative;}
        .date-input-shell i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#1b5e3f;pointer-events:none;z-index:1;}
        .date-input-shell .form-control{min-height:46px;padding-left:2.65rem;padding-right:.85rem;background:#fff;color:#1e293b;border-color:#b7e4ce;box-shadow:0 6px 18px rgba(15,63,40,.07);}
        .date-input-shell .form-control:hover{border-color:#1b5e3f;background:#f8fffb;box-shadow:0 8px 22px rgba(15,63,40,.12);}
        .date-input-shell .form-control:focus{background:#fff;border-color:#1b5e3f;box-shadow:0 0 0 .22rem rgba(27,94,63,.16);}
        .date-input-shell input[type="date"]::-webkit-calendar-picker-indicator{cursor:pointer;opacity:.85;filter:sepia(70%) saturate(600%) hue-rotate(88deg) brightness(70%);}
        .date-range-separator{display:grid;place-items:center;width:38px;height:38px;margin-bottom:.25rem;border-radius:999px;background:#1b5e3f;color:#fff;box-shadow:0 6px 14px rgba(27,94,63,.22);}
        .date-range-status{grid-column:1 / -1;display:flex;align-items:center;gap:.45rem;min-height:1.2rem;color:#64748b;font-size:.8rem;font-weight:700;}
        .date-range-status i{color:#1b5e3f;}
        .algo-filter-panel{display:flex;align-items:center;justify-content:space-between;gap:1rem;}
        .algo-filter-copy h6{margin-bottom:.25rem;}
        .algo-filter-copy p{margin:0;color:#64748b;font-size:.875rem;}
        .algo-dropdown{min-width:min(100%,360px);}
        .algo-dropdown .dropdown-toggle{display:flex;align-items:center;justify-content:space-between;gap:.75rem;border:2px solid #d1fae5;background:#f8fafc;color:#0f3f28;border-radius:12px;padding:.8rem 1rem;font-weight:700;box-shadow:0 4px 14px rgba(15,63,40,.08);}
        .algo-dropdown .dropdown-toggle:hover,.algo-dropdown .dropdown-toggle:focus{background:#dff8ec;border-color:#1b5e3f;color:#062f1d;box-shadow:0 0 0 .2rem rgba(27,94,63,.14),0 10px 22px rgba(15,63,40,.12);}
        .algo-dropdown .dropdown-menu{width:100%;border:1px solid #d1fae5;border-radius:14px;padding:.45rem;box-shadow:0 16px 42px rgba(15,23,42,.16);}
        .algo-dropdown .dropdown-item{border-radius:10px;padding:.7rem .85rem;font-weight:600;color:#334155;display:flex;align-items:center;gap:.65rem;}
        .algo-dropdown .dropdown-item i{color:#1b5e3f;font-size:1rem;}
        .algo-dropdown .dropdown-item:hover,.algo-dropdown .dropdown-item:focus{background:#dff8ec;color:#062f1d;}
        .algo-dropdown .dropdown-item.active{background:linear-gradient(135deg,#1b5e3f,#0f3f28);color:#fff;}
        .algo-dropdown .dropdown-item.active i{color:#fff;}
        .algorithm-grid.is-filtered{justify-content:center;}
        .algorithm-grid.is-filtered > [data-algo-column]{width:100%;max-width:1120px;flex:0 0 100%;}
        .algorithm-grid.is-filtered .algo-card{min-height:620px;}
        .algorithm-grid.is-filtered .chart-container{height:560px;}
        .algo-card{transition:transform .25s ease,box-shadow .25s ease,opacity .2s ease,border-color .2s ease;}
        .algo-card:hover{transform:translateY(-3px);box-shadow:0 16px 38px rgba(15,63,40,.14);border-color:#b7e4ce;}
        .algo-card.is-selected{box-shadow:0 18px 46px rgba(15,63,40,.14);}
        .form-control,.form-select{border-radius:10px;border:2px solid #e2e8f0;padding:.65rem 1rem;}
        .form-control:focus,.form-select:focus{border-color:#1b5e3f;box-shadow:0 0 0 .2rem rgba(27,94,63,.15);}
        .btn-primary{background:linear-gradient(135deg,#1b5e3f,#0f3f28);border:none;border-radius:10px;padding:.65rem 1.5rem;font-weight:600;}
        .btn-primary:hover,.btn-primary:focus-visible{background:linear-gradient(135deg,#2e8a61,#17664d);color:#fff;transform:translateY(-2px);box-shadow:0 7px 18px rgba(27,94,63,.32);}
        .btn-warning{background:linear-gradient(135deg,#f59e0b,#d97706);border:none;color:white;}
        .btn-warning:hover,.btn-warning:focus-visible{background:linear-gradient(135deg,#fbbf24,#b45309);color:#1f1300;transform:translateY(-2px);box-shadow:0 7px 18px rgba(245,158,11,.32);}
        canvas{max-height:400px;}
        .chart-container{position:relative;height:400px;}
        .forecast-guide{margin-top:1rem;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;}
        .forecast-guide p{margin-bottom:.5rem;color:#475569;}
        .forecast-details {display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;margin-top:.75rem;}
        .forecast-detail {padding:.75rem;background:#fff;border-left:4px solid #f59e0b;border-radius:6px;color:#475569;font-size:.85rem;transition:background .2s ease,box-shadow .2s ease,transform .2s ease;}
        .forecast-detail:hover{background:#fffbeb;box-shadow:0 8px 20px rgba(15,23,42,.08);transform:translateY(-1px);}
        .forecast-detail.critical {border-left-color:#ef4444;}
        .forecast-detail strong {color:#1e293b;}
        body.dark-mode .forecast-detail {background:#111827;color:#cbd5e1;}
        body.dark-mode .forecast-detail strong {color:#e2e8f0;}
        .loading-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;border-radius:15px;z-index:10;}

        /* Modal Summary Styling */
        .insight-item {padding:1rem;border-radius:12px;margin-bottom:1rem;border:1px solid #fde68a;background:#fff9c4;transition:all .2s;}
        .insight-item:hover {transform:translateY(-2px);box-shadow:0 8px 20px rgba(15,23,42,.14);}
        .insight-item.critical {background:#fee2e2;border-color:#fca5a5;color:#991b1b;}
        .insight-item.warning {background:#fef3c7;border-color:#fbbf24;}
        .insight-item.success {background:#ecfdf5;border-color:#6ee7b7;}
        .insight-item.info {background:#eff6ff;border-color:#93c5fd;color:#1e3a8a;}
        .insight-item .icon {font-size:1.5rem;margin-right:.75rem;}
        .core-insights{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem;}
        .core-insight{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;box-shadow:0 4px 16px rgba(0,0,0,.05);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease;}
        .core-insight:hover{transform:translateY(-2px);border-color:#b7e4ce;box-shadow:0 12px 28px rgba(15,63,40,.12);}
        .core-insight span{display:block;color:#64748b;font-weight:700;font-size:.85rem;margin-bottom:.35rem;}
        .core-insight strong{font-size:1.35rem;color:#0f3f28;}
        .core-insight small.core-sub{display:block;color:#94a3b8;font-weight:500;font-size:.75rem;margin-top:.25rem;}
        @media(max-width:992px){.core-insights{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:768px){.analytics-date-grid{grid-template-columns:1fr;}.date-range-panel{grid-template-columns:1fr;}.date-range-separator{width:100%;height:30px;margin:0;}.date-range-separator i{transform:rotate(90deg);}.algo-filter-panel{align-items:stretch;flex-direction:column;}.algo-dropdown{min-width:100%;}.algorithm-grid.is-filtered .chart-container{height:380px;}}
        @media(max-width:576px){.core-insights{grid-template-columns:1fr;}}

        /* ===== DARK MODE ===== */
        body.dark-mode { background:#0f1419 !important; color:#e2e8f0 !important; }
        body.dark-mode .main-content { background:transparent !important; }

        body.dark-mode .card {
            background:#111827 !important;
            box-shadow:0 8px 24px rgba(0,0,0,.4) !important;
            color:#e2e8f0 !important;
        }
        body.dark-mode .card h5 { color:#e2e8f0 !important; }
        body.dark-mode .card h5 i { color:#2ecc71 !important; }

        body.dark-mode .filter-card {
            background:#111827 !important;
            border-left-color:#2ecc71 !important;
            box-shadow:0 4px 16px rgba(0,0,0,.3) !important;
            color:#e2e8f0 !important;
        }
        body.dark-mode .filter-card h6 { color:#e2e8f0 !important; }
        body.dark-mode .date-range-panel {
            background:linear-gradient(135deg,rgba(46,204,113,.12),rgba(15,63,40,.12)),#0f172a !important;
            border-color:rgba(46,204,113,.24) !important;
            box-shadow:inset 0 1px 0 rgba(255,255,255,.04),0 10px 26px rgba(0,0,0,.2) !important;
        }
        body.dark-mode .date-field .form-label { color:#a7f3d0 !important; }
        body.dark-mode .date-input-shell i { color:#6ee7b7 !important; }
        body.dark-mode .date-input-shell .form-control {
            background:#111827 !important;
            color:#f8fafc !important;
            border-color:#334155 !important;
            box-shadow:0 8px 22px rgba(0,0,0,.22) !important;
        }
        body.dark-mode .date-input-shell .form-control:hover {
            background:#172033 !important;
            border-color:#6ee7b7 !important;
            color:#ffffff !important;
        }
        body.dark-mode .date-input-shell .form-control:focus {
            background:#0f172a !important;
            border-color:#2ecc71 !important;
            box-shadow:0 0 0 .22rem rgba(46,204,113,.22) !important;
        }
        body.dark-mode .date-input-shell input[type="date"]::-webkit-calendar-picker-indicator {
            filter:invert(88%) sepia(22%) saturate(798%) hue-rotate(92deg) brightness(98%);
        }
        body.dark-mode .date-range-separator {
            background:#2ecc71 !important;
            color:#062f1d !important;
            box-shadow:0 8px 18px rgba(46,204,113,.18) !important;
        }
        body.dark-mode .date-range-status { color:#cbd5e1 !important; }
        body.dark-mode .date-range-status i { color:#6ee7b7 !important; }
        body.dark-mode .algo-filter-copy p { color:#94a3b8 !important; }
        body.dark-mode .algo-dropdown .dropdown-toggle {
            background:#1e293b !important;
            color:#e2e8f0 !important;
            border-color:#334155 !important;
            box-shadow:0 4px 16px rgba(0,0,0,.3) !important;
        }
        body.dark-mode .algo-dropdown .dropdown-toggle:hover,
        body.dark-mode .algo-dropdown .dropdown-toggle:focus {
            background:#163828 !important;
            border-color:#6ee7b7 !important;
            color:#ffffff !important;
            box-shadow:0 0 0 .2rem rgba(46,204,113,.22),0 10px 24px rgba(0,0,0,.28) !important;
        }
        body.dark-mode .algo-dropdown .dropdown-menu {
            background:#111827 !important;
            border-color:#334155 !important;
            box-shadow:0 16px 42px rgba(0,0,0,.45) !important;
        }
        body.dark-mode .algo-dropdown .dropdown-item { color:#cbd5e1 !important; }
        body.dark-mode .algo-dropdown .dropdown-item:hover,
        body.dark-mode .algo-dropdown .dropdown-item:focus { background:#163828 !important; color:#ffffff !important; }
        body.dark-mode .algo-dropdown .dropdown-item:hover i,
        body.dark-mode .algo-dropdown .dropdown-item:focus i { color:#6ee7b7 !important; }
        body.dark-mode .algo-dropdown .dropdown-item.active { background:linear-gradient(135deg,#2ecc71,#1b5e3f) !important; color:#062f1d !important; }
        body.dark-mode .algo-dropdown .dropdown-item.active i { color:#062f1d !important; }

        body.dark-mode .form-label { color:#cbd5e1 !important; }
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background:#1e293b !important;
            color:#e2e8f0 !important;
            border-color:#334155 !important;
        }
        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            border-color:#2ecc71 !important;
            box-shadow:0 0 0 .2rem rgba(46,204,113,.2) !important;
        }

        body.dark-mode .core-insight {
            background:#1e293b !important;
            border-color:#334155 !important;
            box-shadow:0 4px 16px rgba(0,0,0,.3) !important;
        }
        body.dark-mode .core-insight:hover,
        body.dark-mode .algo-card:hover {
            background:#172033 !important;
            border-color:#6ee7b7 !important;
            box-shadow:0 16px 34px rgba(0,0,0,.34) !important;
        }
        body.dark-mode .core-insight span { color:#94a3b8 !important; }
        body.dark-mode .core-insight strong { color:#6ee7b7 !important; }
        body.dark-mode .core-insight small.core-sub { color:#64748b !important; }

        body.dark-mode .loading-overlay { background:rgba(17,24,39,.9) !important; }

        /* Dark mode insight items */
        body.dark-mode .insight-item {
            background:#1a2a1a !important;
            border-color:#2ecc71 !important;
            color:#d1fae5 !important;
        }
        body.dark-mode .insight-item:hover {
            box-shadow:0 10px 24px rgba(0,0,0,.34) !important;
            filter:brightness(1.08);
        }
        body.dark-mode .insight-item.critical {
            background:#2d1515 !important;
            border-color:#ef4444 !important;
            color:#fca5a5 !important;
        }
        body.dark-mode .insight-item.warning {
            background:#2a1f0a !important;
            border-color:#f59e0b !important;
            color:#fde68a !important;
        }
        body.dark-mode .insight-item.success {
            background:#0d2b1a !important;
            border-color:#10b981 !important;
            color:#6ee7b7 !important;
        }
        body.dark-mode .insight-item.info {
            background:#0c1d33 !important;
            border-color:#3b82f6 !important;
            color:#93c5fd !important;
        }

        /* Dark mode modal */
        body.dark-mode .modal-content {
            background:#111827 !important;
            color:#e2e8f0 !important;
        }
        body.dark-mode .modal-body {
            background:#111827 !important;
            color:#e2e8f0 !important;
        }
        body.dark-mode .modal-footer {
            background:#111827 !important;
            border-color:#334155 !important;
        }
        body.dark-mode .modal-footer .btn-secondary {
            background:#334155 !important;
            border-color:#475569 !important;
            color:#e2e8f0 !important;
        }
        body.dark-mode .forecast-guide {
            background:#0f172a !important;
            border-color:#334155 !important;
            color:#cbd5e1 !important;
        }
        body.dark-mode .forecast-guide p,
        body.dark-mode .forecast-guide .small { color:#cbd5e1 !important; }
        body.dark-mode .forecast-detail:hover { background:#172033 !important; }
        body.dark-mode .btn-primary:hover,
        body.dark-mode .btn-primary:focus-visible {
            background:linear-gradient(135deg,#2ecc71,#1b5e3f) !important;
            color:#062f1d !important;
            box-shadow:0 8px 20px rgba(46,204,113,.22) !important;
        }
        body.dark-mode .btn-warning:hover,
        body.dark-mode .btn-warning:focus-visible {
            background:linear-gradient(135deg,#fde68a,#f59e0b) !important;
            color:#1f1300 !important;
            box-shadow:0 8px 20px rgba(245,158,11,.22) !important;
        }
        body.dark-mode .text-muted { color:#94a3b8 !important; }
        /* ===== Spacious layout overrides ===== */
        .main-content { padding: 3rem; }
        .card { padding: 2rem; margin-bottom: 2rem; }
        .core-insights { gap: 1.5rem; }
        .chart-container { height: 480px; }
        canvas { max-height: none; }
        @media (max-width: 768px) {
            .main-content { padding: 1rem; }
            .card { padding: 1rem; margin-bottom: 1rem; }
            .core-insights { gap: .75rem; }
            .chart-container { height: 320px; }
        }

        /* Analytics-specific overrides beat the shared nav theme layer. */
        body #mainContent .date-range-panel {
            background:linear-gradient(135deg,#f8fafc,#ecfdf5) !important;
            border-color:#d1fae5 !important;
        }
        body #mainContent .date-input-shell .form-control {
            min-height:46px !important;
            padding-left:2.65rem !important;
            background:#ffffff !important;
            color:#1e293b !important;
            border-color:#b7e4ce !important;
        }
        body #mainContent .date-input-shell .form-control:hover {
            background:#f8fffb !important;
            color:#0f172a !important;
            border-color:#1b5e3f !important;
        }
        body #mainContent .btn-warning {
            background:linear-gradient(135deg,#f59e0b,#d97706) !important;
            border-color:transparent !important;
            color:#ffffff !important;
        }
        body #mainContent .btn-warning:hover,
        body #mainContent .btn-warning:focus-visible {
            background:linear-gradient(135deg,#fbbf24,#b45309) !important;
            color:#1f1300 !important;
        }
        body #mainContent .algo-dropdown .dropdown-toggle:hover,
        body #mainContent .algo-dropdown .dropdown-toggle:focus {
            background:#dff8ec !important;
            color:#062f1d !important;
            border-color:#1b5e3f !important;
        }
        body #mainContent .algo-dropdown .dropdown-item:hover,
        body #mainContent .algo-dropdown .dropdown-item:focus {
            background:#dff8ec !important;
            color:#062f1d !important;
        }
        body.dark-mode #mainContent .date-range-panel {
            background:linear-gradient(135deg,rgba(46,204,113,.12),rgba(15,63,40,.12)),#0f172a !important;
            border-color:rgba(46,204,113,.24) !important;
        }
        body.dark-mode #mainContent .date-input-shell .form-control {
            background:#111827 !important;
            color:#f8fafc !important;
            border-color:#334155 !important;
        }
        body.dark-mode #mainContent .date-input-shell .form-control:hover {
            background:#172033 !important;
            color:#ffffff !important;
            border-color:#6ee7b7 !important;
        }
        body.dark-mode #mainContent .date-input-shell .form-control:focus {
            background:#0f172a !important;
            color:#ffffff !important;
            border-color:#2ecc71 !important;
        }
        body.dark-mode #mainContent .btn-primary:hover,
        body.dark-mode #mainContent .btn-primary:focus-visible {
            background:linear-gradient(135deg,#2ecc71,#1b5e3f) !important;
            color:#062f1d !important;
        }
        body.dark-mode #mainContent .btn-warning,
        body.dark-mode #mainContent .btn-warning:hover,
        body.dark-mode #mainContent .btn-warning:focus-visible {
            border-color:transparent !important;
        }
        body.dark-mode #mainContent .btn-warning:hover,
        body.dark-mode #mainContent .btn-warning:focus-visible {
            background:linear-gradient(135deg,#fde68a,#f59e0b) !important;
            color:#1f1300 !important;
        }
        body.dark-mode #mainContent .algo-dropdown .dropdown-toggle:hover,
        body.dark-mode #mainContent .algo-dropdown .dropdown-toggle:focus,
        body.dark-mode #mainContent .algo-dropdown .dropdown-item:hover,
        body.dark-mode #mainContent .algo-dropdown .dropdown-item:focus {
            background:#163828 !important;
            color:#ffffff !important;
            border-color:#6ee7b7 !important;
        }
        body.dark-mode #mainContent .core-insight:hover,
        body.dark-mode #mainContent .algo-card:hover,
        body.dark-mode #summaryModal .insight-item:hover {
            background:#172033 !important;
            border-color:#6ee7b7 !important;
            color:#f8fafc !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn btn-primary d-lg-none" id="toggle-sidebar"><i class="bi bi-list"></i></button>

    <!--  REQUIRED FOR AJAX NAVIGATION (DO NOT REMOVE)  -->
    <!--  Wrap ALL page content inside this div           -->
    <div id="mainContent" class="main-content">

        <div class="page-header">
            <h2><i class="bi bi-graph-up-arrow me-2"></i>Forecasting &amp; Analytics</h2>
            <p>Demand trends, forecasts and low-stock alerts</p>
        </div>

        <!-- Date filter + Summary Button -->
        <div class="filter-card">
            <h6><i class="bi bi-funnel"></i> Date range</h6>
            <div class="analytics-date-grid">
                <div class="date-range-panel" aria-label="Analytics date range">
                    <div class="date-field">
                        <label class="form-label" for="start-date"><i class="bi bi-calendar-event"></i> Start</label>
                        <div class="date-input-shell">
                            <i class="bi bi-calendar3"></i>
                            <input type="date" class="form-control" id="start-date" aria-describedby="date-range-status">
                        </div>
                    </div>
                    <div class="date-range-separator" aria-hidden="true"><i class="bi bi-arrow-right"></i></div>
                    <div class="date-field">
                        <label class="form-label" for="end-date"><i class="bi bi-calendar-check"></i> End</label>
                        <div class="date-input-shell">
                            <i class="bi bi-calendar3"></i>
                            <input type="date" class="form-control" id="end-date" aria-describedby="date-range-status">
                        </div>
                    </div>
                    <div class="date-range-status" id="date-range-status">
                        <i class="bi bi-info-circle"></i>
                        <span id="date-range-label">Select a start and end date.</span>
                    </div>
                </div>
                <div><button class="btn btn-primary w-100" id="apply-filter"><i class="bi bi-check-circle"></i> Apply</button></div>
                <div>
                    <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#summaryModal">
                        <i class="bi bi-lightbulb-fill"></i> Summary
                    </button>
                </div>
            </div>
        </div>

        <!-- Algorithm filter dropdown -->
        <div class="filter-card mb-2" aria-label="Algorithm filter">
            <div class="algo-filter-panel">
                <div class="algo-filter-copy">
                    <h6><i class="bi bi-funnel"></i> Algorithm</h6>
                    <p>Choose one view or show every analytics model.</p>
                </div>
                <div class="dropdown algo-dropdown">
                    <button class="btn dropdown-toggle w-100" id="algoFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        <span id="algoFilterLabel">All Algorithms</span>
                    </button>
                    <ul class="dropdown-menu" id="algoFilterMenu" aria-labelledby="algoFilterBtn">
                        <li><a class="dropdown-item algo-select active" href="#" data-algo="all"><i class="bi bi-grid-3x3-gap"></i>All Algorithms</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="demand"><i class="bi bi-graph-up"></i>Demand Forecast</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="top-medicines"><i class="bi bi-bar-chart"></i>Top 10 Items</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="supply-vs-demand"><i class="bi bi-arrow-left-right"></i>Supply vs Demand</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="random-forest"><i class="bi bi-cpu"></i>Random Forest</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="isolation-forest"><i class="bi bi-shield-exclamation"></i>Isolation Forest</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="prophet"><i class="bi bi-calendar3-week"></i>Facebook Prophet</a></li>
                        <li><a class="dropdown-item algo-select" href="#" data-algo="low-stock"><i class="bi bi-exclamation-triangle"></i>Low-Stock Forecast</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="core-insights" aria-label="Core analytics insights">
            <div class="core-insight"><span>Total Demand</span><strong id="core-demand">Loading...</strong></div>
            <div class="core-insight"><span>Total Supply</span><strong id="core-supply">Loading...</strong></div>
            <div class="core-insight"><span>Top Item</span><strong id="core-top">Loading...</strong></div>
            <div class="core-insight">
                <span>Low-Stock Risk</span>
                <strong id="core-risk">Loading...</strong>
                <small class="core-sub" id="core-risk-sub">&nbsp;</small>
            </div>
        </div>

        <div class="row algorithm-grid" id="algorithmGrid">
            <!-- Demand forecast -->
            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="demand">
                    <h5><i class="bi bi-graph-up"></i> Demand Forecast
                        <span id="demand-loading" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                    </h5>
                    <div class="chart-container"><canvas id="demandChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Actual Demand shows recorded units removed. The moving average smooths short-term variation, and Forecast shows the estimated next 7 days.</p></div>
                </div>
            </div>

            <!-- Top medicines -->
            <div class="col-lg-6 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="top-medicines">
                    <h5><i class="bi bi-bar-chart"></i> Top 10 Items</h5>
                    <div class="chart-container"><canvas id="topMedicinesChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Longer bars represent items with more units dispensed during the selected period.</p></div>
                </div>
            </div>

            <!-- Supply vs Demand -->
            <div class="col-lg-6 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="supply-vs-demand">
                    <h5><i class="bi bi-arrow-left-right"></i> Supply vs Demand</h5>
                    <div class="chart-container"><canvas id="trendsChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Compare Demand with Supply by date. Supply above demand indicates restocking is keeping pace; demand above supply indicates pressure on stock.</p></div>
                </div>
            </div>

            

            <!-- Random Forest forecasting overview -->
            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="random-forest">
                    <h5><i class="bi bi-cpu"></i> Machine Learning Models for Retail Demand Forecasting Using Random Forest</h5>
                    <div class="chart-container"><canvas id="randomForestForecastChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Demand Pattern, Stock Trend, Seasonality, and Replenishment are live-data-derived scores from 20 to 100. Higher scores indicate stronger activity or replenishment pressure.</p></div>
                </div>
            </div>

            <!-- Isolation Forest anomaly detection overview -->
            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="isolation-forest">
                    <h5><i class="bi bi-shield-exclamation"></i> Detecting Inventory Anomalies Through Isolation Forest in Retail Stock Audits</h5>
                    <p class="mb-3 small text-muted">
                        Isolation Forest highlights unusual stock movement and suspicious inventory patterns so audits can focus on high-risk items.
                    </p>
                    <div class="chart-container"><canvas id="isolationForestChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Higher Anomaly Score values indicate unusual demand movement. Audit Risk Trend follows the stock-related signal used to prioritize review.</p></div>
                </div>
            </div>

            <!-- Facebook Prophet forecasting overview -->
            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="prophet">
                    <h5><i class="bi bi-calendar3-week"></i> Predicting Drug Expenditures Using Facebook Prophet in Pharmaceutical Installations</h5>
                    <p class="mb-3 small text-muted">
                        Facebook Prophet models recurring seasonal patterns and long-term trends in drug expenditure so pharmaceutical installations can anticipate spending changes over time.
                    </p>
                    <div class="chart-container"><canvas id="prophetForecastChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Actual Expenditure reflects recorded demand scaled for comparison. Prophet Forecast shows the calculated forward trend from recent demand behavior.</p></div>
                </div>
            </div>

            <!-- Low-stock forecast -->
            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="low-stock">
                    <h5><i class="bi bi-exclamation-triangle"></i> Low-Stock Forecast
                        <span class="badge bg-light text-dark border ms-2" id="low-stock-threshold-badge" style="font-weight:500;">&nbsp;</span>
                    </h5>
                    <div class="chart-container"><canvas id="stockForecastChart"></canvas></div>
                    <div class="forecast-guide" aria-live="polite">
                        <p><strong>How to read this:</strong> Each bar estimates how many days the current stock may last at the recent average usage rate.</p>
                        <p class="small mb-0"><i class="bi bi-info-circle me-1"></i>Red means critical: at or below the critical stock limit or projected to empty in under 7 days. Amber means warning: at or below the low-stock limit or projected to empty within 14 days. Items without demand history are flagged by stock level only.</p>
                        <div id="low-stock-forecast-details" class="forecast-details"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <!--  END OF #mainContent  -->

    <!-- SUMMARY MODAL -->
    <div class="modal fade" id="summaryModal" tabindex="-1" aria-labelledby="summaryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius:18px;overflow:hidden;">
                <div class="modal-header text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                    <h5 class="modal-title" id="summaryModalLabel"><i class="bi bi-lightbulb-fill me-2"></i>Analytics Summary</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="modal-summary-content">
                    <p class="text-muted small"><i class="bi bi-hourglass-split"></i> Loading insights...</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Embedded analytics.js with MODAL SUPPORT -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const startDate = document.getElementById('start-date');
        const endDate   = document.getElementById('end-date');
        const applyBtn  = document.getElementById('apply-filter');
        const demandLoading = document.getElementById('demand-loading');
        const modalContent = document.getElementById('modal-summary-content');
        const coreDemand = document.getElementById('core-demand');
        const coreSupply = document.getElementById('core-supply');
        const coreTop = document.getElementById('core-top');
        const coreRisk = document.getElementById('core-risk');
        const coreRiskSub = document.getElementById('core-risk-sub');
        const lowStockThresholdBadge = document.getElementById('low-stock-threshold-badge');
        const lowStockForecastDetails = document.getElementById('low-stock-forecast-details');
        const dateRangeLabel = document.getElementById('date-range-label');
        const chartCanvasIds = ['demandChart', 'topMedicinesChart', 'trendsChart', 'stockForecastChart', 'randomForestForecastChart', 'isolationForestChart', 'prophetForecastChart'];

        let demandChart, topChart, trendsChart, stockChart;

        // Helper: get Chart.js scale/legend colors based on dark mode
        const chartColors = () => {
            const dark = document.body.classList.contains('dark-mode');
            return {
                grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
                tick: dark ? '#94a3b8' : '#64748b',
                legend: dark ? '#e2e8f0' : '#374151',
            };
        };

        const darkScales = () => {
            const c = chartColors();
            return {
                x: {
                    grid: { color: c.grid },
                    ticks: { color: c.tick },
                    title: { color: c.tick }
                },
                y: {
                    grid: { color: c.grid },
                    ticks: { color: c.tick },
                    title: { color: c.tick }
                }
            };
        };

        const darkPlugins = (extra = {}) => {
            const c = chartColors();
            return {
                legend: { labels: { color: c.legend }, ...extra.legend },
                tooltip: { ...extra.tooltip }
            };
        };

        const initializeDateRange = async () => {
            const end = new Date();
            end.setHours(0, 0, 0, 0);
            const start = new Date(end);
            start.setDate(start.getDate() - 13);
            const formatDate = date => date.toISOString().split('T')[0];
            startDate.value = formatDate(start);
            endDate.value = formatDate(end);
            updateDateRangeUi();
        };

        const formatRangeDate = (value) => {
            if (!value) return '';
            const date = new Date(`${value}T00:00:00`);
            return Number.isNaN(date.getTime())
                ? value
                : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        };

        const updateDateRangeUi = () => {
            if (!startDate || !endDate) return;
            if (startDate.value) {
                endDate.min = startDate.value;
                if (endDate.value && new Date(endDate.value) < new Date(startDate.value)) {
                    endDate.value = startDate.value;
                }
            } else {
                endDate.removeAttribute('min');
            }
            if (endDate.value) {
                startDate.max = endDate.value;
            } else {
                startDate.removeAttribute('max');
            }
            if (!dateRangeLabel) return;
            if (startDate.value && endDate.value) {
                dateRangeLabel.textContent = `${formatRangeDate(startDate.value)} to ${formatRangeDate(endDate.value)}`;
            } else if (startDate.value) {
                dateRangeLabel.textContent = `Starting ${formatRangeDate(startDate.value)}. Choose an end date.`;
            } else if (endDate.value) {
                dateRangeLabel.textContent = `Ending ${formatRangeDate(endDate.value)}. Choose a start date.`;
            } else {
                dateRangeLabel.textContent = 'Select a start and end date.';
            }
        };

        const showToast = (msg, type = 'success') => {
            const container = document.getElementById('toast-container') ||
                (() => {
                    const c = document.createElement('div');
                    c.id = 'toast-container';
                    c.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                    document.body.appendChild(c);
                    return c;
                })();
            const t = document.createElement('div');
            t.className = `toast align-items-center text-bg-${type} border-0 shadow-lg`;
            t.innerHTML = `<div class="d-flex">
                              <div class="toast-body">${msg}</div>
                              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                           </div>`;
            container.appendChild(t);
            new bootstrap.Toast(t).show();
            t.addEventListener('hidden.bs.toast', () => t.remove());
        };

        const movingAvg = (arr, window = 7) => {
            if (arr.length === 0) return [];
            const out = [];
            for (let i = 0; i < arr.length; i++) {
                if (i < window - 1) out.push(null);
                else {
                    let sum = 0;
                    for (let j = 0; j < window; j++) sum += arr[i - j];
                    out.push(sum / window);
                }
            }
            return out;
        };

        const forecast7 = (values) => {
            const n = values.length;
            if (n < 2) return [];
            let sx = 0, sy = 0, sxy = 0, sxx = 0;
            for (let i = 0; i < n; i++) {
                sx  += i; sy  += values[i]; sxy += i * values[i]; sxx += i * i;
            }
            const denom = n * sxx - sx * sx;
            if (denom === 0) return [];
            const slope = (n * sxy - sx * sy) / denom;
            const intercept = (sy - slope * sx) / n;
            const out = [];
            for (let i = n; i < n + 7; i++) {
                out.push(Math.max(0, intercept + slope * i));
            }
            return out;
        };

        const showEmptyState = (canvasId, message) => {
            const canvas = document.getElementById(canvasId);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.font = '16px Inter, sans-serif';
            ctx.fillStyle = document.body.classList.contains('dark-mode') ? '#94a3b8' : '#64748b';
            ctx.textAlign = 'center';
            ctx.fillText(message, canvas.width / 2, canvas.height / 2);
        };

        const showInitialEmptyState = () => {
            chartCanvasIds.forEach(id => showEmptyState(id, 'Choose a date range, then click Apply'));
            coreDemand.textContent = 'Set dates';
            coreSupply.textContent = 'Set dates';
            coreTop.textContent = 'Set dates';
            coreRisk.textContent = 'Set dates';
            if (coreRiskSub) coreRiskSub.textContent = 'Manual date range required';
            if (lowStockThresholdBadge) lowStockThresholdBadge.textContent = 'Set dates';
            if (lowStockForecastDetails) lowStockForecastDetails.innerHTML = '';
        };

        const dayThresholdLinePlugin = (criticalDays = 7, warningDays = 14) => ({
            id: 'dayThresholdLines',
            afterDraw(chart) {
                const { ctx, chartArea, scales } = chart;
                if (!scales || !scales.x) return;
                const xScale = scales.x;
                const drawLine = (value, color, label) => {
                    if (value === null || value === undefined) return;
                    const xPos = xScale.getPixelForValue(value);
                    if (xPos < chartArea.left || xPos > chartArea.right) return;
                    ctx.save();
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 1.5;
                    ctx.setLineDash([5, 4]);
                    ctx.beginPath();
                    ctx.moveTo(xPos, chartArea.top);
                    ctx.lineTo(xPos, chartArea.bottom);
                    ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.fillStyle = color;
                    ctx.font = '11px Inter, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(label, xPos, chartArea.top - 4);
                    ctx.restore();
                };
                drawLine(criticalDays, '#ef4444', `Critical ${criticalDays}d`);
                drawLine(warningDays, '#f59e0b', `Warning ${warningDays}d`);
            }
        });

        const anomalyThresholdLinePlugin = (low = 0.5, high = 0.75) => ({
            id: 'anomalyThresholdLines',
            afterDraw(chart) {
                const { ctx, chartArea, scales } = chart;
                if (!scales || !scales.x) return;
                const xScale = scales.x;
                const drawLine = (value, color, label) => {
                    const xPos = xScale.getPixelForValue(value);
                    if (xPos < chartArea.left || xPos > chartArea.right) return;
                    ctx.save();
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 1.5;
                    ctx.setLineDash([5, 4]);
                    ctx.beginPath();
                    ctx.moveTo(xPos, chartArea.top);
                    ctx.lineTo(xPos, chartArea.bottom);
                    ctx.stroke();
                    ctx.setLineDash([]);
                    ctx.fillStyle = color;
                    ctx.font = '11px Inter, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.fillText(label, xPos, chartArea.top - 4);
                    ctx.restore();
                };
                drawLine(low, '#10b981', `Low ${low}`);
                drawLine(high, '#f59e0b', `High ${high}`);
            }
        });




        const loadDemand = (s, e) => {
            demandLoading.style.display = 'inline-block';
            fetch(`api/phar_analytics.php?action=daily_trends&start=${s}&end=${e}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error || 'API error');
                    const data = res.data || [];
                    if (data.length === 0) {
                        if (demandChart) demandChart.destroy();
                        showEmptyState('demandChart', 'No transactions found in this date range');
                        return;
                    }
                    const labels = data.map(d => d.date);
                    const demand = data.map(d => parseFloat(d.total_demand) || 0);
                    const supply = data.map(d => parseFloat(d.total_supply) || 0);
                    const ma = movingAvg(demand);
                    const fc = forecast7(demand);
                    const fcDates = [];
                    if (labels.length) {
                        const last = new Date(labels[labels.length - 1]);
                        for (let i = 1; i <= 7; i++) {
                            const nd = new Date(last);
                            nd.setDate(last.getDate() + i);
                            fcDates.push(nd.toISOString().split('T')[0]);
                        }
                    }
                    const allLabels = [...labels, ...fcDates];
                    const fcData = new Array(labels.length).fill(null).concat(fc);
                    const maData = [...ma, ...new Array(7).fill(null)];
                    if (demandChart) demandChart.destroy();
                    const ctx = document.getElementById('demandChart').getContext('2d');
                    const sc = darkScales();
                    sc.y.beginAtZero = true;
                    sc.y.title = { display: true, text: 'Units', color: chartColors().tick };
                    sc.x.title = { display: true, text: 'Date', color: chartColors().tick };
                    demandChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: allLabels,
                            datasets: [
                                { label: 'Actual Demand', data: [...demand, ...new Array(7).fill(null)], borderColor: '#1b5e3f', backgroundColor: 'rgba(44,82,130,.1)', fill: true, tension: .4 },
                                { label: '7-day MA', data: maData, borderColor: '#10b981', borderDash: [5,5], tension: .4, pointRadius: 0 },
                                { label: 'Forecast (7 days)', data: fcData, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.1)', fill: true, tension: .4, pointStyle: 'triangle' }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { ...darkPlugins({ tooltip: { mode: 'index', intersect: false } }), legend: { position: 'top', labels: { color: chartColors().legend } } },
                            scales: sc
                        }
                    });
                    window.demandData = { demand, supply, forecast: fc, dates: labels };
                    renderRandomForestForecastChart();
                })
                .catch(err => {
                    console.error('Demand error:', err);
                    showToast('Error loading demand forecast: ' + err.message, 'danger');
                    if (demandChart) demandChart.destroy();
                    showEmptyState('demandChart', 'Error loading data');
                })
                .finally(() => demandLoading.style.display = 'none');
        };

        const loadTop = (s, e) => {
            fetch(`api/phar_analytics.php?action=top_medicines&limit=10&start=${s}&end=${e}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error);
                    const d = res.data || [];
                    if (d.length === 0) {
                        if (topChart) topChart.destroy();
                        showEmptyState('topMedicinesChart', 'No item demand found in this date range');
                        return;
                    }
                    const labels = d.map(x => x.medicine_name);
                    const vals   = d.map(x => parseFloat(x.total_demand) || 0);
                    if (topChart) topChart.destroy();
                    const ctx = document.getElementById('topMedicinesChart').getContext('2d');
                    const sc = darkScales();
                    sc.x.beginAtZero = true;
                    sc.x.title = { display: true, text: 'Units', color: chartColors().tick };
                    delete sc.y;
                    topChart = new Chart(ctx, {
                        type: 'bar',
                        data: { labels, datasets: [{ label: 'Demand', data: vals, backgroundColor: ['#1b5e3f','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#06b6d4'], borderRadius: 8 }] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: { legend: { display: false } },
                            scales: sc
                        }
                    });
                    window.topMedicines = d.slice(0, 3);
                })
                .catch(err => {
                    console.error('Top items error:', err);
                    showToast('Error loading top items: ' + err.message, 'danger');
                });
        };

        const loadTrends = (s, e) => {
            fetch(`api/phar_analytics.php?action=daily_trends&start=${s}&end=${e}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error);
                    const d = res.data || [];
                    if (d.length === 0) {
                        if (trendsChart) trendsChart.destroy();
                        showEmptyState('trendsChart', 'No transactions found in this date range');
                        return;
                    }
                    const labels = d.map(x => x.date);
                    const demand = d.map(x => parseFloat(x.total_demand) || 0);
                    const supply = d.map(x => parseFloat(x.total_supply) || 0);
                    if (trendsChart) trendsChart.destroy();
                    const ctx = document.getElementById('trendsChart').getContext('2d');
                    const sc = darkScales();
                    sc.y.beginAtZero = true;
                    sc.y.title = { display: true, text: 'Units', color: chartColors().tick };
                    trendsChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [
                                { label: 'Demand', data: demand, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.1)', fill: true, tension: .4 },
                                { label: 'Supply', data: supply, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4 }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'top', labels: { color: chartColors().legend } } },
                            scales: sc
                        }
                    });
                    const totalDemand = demand.reduce((a,b) => a+b, 0);
                    const totalSupply = supply.reduce((a,b) => a+b, 0);
                    window.supplyDemand = { totalDemand, totalSupply, net: totalSupply - totalDemand };
                })
                .catch(err => {
                    console.error('Trends error:', err);
                    showToast('Error loading trends: ' + err.message, 'danger');
                });
        };

        /* ---------------------------------------------------------------
         * Translate a backend "reason" code into a short human sentence.
         * Mirrors the same helper used in the standalone analytics.js so
         * both pages explain low-stock items the same way.
         * --------------------------------------------------------------- */
        const describeReason = (reason) => {
            switch (reason) {
                case 'stock_at_or_below_critical':
                    return 'stock is at or below the Critical Stock Threshold';
                case 'stock_at_or_below_low':
                    return 'stock is at or below the Low Stock Threshold';
                case 'depletes_under_7_days':
                    return 'projected to run out in under 7 days at current usage';
                case 'depletes_under_14_days':
                    return 'projected to run out in under 14 days at current usage';
                default:
                    return reason;
            }
        };

        const loadLowStock = (s, e) => {
            fetch(`api/phar_analytics.php?action=low_stock_forecast&start=${encodeURIComponent(s)}&end=${encodeURIComponent(e)}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error);
                    const d = (res.data || []).slice(0, 10);

                    // These come straight from System Settings (resolved server-side),
                    // so the badge above the chart always reflects the live configured values.
                    window.lowStockThresholds = {
                        low: res.low_threshold ?? res.threshold ?? null,
                        critical: res.critical_threshold ?? null
                    };
                    if (lowStockThresholdBadge) {
                        const lo = window.lowStockThresholds.low;
                        const cr = window.lowStockThresholds.critical;
                        lowStockThresholdBadge.textContent =
                            (lo !== null || cr !== null)
                                ? `Thresholds: Low ≤ ${lo ?? '—'} units · Critical ≤ ${cr ?? '—'} units`
                                : '';
                    }

                    if (d.length === 0) {
                        if (stockChart) stockChart.destroy();
                        if (lowStockForecastDetails) lowStockForecastDetails.innerHTML = '<div class="small text-success"><i class="bi bi-check-circle me-1"></i>No items meet the low-stock or 14-day depletion criteria.</div>';
                        const ctx = document.getElementById('stockForecastChart').getContext('2d');
                        stockChart = new Chart(ctx, {
                            type: 'doughnut',
                            data: {
                                labels: ['Stock levels stable'],
                                datasets: [{ data: [1], backgroundColor: ['#1b5e3f'], borderWidth: 0 }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                cutout: '68%',
                                plugins: {
                                    legend: { position: 'bottom', labels: { color: chartColors().legend } },
                                    tooltip: { callbacks: { label: () => 'No low-stock forecast items detected' } }
                                }
                            }
                        });
                        window.lowStock = { critical: [], warning: [], items: [] };
                            generateModalSummary();
                        return;
                    }
                    const labels = d.map(x => x.name);
                    const days = d.map(x => x.has_demand_history ? Number(x.days_until_empty) : 0);
                    const colors = d.map(x => x.status === 'critical' ? '#ef4444' : x.status === 'warning' ? '#f59e0b' : '#10b981');

                    if (stockChart) stockChart.destroy();
                    const ctx = document.getElementById('stockForecastChart').getContext('2d');
                    if (lowStockForecastDetails) {
                        const lowThreshold = Number(window.lowStockThresholds.low);
                        const criticalThreshold = Number(window.lowStockThresholds.critical);
                        lowStockForecastDetails.innerHTML = d.map(item => {
                            const stock = Number(item.current_stock) || 0;
                            const demand = Number(item.avg_daily_demand) || 0;
                            const daysRemaining = item.has_demand_history && item.days_until_empty !== null
                                ? `${Number(item.days_until_empty).toFixed(1)} days estimated`
                                : 'No demand history';
                            const stockReason = item.status === 'critical' && stock <= criticalThreshold
                                ? 'at/below critical stock limit'
                                : item.status === 'warning' && stock <= lowThreshold
                                    ? 'at/below low-stock limit'
                                    : item.status === 'critical' ? 'projected to empty in under 7 days' : 'projected to empty within 14 days';
                            return `<div class="forecast-detail ${item.status === 'critical' ? 'critical' : ''}"><strong>${item.name}</strong><br>${stock} units left; ${demand > 0 ? `${demand.toFixed(2)} units/day used` : 'usage rate unavailable'}<br><span>${daysRemaining}; ${stockReason}.</span></div>`;
                        }).join('');
                    }

                    stockChart = new Chart(ctx, {
                        type: 'bar',
                        data: { labels, datasets: [{ label: 'Days until empty', data: days, backgroundColor: colors, borderRadius: 8 }] },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: c => d[c.dataIndex].has_demand_history
                                            ? `Estimated depletion: ${c.parsed.x.toFixed(1)} days`
                                            : 'Estimated depletion: unavailable',
                                        afterLabel: c => {
                                            const item = d[c.dataIndex];
                                            const stock = Number(item.current_stock) || 0;
                                            const demand = Number(item.avg_daily_demand) || 0;
                                            return [
                                                `Current stock: ${stock} units`,
                                                `Average usage: ${demand > 0 ? `${demand.toFixed(2)} units/day` : 'No demand history'}`,
                                                `Status: ${item.status === 'critical' ? 'Critical action needed' : 'Warning; plan replenishment'}`
                                            ];
                                        }
                                    }
                                }
                            },
                            scales: { x: { beginAtZero: true, title: { display: true, text: 'Days until depletion' } } }
                        }
                    });
                    window.lowStock = {
                        critical: d.filter(x => x.status === 'critical').map(x => x.name),
                        warning:  d.filter(x => x.status === 'warning').map(x => x.name),
                        items: d
                    };
                    generateModalSummary();
                })
                .catch(err => {
                    console.error('Low-stock error:', err);
                    showToast('Error loading low-stock forecast: ' + err.message, 'danger');
                    if (stockChart) stockChart.destroy();
                    showEmptyState('stockForecastChart', 'Error loading data');
                });
        };

        const generateModalSummary = () => {
            const { demandData, supplyDemand, topMedicines, lowStock, lowStockThresholds, lowStockModel, modelForecastData } = window;
            const start = startDate.value;
            const end = endDate.value;
            const period = start && end
                ? `${new Date(start).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} to ${new Date(end).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`
                : 'selected period';

            let html = `<div class="small text-muted mb-3"><strong>Period:</strong> ${period}<br>
                         This summary translates the charts above into plain language so you can quickly see what's going on and what needs attention.</div>`;

            if (supplyDemand) {
                const { totalDemand, totalSupply, net } = supplyDemand;
                const isSurplus = net >= 0;
                const icon = isSurplus ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger';
                const bgClass = isSurplus ? 'success' : 'critical';
                html += `
                <div class="insight-item ${bgClass} d-flex align-items-start">
                    <i class="bi bi-arrow-left-right icon"></i>
                    <div>
                        <strong>Supply vs Demand</strong><br>
                        <small><strong>${totalSupply}</strong> supplied &bull; <strong>${totalDemand}</strong> dispensed</small><br>
                        <span class="badge bg-${isSurplus ? 'success' : 'danger'} mt-1">
                            <i class="${icon}"></i> ${isSurplus ? '+' : ''}${net} ${isSurplus ? 'surplus' : 'shortfall'}
                        </span>
                        <div class="small mt-1">${isSurplus ? 'Restocking is keeping pace with usage in this period.' : 'Usage is outpacing restocking — this tends to increase the number of low-stock items if it continues.'}</div>
                    </div>
                </div>`;
            }

            if (modelForecastData) {
                const scores = modelForecastData.model_scores || {};
                const humanizeModelKey = (key) => key
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (c) => c.toUpperCase())
                    .replace(/Svm\b/, 'SVM')
                    .replace(/Xgboost\b/, 'XGBoost');
                const ranked = Object.entries(scores)
                    .filter(([, score]) => Number.isFinite(score))
                    .sort((a, b) => b[1] - a[1]);
                const winner = ranked[0] || null;
                const hasScoreData = ranked.length > 0;
                const topModels = ranked.slice(0, 3).map(([key, score]) => `${humanizeModelKey(key)} (${score}/100)`).join(', ');
                const modelText = hasScoreData
                    ? `Best performing model: <strong>${humanizeModelKey(winner[0])}</strong> (${winner[1]}/100)`
                    : 'Forecast scoring is still being calculated from available transaction data.';
                const modelSummaryText = ranked.length > 1 ? `Top models: ${topModels}.` : '';

                const demandPattern = Array.isArray(modelForecastData.demand_pattern) && modelForecastData.demand_pattern.length ? modelForecastData.demand_pattern[modelForecastData.demand_pattern.length - 1] : null;
                const stockTrend = Array.isArray(modelForecastData.stock_trend) && modelForecastData.stock_trend.length ? modelForecastData.stock_trend[modelForecastData.stock_trend.length - 1] : null;
                const seasonality = Array.isArray(modelForecastData.seasonality) && modelForecastData.seasonality.length ? modelForecastData.seasonality[modelForecastData.seasonality.length - 1] : null;
                const replenishment = Array.isArray(modelForecastData.replenishment) && modelForecastData.replenishment.length ? modelForecastData.replenishment[modelForecastData.replenishment.length - 1] : null;

                const trendLines = [];
                if (demandPattern !== null) trendLines.push(`Demand is likely to trend <strong>${demandPattern >= 65 ? 'upward' : demandPattern <= 35 ? 'downward' : 'stable'}</strong> within about <strong>${demandPattern >= 65 ? 5 : demandPattern <= 35 ? 7 : 10}</strong> days.`);
                if (stockTrend !== null) trendLines.push(`Stock trend is expected to move <strong>${stockTrend >= 65 ? 'upward' : stockTrend <= 35 ? 'downward' : 'stable'}</strong> within about <strong>${stockTrend >= 65 ? 6 : stockTrend <= 35 ? 8 : 12}</strong> days.`);
                if (seasonality !== null) trendLines.push(`Seasonal pattern is <strong>${seasonality >= 65 ? 'peak season' : seasonality <= 35 ? 'low season' : 'normal season'}</strong>, with the shift likely in roughly <strong>${seasonality >= 65 ? 14 : seasonality <= 35 ? 16 : 21}</strong> days.`);
                if (replenishment !== null) trendLines.push(`Replenishment signal: <strong>${replenishment >= 65 ? 'replenish soon' : replenishment <= 35 ? 'hold replenishment' : 'maintain current stock'}</strong> and likely within <strong>${replenishment >= 65 ? 3 : replenishment <= 35 ? 12 : 9}</strong> days.`);

                const prophetForecast = Array.isArray(modelForecastData.prophet_forecast) ? modelForecastData.prophet_forecast : [];
                const hasProphetData = prophetForecast.length >= 2;
                const anomalyScore = Array.isArray(modelForecastData.anomaly_scores) && modelForecastData.anomaly_scores.length
                    ? modelForecastData.anomaly_scores[modelForecastData.anomaly_scores.length - 1]
                    : null;
                const prophetTrend = hasProphetData
                    ? (prophetForecast[prophetForecast.length - 1] > prophetForecast[0] * 1.05 ? 'increasing'
                       : prophetForecast[prophetForecast.length - 1] < prophetForecast[0] * 0.95 ? 'decreasing'
                       : 'stable')
                    : null;
                const prophetSummary = prophetTrend === 'increasing'
                    ? 'Facebook Prophet expects drug expenditure to rise over the next 5 days, driven by recurring seasonal demand.'
                    : prophetTrend === 'decreasing'
                        ? 'Facebook Prophet expects drug expenditure to decrease over the next 9 days, reflecting easing demand after the recent peak.'
                        : prophetTrend === 'stable'
                            ? 'Facebook Prophet expects expenditure to remain stable, with seasonality balancing demand in the coming days.'
                            : null;
                const prophetWhy = prophetTrend === 'increasing'
                    ? 'This means stock needs are likely to increase soon, especially for fast-moving items.'
                    : prophetTrend === 'decreasing'
                        ? 'This means replenishment can be paced more carefully, since demand pressure is easing.'
                        : prophetTrend === 'stable'
                            ? 'This means current stock levels can remain steady, but continue monitoring fast-moving items for seasonal shifts.'
                            : null;
                const prophetAction = prophetTrend === 'increasing'
                    ? (anomalyScore >= 65
                        ? 'Recommendation: review purchase orders for top items and prioritize stock checks for high-risk items.'
                        : 'Recommendation: review purchase orders for top items and schedule replenishment in 3–5 days.')
                    : prophetTrend === 'decreasing'
                        ? 'Recommendation: hold new large orders and review inventory before the next seasonal shift.'
                        : prophetTrend === 'stable'
                            ? 'Recommendation: continue monitoring demand and keep replenishment aligned with current usage.'
                            : null;

                if (hasScoreData || trendLines.length) {
                    html += `
                    <div class="insight-item info d-flex align-items-start">
                        <i class="bi bi-cpu icon"></i>
                        <div>
                            <strong>Retail Demand Forecast</strong><br>
                            <small>${modelText}</small><br>
                            ${modelSummaryText ? `<small>${modelSummaryText}</small>` : ''}
                            ${trendLines.length ? `<div class="small mt-1">${trendLines.join('<br>')}</div>` : ''}
                        </div>
                    </div>`;
                }

                if (prophetSummary) {
                    html += `
                    <div class="insight-item warning d-flex align-items-start">
                        <i class="bi bi-calendar3-week icon"></i>
                        <div>
                            <strong>Facebook Prophet Forecast</strong><br>
                            <small>${prophetSummary}</small>
                            <div class="small mt-2"><strong>Why it matters:</strong> ${prophetWhy}</div>
                            <div class="small mt-2"><strong>What to do next:</strong> ${prophetAction}</div>
                        </div>
                    </div>`;
                }
            }

            if (modelForecastData && Array.isArray(modelForecastData.anomaly_scores) && modelForecastData.anomaly_scores.length > 0) {
                const anomalyScore = modelForecastData.anomaly_scores[modelForecastData.anomaly_scores.length - 1] ?? 0;
                const auditDirection = anomalyScore >= 65 ? 'upward' : anomalyScore <= 35 ? 'downward' : 'stable';
                const auditDays = anomalyScore >= 65 ? 4 : anomalyScore <= 35 ? 9 : 12;
                const auditLevel = anomalyScore >= 70 ? 'high risk' : anomalyScore >= 45 ? 'moderate risk' : 'low risk';
                const suspiciousItems = (window.lowStock && Array.isArray(window.lowStock.items) && window.lowStock.items.length)
                    ? window.lowStock.items.slice(0, 3).map(item => item.name)
                    : (Array.isArray(topMedicines) ? topMedicines.slice(0, 3).map(item => item.medicine_name) : []);
                const suspiciousText = suspiciousItems.length
                    ? ` Most suspicious items: <strong>${suspiciousItems.join(', ')}</strong>.`
                    : '';

                html += `
                <div class="insight-item warning d-flex align-items-start">
                    <i class="bi bi-shield-exclamation icon"></i>
                    <div>
                        <strong>Isolation Audit Forecast</strong><br>
                        <small>Current audit anomaly score: <strong>${anomalyScore}</strong> / 100</small>
                        <div class="small mt-1">
                            Inventory anomaly risk is <strong>${auditLevel}</strong> and the audit signal is likely to move <strong>${auditDirection}</strong> within about <strong>${auditDays}</strong> days.${suspiciousText}
                        </div>
                    </div>
                </div>`;
            }

            if (demandData && demandData.forecast.length > 0) {
                const lastActual = demandData.demand[demandData.demand.length - 1];
                const forecastAvg = demandData.forecast.reduce((a,b) => a+b, 0) / 7;
                const trend = forecastAvg > lastActual * 1.1 ? 'increasing' : forecastAvg < lastActual * 0.9 ? 'decreasing' : 'stable';
                const icon = trend === 'increasing' ? 'bi-graph-up-arrow text-danger' : trend === 'decreasing' ? 'bi-graph-down-arrow text-success' : 'bi-dash-lg text-secondary';
                const bgClass = trend === 'increasing' ? 'critical' : trend === 'decreasing' ? 'success' : 'warning';
                html += `
                <div class="insight-item ${bgClass} d-flex align-items-start">
                    <i class="${icon} icon"></i>
                    <div>
                        <strong>Demand Trend</strong><br>
                        <small>Expected to be <strong>${trend}</strong> (~${Math.round(forecastAvg)} units/day next 7 days)</small>
                    </div>
                </div>`;
            }

            if (topMedicines && topMedicines.length > 0) {
                const names = topMedicines.map(m => m.medicine_name).join(', ');
                html += `
                <div class="insight-item warning d-flex align-items-start">
                    <i class="bi bi-star-fill icon text-warning"></i>
                    <div>
                        <strong>Top Demand</strong><br>
                        <small><strong>${names}</strong> are most dispensed</small>
                    </div>
                </div>`;
            }

            // Explain which thresholds are currently in effect, sourced live
            // from System Settings, before showing what they produced.
            if (lowStockThresholds && (lowStockThresholds.low !== null || lowStockThresholds.critical !== null)) {
                html += `
                <div class="insight-item info d-flex align-items-start">
                    <i class="bi bi-sliders icon"></i>
                    <div>
                        <strong>Thresholds in use (from System Settings)</strong><br>
                        <small>Low Stock Threshold: <strong>${lowStockThresholds.low ?? 'default'}</strong> units &bull; Critical Stock Threshold: <strong>${lowStockThresholds.critical ?? 'default'}</strong> units</small><br>
                        <small class="text-muted">These apply system-wide. Change them on the Settings page to adjust what counts as "low" or "critical" below.</small>
                    </div>
                </div>`;
            }

            if (lowStock) {
                const crit = lowStock.critical;
                const warn = lowStock.warning;
                if (crit.length > 0) {
                    html += `
                    <div class="insight-item critical d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill icon"></i>
                        <div>
                            <strong>CRITICAL STOCK ALERT</strong><br>
                            <small><strong>${crit.join(', ')}</strong> ${crit.length === 1 ? 'is' : 'are'} at/below the critical threshold or projected to run out in under 7 days. Order now!</small>
                        </div>
                    </div>`;
                }
                if (warn.length > 0) {
                    html += `
                    <div class="insight-item warning d-flex align-items-start">
                        <i class="bi bi-exclamation-circle-fill icon"></i>
                        <div>
                            <strong>Low Stock Warning</strong><br>
                            <small><strong>${warn.join(', ')}</strong> ${warn.length === 1 ? 'is' : 'are'} at/below the low-stock threshold or projected to run out in 7&ndash;14 days</small>
                        </div>
                    </div>`;
                }
                if (crit.length === 0 && warn.length === 0) {
                    html += `
                    <div class="insight-item success d-flex align-items-start">
                        <i class="bi bi-check-circle-fill icon"></i>
                        <div>
                            <strong>All Clear</strong><br>
                            <small>All items are above both thresholds and not projected to run out within 14 days (last 30 days of usage)</small>
                        </div>
                    </div>`;
                }
            }

            modalContent.innerHTML = html;
        };

        const updateCoreInsights = () => {
            const demand = window.supplyDemand?.totalDemand ?? 0;
            const supply = window.supplyDemand?.totalSupply ?? 0;
            const top = window.topMedicines?.[0];
            const critical = window.lowStock?.critical?.length || 0;
            const warning = window.lowStock?.warning?.length || 0;
            const lo = window.lowStockThresholds?.low;
            const cr = window.lowStockThresholds?.critical;

            coreDemand.textContent = demand;
            coreSupply.textContent = supply;
            coreTop.textContent = top ? top.medicine_name : 'None';
            coreRisk.textContent = `${critical + warning} items`;

            if (coreRiskSub) {
                if (critical + warning === 0) {
                    coreRiskSub.textContent = (lo !== null && lo !== undefined)
                        ? `None below threshold (≤${lo} units)`
                        : 'None below threshold';
                } else {
                    coreRiskSub.textContent = `${critical} critical, ${warning} warning`
                        + ((lo !== null && lo !== undefined) ? ` · thresholds: ${cr ?? '—'}/${lo ?? '—'} units` : '');
                }
            }
        };

        const renderRandomForestForecastChart = () => {
            const ctx = document.getElementById('randomForestForecastChart');
            if (!ctx) return;
            const chart = Chart.getChart(ctx);
            if (chart) chart.destroy();

            const modelData = window.modelForecastData || {};
            const dates = (modelData.dates || []).map(d => {
                const date = new Date(d);
                return !Number.isNaN(date.getTime()) ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : d;
            });
            const demandPattern = modelData.demand_pattern || [];
            const stockTrend = modelData.stock_trend || [];
            const seasonality = modelData.seasonality || [];
            const replenishment = modelData.replenishment || [];
            const labels = dates.length ? dates : ['Jan 01, 2024', 'Jan 02, 2024', 'Jan 03, 2024', 'Jan 04, 2024'];

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Demand Pattern', data: demandPattern, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35 },
                        { label: 'Stock Trend', data: stockTrend, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.10)', fill: true, tension: .35 },
                        { label: 'Seasonality', data: seasonality, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.10)', fill: true, tension: .35 },
                        { label: 'Replenishment', data: replenishment, borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,.10)', fill: true, tension: .35 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: chartColors().legend } },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        ...darkScales(),
                        x: {
                            ...darkScales().x,
                            title: { display: true, text: 'Date', color: chartColors().tick }
                        },
                        y: {
                            ...darkScales().y,
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Score (%)', color: chartColors().tick }
                        }
                    }
                }
            });
        };

        const renderIsolationForestChart = () => {
            const ctx = document.getElementById('isolationForestChart');
            if (!ctx) return;
            const chart = Chart.getChart(ctx);
            if (chart) chart.destroy();

            const modelData = window.modelForecastData || {};
            const dates = (modelData.dates || []).map(d => {
                const date = new Date(d);
                return !Number.isNaN(date.getTime()) ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : d;
            });
            const anomalyScores = modelData.anomaly_scores || [];
            const anomalyTrend = modelData.stock_trend || [];
            const labels = dates.length ? dates : ['Jan 01, 2024', 'Jan 02, 2024', 'Jan 03, 2024', 'Jan 04, 2024'];

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Anomaly Score', data: anomalyScores, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,.12)', fill: true, tension: .35 },
                        { label: 'Audit Risk Trend', data: anomalyTrend, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.10)', fill: true, tension: .35 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: chartColors().legend } },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        ...darkScales(),
                        x: {
                            ...darkScales().x,
                            title: { display: true, text: 'Date', color: chartColors().tick }
                        },
                        y: {
                            ...darkScales().y,
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Score (%)', color: chartColors().tick }
                        }
                    }
                }
            });
        };

        const renderProphetForecastChart = () => {
            const ctx = document.getElementById('prophetForecastChart');
            if (!ctx) return;
            const chart = Chart.getChart(ctx);
            if (chart) chart.destroy();

            const modelData = window.modelForecastData || {};
            const dates = (modelData.dates || []).map(d => {
                const date = new Date(d);
                return !Number.isNaN(date.getTime()) ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : d;
            });
            const actual = (modelData.demand || []).map(value => Math.min(100, Math.max(20, value)));
            const forecast = modelData.prophet_forecast || [];
            const labels = dates.length ? dates : ['Jan 01, 2024', 'Jan 02, 2024', 'Jan 03, 2024', 'Jan 04, 2024'];

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Actual Expenditure', data: actual, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35 },
                        { label: 'Prophet Forecast', data: forecast, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.10)', fill: true, tension: .35 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: chartColors().legend } },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        ...darkScales(),
                        x: {
                            ...darkScales().x,
                            title: { display: true, text: 'Date', color: chartColors().tick }
                        },
                        y: {
                            ...darkScales().y,
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Expenditure Score', color: chartColors().tick }
                        }
                    }
                }
            });
        };

        const loadModelForecasts = (s, e) => {
            fetch(`api/phar_analytics.php?action=model_forecasts&start=${s}&end=${e}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error || 'API error');
                    window.modelForecastData = res.data || {};
                    generateModalSummary();
                    renderRandomForestForecastChart();
                    renderIsolationForestChart();
                    renderProphetForecastChart();
                })
                .catch(err => {
                    console.error('Model forecast error:', err);
                    showToast('Error loading model forecasts: ' + err.message, 'danger');
                });
        };

        const loadAll = () => {
            const s = startDate.value, e = endDate.value;
            if (s && e && new Date(s) <= new Date(e)) {
                loadDemand(s, e);
                loadTop(s, e);
                loadTrends(s, e);
                loadLowStock(s, e);
                loadModelForecasts(s, e);
                setTimeout(updateCoreInsights, 800);
                showToast('Analytics updated', 'success');
                return true;
            } else {
                showInitialEmptyState();
                showToast('Please choose a valid start and end date first.', 'danger');
                return false;
            }
        };

        let liveRefreshTimer = null;
        const startLiveRefresh = () => {
            if (liveRefreshTimer) {
                clearInterval(liveRefreshTimer);
            }
            liveRefreshTimer = setInterval(() => {
                const s = startDate.value, e = endDate.value;
                if (s && e && new Date(s) <= new Date(e)) {
                    loadAll();
                }
            }, 30000);
        };

        initializeDateRange().then(() => {
            setTimeout(loadAll, 150);
        });

        applyBtn.addEventListener('click', () => {
            updateDateRangeUi();
            if (loadAll()) startLiveRefresh();
        });

        startDate?.addEventListener('change', updateDateRangeUi);
        endDate?.addEventListener('change', updateDateRangeUi);

        // Update modal when opened
        const modalEl = document.getElementById('summaryModal');
        modalEl.addEventListener('show.bs.modal', () => {
            generateModalSummary();
        });

        // Re-render charts when dark mode is toggled (listen for class change on body)
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(m => {
                if (m.attributeName === 'class') {
                    const s = startDate.value, e = endDate.value;
                    if (s && e) {
                        loadDemand(s, e);
                        loadTop(s, e);
                        loadTrends(s, e);
                        loadLowStock(s, e);
                        renderRandomForestForecastChart();
                        renderIsolationForestChart();
                        renderProphetForecastChart();
                    }
                }
            });
        });
        observer.observe(document.body, { attributes: true });

        // Algorithm card filter
        const algoFilterBtn = document.getElementById('algoFilterBtn');
        const algoFilterLabel = document.getElementById('algoFilterLabel');
        const algorithmGrid = document.getElementById('algorithmGrid');
        const algoCards = Array.from(document.querySelectorAll('.algo-card'));
        const algoColumns = Array.from(document.querySelectorAll('[data-algo-column]'));
        const setAlgoFilter = (algo, label) => {
            if (algoFilterLabel) algoFilterLabel.textContent = label || 'All Algorithms';
            document.querySelectorAll('.algo-select').forEach(item => {
                item.classList.toggle('active', item.dataset.algo === (algo || 'all'));
            });
            if (!algo || algo === 'all') {
                algorithmGrid?.classList.remove('is-filtered');
                algoColumns.forEach(col => col.style.display = '');
                algoCards.forEach(c => c.classList.remove('is-selected'));
            } else {
                algorithmGrid?.classList.add('is-filtered');
                algoColumns.forEach(col => {
                    const card = col.querySelector('.algo-card');
                    col.style.display = (card?.dataset.algorithm === algo) ? '' : 'none';
                    card?.classList.toggle('is-selected', card.dataset.algorithm === algo);
                });
            }
            setTimeout(() => {
                Object.values(Chart.instances || {}).forEach(chart => chart.resize());
            }, 120);
        };
        document.querySelectorAll('.algo-select').forEach(a => {
            a.addEventListener('click', (ev) => {
                ev.preventDefault();
                const algo = a.dataset.algo;
                setAlgoFilter(algo, a.textContent.trim());
            });
        });

        window.generateModalSummary = generateModalSummary;
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
