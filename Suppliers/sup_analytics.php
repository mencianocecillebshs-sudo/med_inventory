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
    <title>Forecasting & Analytics</title>
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
        .analytics-date-grid{display:grid;grid-template-columns:minmax(320px,1fr) auto auto;gap:.85rem;align-items:end;}
        .date-range-panel{display:grid;grid-template-columns:1fr auto 1fr;gap:.75rem;align-items:end;padding:.85rem;border:1px solid #d1fae5;border-radius:14px;background:linear-gradient(135deg,#f8fafc,#ecfdf5);box-shadow:inset 0 1px 0 rgba(255,255,255,.85);}
        .date-field{min-width:0;}
        .date-field .form-label{display:flex;align-items:center;gap:.4rem;margin-bottom:.4rem;color:#335045;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;}
        .date-input-shell{position:relative;}
        .date-input-shell i{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#1b5e3f;pointer-events:none;z-index:1;}
        .date-input-shell .form-control{min-height:46px;padding-left:2.65rem;background:#fff;color:#1e293b;border-color:#b7e4ce;box-shadow:0 6px 18px rgba(15,63,40,.07);}
        .date-range-separator{display:grid;place-items:center;width:38px;height:38px;margin-bottom:.25rem;border-radius:999px;background:#1b5e3f;color:#fff;box-shadow:0 6px 14px rgba(27,94,63,.22);}
        .date-range-status{grid-column:1 / -1;display:flex;align-items:center;gap:.45rem;min-height:1.2rem;color:#64748b;font-size:.8rem;font-weight:700;}
        .date-range-status i{color:#1b5e3f;}
        .algo-filter-panel{display:flex;align-items:center;justify-content:space-between;gap:1rem;}
        .algo-filter-copy p{margin:0;color:#64748b;font-size:.875rem;}
        .algo-dropdown{min-width:min(100%,360px);}
        .algo-dropdown .dropdown-toggle{display:flex;align-items:center;justify-content:space-between;border:2px solid #d1fae5;background:#f8fafc;color:#0f3f28;border-radius:12px;padding:.8rem 1rem;font-weight:700;}
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
        .form-control,.form-select{border-radius:10px;border:2px solid #e2e8f0;padding:.65rem 1rem;}
        .form-control:focus,.form-select:focus{border-color:#1b5e3f;box-shadow:0 0 0 .2rem rgba(27,94,63,.15);}
        .btn-primary{background:linear-gradient(135deg,#1b5e3f,#0f3f28);border:none;border-radius:10px;padding:.65rem 1.5rem;font-weight:600;}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(27,94,63,.3);}
        .btn-warning{background:linear-gradient(135deg,#f59e0b,#d97706);border:none;color:white;}
        .btn-warning:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(245,158,11,.3);}
        canvas{max-height:400px;}
        .chart-container{position:relative;height:400px;}
        .loading-overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;border-radius:15px;z-index:10;}

        .core-insights{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem;}
        .core-insight{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1rem;box-shadow:0 4px 16px rgba(0,0,0,.05);}
        .core-insight span{display:block;color:#64748b;font-weight:700;font-size:.85rem;margin-bottom:.35rem;}
        .core-insight strong{font-size:1.35rem;color:#0f3f28;}
        .core-insight small.core-sub{display:block;color:#94a3b8;font-weight:500;font-size:.75rem;margin-top:.25rem;}
        @media(max-width:992px){.core-insights{grid-template-columns:repeat(2,minmax(0,1fr));}}
        @media(max-width:768px){.analytics-date-grid{grid-template-columns:1fr;}.date-range-panel{grid-template-columns:1fr;}.date-range-separator{width:100%;height:30px;margin:0;}.date-range-separator i{transform:rotate(90deg);}.algo-filter-panel{align-items:stretch;flex-direction:column;}.algo-dropdown{min-width:100%;}.algorithm-grid.is-filtered .chart-container{height:380px;}}
        @media(max-width:576px){.core-insights{grid-template-columns:1fr;}}

        /* Modal Summary Styling */
        .insight-item {padding: 1rem; border-radius: 12px; margin-bottom: 1rem; border: 1px solid #fde68a; background: #fff9c4; transition: all .2s;}
        .insight-item:hover {transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.1);}
        .insight-item.critical {background: #fee2e2; border-color: #fca5a5; color: #991b1b;}
        .insight-item.warning {background: #fef3c7; border-color: #fbbf24;}
        .insight-item.success {background: #ecfdf5; border-color: #6ee7b7;}
        .insight-item.info {background: #eff6ff; border-color: #93c5fd; color: #1e3a8a;}
        .insight-item .icon {font-size: 1.5rem; margin-right: .75rem;}
        .forecast-guide {margin-top: 1rem;padding: 1rem;background: #f8fafc;border: 1px solid #e2e8f0;border-radius: 10px;}
        .forecast-guide p {margin-bottom: .5rem;color: #475569;}
        .forecast-details {display: grid;grid-template-columns: repeat(auto-fit,minmax(220px,1fr));gap: .75rem;margin-top: .75rem;}
        .forecast-detail {padding: .75rem;background: #fff;border-left: 4px solid #f59e0b;border-radius: 6px;color: #475569;font-size: .85rem;}
        .forecast-detail.critical {border-left-color: #ef4444;}
        .forecast-detail strong {color: #1e293b;}
        body.dark-mode .forecast-guide {background: #1e293b;border-color: #334155;}
        body.dark-mode .forecast-guide p, body.dark-mode .forecast-detail {color: #cbd5e1;}
        body.dark-mode .forecast-detail {background: #111827;}
        body.dark-mode .forecast-detail strong {color: #e2e8f0;}

        body.dark-mode { background:#0f1419 !important; color:#e2e8f0 !important; }
        body.dark-mode .main-content { background:transparent !important; }
        body.dark-mode .card, body.dark-mode .filter-card, body.dark-mode .core-insight { background:#111827 !important; color:#e2e8f0 !important; box-shadow:0 8px 24px rgba(0,0,0,.4) !important; }
        body.dark-mode .core-insight span { color:#94a3b8 !important; }
        body.dark-mode .core-insight strong { color:#6ee7b7 !important; }
        body.dark-mode .form-control, body.dark-mode .form-select { background:#1e293b !important; color:#e2e8f0 !important; border-color:#334155 !important; }
        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus { border-color:#2ecc71 !important; box-shadow:0 0 0 .2rem rgba(46,204,113,.2) !important; }
        body.dark-mode .insight-item { background:#1a2a1a !important; border-color:#2ecc71 !important; color:#d1fae5 !important; }
        body.dark-mode .insight-item.critical { background:#2d1515 !important; border-color:#ef4444 !important; color:#fca5a5 !important; }
        body.dark-mode .insight-item.warning { background:#2a1f0a !important; border-color:#f59e0b !important; color:#fde68a !important; }
        body.dark-mode .insight-item.success { background:#0d2b1a !important; border-color:#10b981 !important; color:#6ee7b7 !important; }
        body.dark-mode .insight-item.info { background:#0c1d33 !important; border-color:#3b82f6 !important; color:#93c5fd !important; }
        body.dark-mode .modal-content { background:#111827 !important; color:#e2e8f0 !important; }
        body.dark-mode .modal-body { background:#111827 !important; color:#e2e8f0 !important; }
        body.dark-mode .text-muted { color:#94a3b8 !important; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        
        <div class="page-header">
            <h2><i class="bi bi-graph-up-arrow me-2"></i>Forecasting & Analytics</h2>
            <p>Demand trends, forecasts and low-stock alerts</p>
        </div>

        <!-- Date filter + Summary Button -->
        <div class="filter-card">
            <h6><i class="bi bi-funnel"></i> Date range</h6>
            <div class="analytics-date-grid">
                <div class="date-range-panel" aria-label="Analytics date range">
                    <div class="date-field">
                        <label class="form-label" for="start-date"><i class="bi bi-calendar-event"></i> Start</label>
                        <div class="date-input-shell"><i class="bi bi-calendar3"></i><input type="date" class="form-control" id="start-date" aria-describedby="date-range-status"></div>
                    </div>
                    <div class="date-range-separator" aria-hidden="true"><i class="bi bi-arrow-right"></i></div>
                    <div class="date-field">
                        <label class="form-label" for="end-date"><i class="bi bi-calendar-check"></i> End</label>
                        <div class="date-input-shell"><i class="bi bi-calendar3"></i><input type="date" class="form-control" id="end-date" aria-describedby="date-range-status"></div>
                    </div>
                    <div class="date-range-status" id="date-range-status"><i class="bi bi-info-circle"></i><span id="date-range-label">Select a start and end date.</span></div>
                </div>
                <div><button class="btn btn-primary w-100" id="apply-filter"><i class="bi bi-check-circle"></i> Apply</button></div>
                <div><button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#summaryModal"><i class="bi bi-lightbulb-fill"></i> Summary</button></div>
            </div>
        </div>

        <div class="filter-card mb-2" aria-label="Algorithm filter">
            <div class="algo-filter-panel">
                <div class="algo-filter-copy"><h6><i class="bi bi-funnel"></i> Algorithm</h6><p>Choose one view or show every analytics model.</p></div>
                <div class="dropdown algo-dropdown">
                    <button class="btn dropdown-toggle w-100" id="algoFilterBtn" data-bs-toggle="dropdown" aria-expanded="false"><span id="algoFilterLabel">All Algorithms</span></button>
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
            <div class="core-insight"><span>Top Medicine</span><strong id="core-top">Loading...</strong></div>
            <div class="core-insight">
                <span>Low-Stock Risk</span>
                <strong id="core-risk">Loading...</strong>
                <small class="core-sub" id="core-risk-sub">&nbsp;</small>
            </div>
        </div>

        <div class="row algorithm-grid" id="algorithmGrid">
            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="demand">
                    <h5><i class="bi bi-graph-up"></i> Demand Forecast
                        <span id="demand-loading" class="spinner-border spinner-border-sm ms-2" style="display:none;"></span>
                    </h5>
                    <div class="chart-container"><canvas id="demandChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Actual Demand shows recorded supplier sales. The moving average smooths short-term variation, and Forecast shows the estimated next 7 days.</p></div>
                </div>
            </div>

            <div class="col-lg-6 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="top-medicines">
                    <h5><i class="bi bi-bar-chart"></i> Top 10 Medicines</h5>
                    <div class="chart-container"><canvas id="topMedicinesChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Longer bars represent medicines with more units sold during the selected period.</p></div>
                </div>
            </div>

            <div class="col-lg-6 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="supply-vs-demand">
                    <h5><i class="bi bi-arrow-left-right"></i> Supply vs Demand</h5>
                    <div class="chart-container"><canvas id="trendsChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Compare Demand with Supply by date. Supply above demand indicates stock-in is keeping pace; demand above supply indicates pressure on stock.</p></div>
                </div>
            </div>

            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="random-forest">
                    <h5><i class="bi bi-cpu"></i> Forecasting Models for Retail Demand Planning</h5>
                    <p class="mb-3 small text-muted">
                        Random Forest, SVM/XGBoost, and Facebook Prophet are evaluated alongside the linear baseline to capture nonlinear purchase behavior, seasonal demand, and replenishment patterns in supplier inventory planning.
                    </p>
                    <div class="chart-container"><canvas id="randomForestForecastChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Demand Pattern, Stock Trend, Seasonality, and Replenishment are live-data-derived scores from 20 to 100. Higher scores indicate stronger activity or replenishment pressure.</p></div>
                </div>
            </div>

            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="isolation-forest">
                    <h5><i class="bi bi-shield-exclamation"></i> Detecting Inventory Anomalies Through Isolation Forest in Retail Stock Audits</h5>
                    <p class="mb-3 small text-muted">
                        Isolation Forest highlights unusual movement and suspicious stock activity so supplier audits can focus on high-risk products.
                    </p>
                    <div class="chart-container"><canvas id="isolationForestChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Higher Anomaly Score values indicate unusual supplier movement. Audit Risk Trend follows the stock-related signal used to prioritize review.</p></div>
                </div>
            </div>

            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="prophet">
                    <h5><i class="bi bi-calendar3-week"></i> Predicting Drug Expenditures Using Facebook Prophet in Pharmaceutical Installations</h5>
                    <p class="mb-3 small text-muted">
                        Facebook Prophet models recurring seasonal patterns and long-term trends so supplier forecasting can anticipate demand shifts over time.
                    </p>
                    <div class="chart-container"><canvas id="prophetForecastChart"></canvas></div>
                    <div class="forecast-guide"><p class="small mb-0"><strong>How to read this:</strong> Actual Expenditure reflects recorded supplier demand scaled for comparison. Prophet Forecast shows the calculated forward trend from recent demand behavior.</p></div>
                </div>
            </div>

            <div class="col-12 mb-4" data-algo-column>
                <div class="card algo-card" data-algorithm="low-stock">
                    <h5><i class="bi bi-exclamation-triangle"></i> Low-Stock Forecast
                        <span class="badge bg-light text-dark border ms-2" id="low-stock-threshold-badge" style="font-weight:500;">&nbsp;</span>
                    </h5>
                    <div class="chart-container"><canvas id="stockForecastChart"></canvas></div>
                    <div class="forecast-guide" aria-live="polite">
                        <p><strong>How to read this:</strong> Each bar estimates how many days the current stock may last at the recent average usage rate.</p>
                        <p class="small mb-0"><i class="bi bi-info-circle me-1"></i>Red means critical: at/below the critical stock limit or projected to empty in under 7 days. Amber means warning: at/below the low-stock limit or projected to empty within 14 days. Items without demand history are flagged by stock level only.</p>
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
            <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <h5 class="modal-title" id="summaryModalLabel"><i class="bi bi-lightbulb-fill me-2"></i>Analytics Summary</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" style="background: #fffbeb;" id="modal-summary-content">
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

        let demandChart, topChart, trendsChart, stockChart;

        const updateCoreInsights = () => {
            const supplyDemand = window.supplyDemand || { totalDemand: 0, totalSupply: 0, net: 0 };
            const topMedicines = Array.isArray(window.topMedicines) ? window.topMedicines : [];
            const lowStock = window.lowStock || { critical: [], warning: [] };

            if (coreDemand) coreDemand.textContent = Number(supplyDemand.totalDemand || 0).toLocaleString();
            if (coreSupply) coreSupply.textContent = Number(supplyDemand.totalSupply || 0).toLocaleString();
            if (coreTop) coreTop.textContent = topMedicines.length ? topMedicines[0].medicine_name : 'N/A';

            const riskyCount = (lowStock.critical || []).length + (lowStock.warning || []).length;
            if (coreRisk) {
                coreRisk.textContent = riskyCount > 0 ? `${riskyCount} item${riskyCount > 1 ? 's' : ''}` : 'Low';
            }
            if (coreRiskSub) {
                if (lowStock.critical && lowStock.critical.length) {
                    coreRiskSub.textContent = `${lowStock.critical.length} critical`;
                } else if (lowStock.warning && lowStock.warning.length) {
                    coreRiskSub.textContent = `${lowStock.warning.length} warning`;
                } else {
                    coreRiskSub.textContent = 'No immediate risk';
                }
            }
        };

        const applyAlgorithmFilter = (selectedAlgo) => {
            const button = document.getElementById('algoFilterBtn');
            const label = document.getElementById('algoFilterLabel');
            const grid = document.getElementById('algorithmGrid');
            if (button || label) {
                const labelMap = {
                    all: 'All Algorithms',
                    demand: 'Demand Forecast',
                    'top-medicines': 'Top 10 Medicines',
                    'supply-vs-demand': 'Supply vs Demand',
                    'random-forest': 'Random Forest',
                    'isolation-forest': 'Isolation Forest',
                    'prophet': 'Facebook Prophet',
                    'low-stock': 'Low-Stock Forecast'
                };
                const text = labelMap[selectedAlgo] || 'All Algorithms';
                if (label) label.textContent = text;
                else if (button) button.textContent = text;
            }

            if (grid) grid.classList.toggle('is-filtered', selectedAlgo !== 'all');

            document.querySelectorAll('.algo-select').forEach((item) => {
                item.classList.toggle('active', item.dataset.algo === selectedAlgo);
            });

            document.querySelectorAll('.algo-card').forEach((card) => {
                const algo = card.dataset.algorithm;
                const shouldShow = selectedAlgo === 'all' || algo === selectedAlgo;
                card.style.display = shouldShow ? '' : 'none';
            });
        };

        document.querySelectorAll('.algo-select').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                applyAlgorithmFilter(link.dataset.algo || 'all');
            });
        });

        const initializeDateRange = async () => {
            const end = new Date();
            end.setHours(0, 0, 0, 0);
            const start = new Date(end);
            start.setDate(start.getDate() - 13);
            const formatDate = date => date.toISOString().split('T')[0];
            startDate.value = formatDate(start);
            endDate.value = formatDate(end);
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
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'center';
            ctx.fillText(message, canvas.width / 2, canvas.height / 2);
        };

        const loadDemand = (s, e) => {
            demandLoading.style.display = 'inline-block';
            fetch(`api/sup_analytics.php?action=daily_trends&start=${s}&end=${e}`)
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
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Units' } }, x: { title: { display: true, text: 'Date' } } } }
                    });
                    window.demandData = { demand, supply, forecast: fc, dates: labels };
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
            fetch(`api/sup_analytics.php?action=top_medicines&limit=10&start=${s}&end=${e}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error);
                    const d = res.data || [];
                    if (d.length === 0) {
                        if (topChart) topChart.destroy();
                        showEmptyState('topMedicinesChart', 'No transactions found in this date range');
                        return;
                    }
                    const labels = d.map(x => x.medicine_name);
                    const vals   = d.map(x => parseFloat(x.total_demand) || 0);
                    if (topChart) topChart.destroy();
                    const ctx = document.getElementById('topMedicinesChart').getContext('2d');
                    topChart = new Chart(ctx, {
                        type: 'bar',
                        data: { labels, datasets: [{ label: 'Demand', data: vals, backgroundColor: ['#1b5e3f','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316','#06b6d4'], borderRadius: 8 }] },
                        options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, title: { display: true, text: 'Units' } } } }
                    });
                    window.topMedicines = d.slice(0, 3);
                    updateCoreInsights();
                })
                .catch(err => {
                    console.error('Top medicines error:', err);
                    showToast('Error loading top medicines: ' + err.message, 'danger');
                });
        };

        const loadTrends = (s, e) => {
            fetch(`api/sup_analytics.php?action=daily_trends&start=${s}&end=${e}`)
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
                    trendsChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [
                                { label: 'Demand', data: demand, borderColor: '#ef4444', backgroundColor: 'rgba(239,68,68,.1)', fill: true, tension: .4 },
                                { label: 'Supply', data: supply, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4 }
                            ]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true, title: { display: true, text: 'Units' } } } }
                    });
                    const totalDemand = demand.reduce((a,b) => a+b, 0);
                    const totalSupply = supply.reduce((a,b) => a+b, 0);
                    window.supplyDemand = { totalDemand, totalSupply, net: totalSupply - totalDemand };
                    updateCoreInsights();
                })
                .catch(err => {
                    console.error('Trends error:', err);
                    showToast('Error loading trends: ' + err.message, 'danger');
                });
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
            if (!dates.length) {
                showEmptyState('randomForestForecastChart', 'No live forecast data for this period');
                return;
            }
            const labels = dates;
            const demandPattern = modelData.demand_pattern || [];
            const stockTrend = modelData.stock_trend || [];
            const seasonality = modelData.seasonality || [];
            const replenishment = modelData.replenishment || [];

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
                    plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } },
                    scales: {
                        x: { title: { display: true, text: 'Date' } },
                        y: { beginAtZero: true, max: 100, title: { display: true, text: 'Score (%)' } }
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
            if (!dates.length) {
                showEmptyState('isolationForestChart', 'No live anomaly data for this period');
                return;
            }
            const labels = dates;
            const anomalyScores = modelData.anomaly_scores || [];
            const anomalyTrend = modelData.stock_trend || [];
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
                    plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } },
                    scales: {
                        x: { title: { display: true, text: 'Date' } },
                        y: { beginAtZero: true, max: 100, title: { display: true, text: 'Score (%)' } }
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
            if (!dates.length) {
                showEmptyState('prophetForecastChart', 'No live forecast data for this period');
                return;
            }
            const labels = dates;
            const actual = (modelData.demand || []).map(value => Math.min(100, Math.max(20, value)));
            const forecast = modelData.prophet_forecast || [];

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
                    plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } },
                    scales: {
                        x: { title: { display: true, text: 'Date' } },
                        y: { beginAtZero: true, max: 100, title: { display: true, text: 'Expenditure Score' } }
                    }
                }
            });
        };

        const loadModelForecasts = (s, e) => {
            fetch(`api/sup_analytics.php?action=model_forecasts&start=${s}&end=${e}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error || 'API error');
                    window.modelForecastData = res.data || {};
                    renderRandomForestForecastChart();
                    renderIsolationForestChart();
                    renderProphetForecastChart();
                })
                .catch(err => {
                    console.error('Model forecast error:', err);
                    showToast('Error loading model forecasts: ' + err.message, 'danger');
                });
        };

        const loadLowStock = (s, e) => {
            fetch(`api/sup_analytics.php?action=low_stock_forecast&start=${encodeURIComponent(s)}&end=${encodeURIComponent(e)}`)
                .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
                .then(res => {
                    if (!res.success) throw new Error(res.error);
                    const d = (res.data || []).slice(0, 10);
                    window.lowStockThresholds = {
                        low: res.low_threshold ?? null,
                        critical: res.critical_threshold ?? null
                    };
                    if (lowStockThresholdBadge) {
                        const lo = window.lowStockThresholds.low;
                        const cr = window.lowStockThresholds.critical;
                        lowStockThresholdBadge.textContent = (lo !== null || cr !== null)
                            ? `Thresholds: Low ≤ ${lo ?? '—'} · Critical ≤ ${cr ?? '—'}`
                            : (d.length ? `${d.length} alerts` : 'Healthy');
                    }
                    if (d.length === 0) {
                        if (stockChart) stockChart.destroy();
                        showEmptyState('stockForecastChart', 'No medicines are running low in the selected period');
                        if (lowStockForecastDetails) lowStockForecastDetails.innerHTML = '<div class="small text-success"><i class="bi bi-check-circle me-1"></i>No items meet the low-stock or 14-day depletion criteria.</div>';
                        window.lowStock = { critical: [], warning: [], items: [] };
                        generateModalSummary();
                        return;
                    }
                    const labels = d.map(x => x.name);
                    const days   = d.map(x => x.has_demand_history ? Number(x.days_until_empty) : 0);
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
                            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
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
                    updateCoreInsights();
                    generateModalSummary();
                })
                .catch(err => {
                    console.error('Low-stock error:', err);
                    showToast('Error loading low-stock forecast: ' + err.message, 'danger');
                    if (stockChart) stockChart.destroy();
                    showEmptyState('stockForecastChart', 'Error loading data');
                });
        };

        // MODAL SUMMARY GENERATOR
        const generateModalSummary = () => {
            const { demandData, supplyDemand, topMedicines, lowStock, lowStockThresholds, modelForecastData } = window;
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
                        <small><strong>${totalSupply}</strong> supplied &bull; <strong>${totalDemand}</strong> sold</small><br>
                        <span class="badge bg-${isSurplus ? 'success' : 'danger'} mt-1">
                            <i class="${icon}"></i> ${isSurplus ? '+' : ''}${net} ${isSurplus ? 'surplus' : 'shortfall'}
                        </span>
                        <div class="small mt-1">${isSurplus ? 'Restocking is keeping pace with sales in this period.' : 'Demand is outpacing replenishment — this tends to increase low-stock pressure if it continues.'}</div>
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
                    ? 'Facebook Prophet expects demand to rise over the next 5 days, driven by recurring usage patterns.'
                    : prophetTrend === 'decreasing'
                        ? 'Facebook Prophet expects demand to ease over the next 9 days, reflecting recent sales moderation.'
                        : prophetTrend === 'stable'
                            ? 'Facebook Prophet expects demand to remain steady, with seasonality roughly balancing current consumption.'
                            : null;
                const prophetWhy = prophetTrend === 'increasing'
                    ? 'This means stock needs are likely to increase soon, especially for fast-moving medicines.'
                    : prophetTrend === 'decreasing'
                        ? 'This means replenishment can be paced more carefully since usage pressure is easing.'
                        : prophetTrend === 'stable'
                            ? 'This means current stock planning can stay steady, but watch fast-moving items closely.'
                            : null;
                const prophetAction = prophetTrend === 'increasing'
                    ? (anomalyScore >= 65
                        ? 'Recommendation: review replenishment for top-selling medicines and prioritize checks on high-risk items.'
                        : 'Recommendation: review replenishment for top-selling medicines and schedule restock within 3–5 days.')
                    : prophetTrend === 'decreasing'
                        ? 'Recommendation: hold large replenishment orders and review stock only before the next demand shift.'
                        : prophetTrend === 'stable'
                            ? 'Recommendation: continue monitoring demand and keep coverage aligned with current sales.'
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
                const forecastAvg = demandData.forecast.reduce((a, b) => a + b, 0) / 7;
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
                        <small><strong>${names}</strong> are your most sold medicines</small>
                    </div>
                </div>`;
            }

            if (lowStockThresholds && (lowStockThresholds.low !== null || lowStockThresholds.critical !== null)) {
                html += `
                <div class="insight-item info d-flex align-items-start">
                    <i class="bi bi-sliders icon"></i>
                    <div>
                        <strong>Thresholds in use (from System Settings)</strong><br>
                        <small>Low Stock Threshold: <strong>${lowStockThresholds.low ?? 'default'}</strong> units &bull; Critical Stock Threshold: <strong>${lowStockThresholds.critical ?? 'default'}</strong> units</small><br>
                        <small class="text-muted">These apply system-wide. A forecast alert can also be triggered by expected depletion: under 7 days is critical, and under 14 days is a warning.</small>
                    </div>
                </div>`;
            }

            if (lowStock) {
                const crit = lowStock.critical || [];
                const warn = lowStock.warning || [];
                if (crit.length > 0) {
                    html += `
                    <div class="insight-item critical d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle-fill icon"></i>
                        <div>
                            <strong>CRITICAL STOCK ALERT</strong><br>
                            <small><strong>${crit.join(', ')}</strong> ${crit.length === 1 ? 'is' : 'are'} at/below the critical threshold or projected to run out in under 7 days. Replenish now.</small>
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
                            <small>All medicines are above both thresholds and not projected to run out within 14 days for the selected period</small>
                        </div>
                    </div>`;
                }
            }

            modalContent.innerHTML = html;
        };

        const loadAll = () => {
            const s = startDate.value, e = endDate.value;
            if (s && e && new Date(s) <= new Date(e)) {
                loadDemand(s, e);
                loadTop(s, e);
                loadTrends(s, e);
                loadLowStock(s, e);
                loadModelForecasts(s, e);
                showToast('Analytics updated', 'success');
            } else {
                showToast('Invalid date range', 'danger');
            }
        };

        applyAlgorithmFilter('all');

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
            startLiveRefresh();
        });
        applyBtn.addEventListener('click', () => {
            loadAll();
            startLiveRefresh();
        });

        // Update modal when opened
        const modalEl = document.getElementById('summaryModal');
        modalEl.addEventListener('show.bs.modal', () => {
            generateModalSummary();
        });

        window.generateModalSummary = generateModalSummary;
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
