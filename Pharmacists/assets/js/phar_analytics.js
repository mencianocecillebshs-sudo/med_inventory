/********************************************************************
 *  analytics.js  – FULLY UPDATED WITH DYNAMIC CONCLUSION
 *  • Low-stock forecast works (500 fixed)
 *  • Low-stock forecast follows the selected date range
 *  • Dynamic conclusion panel at the bottom
 *  • NEW: Low-stock card now reflects the System Settings thresholds
 *         (low_stock_threshold / critical_stock_threshold) instead of
 *         a hardcoded value, and the summary explains WHY each item
 *         was flagged so users can connect Settings -> Analytics.
 ********************************************************************/
document.addEventListener('DOMContentLoaded', () => {
    const startDate = document.getElementById('start-date');
    const endDate = document.getElementById('end-date');
    const applyBtn = document.getElementById('apply-filter');
    const demandLoading = document.getElementById('demand-loading');
    const conclusionEl = document.getElementById('analytics-conclusion');

    let demandChart, topChart, trendsChart, stockChart;
    let loadedCharts = { demand: false, top: false, trends: false, stock: false };
    const STORAGE_KEY = 'pharmacist_analytics_date_range';

    const getFixedRange = () => {
        const end = new Date();
        const start = new Date(end);
        start.setDate(end.getDate() - 13);
        return {
            start: start.toISOString().split('T')[0],
            end: end.toISOString().split('T')[0]
        };
    };

    const getSavedDateRange = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !parsed.start || !parsed.end) return null;
            if (new Date(parsed.start) > new Date(parsed.end)) return null;
            return parsed;
        } catch (err) {
            console.warn('Saved date range unavailable:', err);
            return null;
        }
    };

    const saveDateRange = () => {
        if (!startDate || !endDate) return;
        const range = {
            start: startDate.value,
            end: endDate.value
        };
        if (range.start && range.end) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(range));
        }
    };

    const applySavedRangeOrFixed = () => {
        const saved = getSavedDateRange();
        const fixed = getFixedRange();
        const range = saved || fixed;
        startDate.value = range.start;
        endDate.value = range.end;
        return range;
    };

    /* ------------------------------------------------------------------ *
     *  Initialise date pickers
     * ------------------------------------------------------------------ */
    const initializeDateRange = async () => {
        applySavedRangeOrFixed();
        return;
    };

    /* ------------------------------------------------------------------ *
     *  Toast helper
     * ------------------------------------------------------------------ */
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
                          <button type="button" class="btn-close btn-close-white me-2 m-auto"
                                  data-bs-dismiss="toast"></button>
                       </div>`;
        container.appendChild(t);
        new bootstrap.Toast(t).show();
        t.addEventListener('hidden.bs.toast', () => t.remove());
    };

    /* ------------------------------------------------------------------ *
     *  Math helpers
     * ------------------------------------------------------------------ */
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
            sx += i;
            sy += values[i];
            sxy += i * values[i];
            sxx += i * i;
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
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '16px Inter, sans-serif';
        ctx.fillStyle = '#64748b';
        ctx.textAlign = 'center';
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
    };

    /* ------------------------------------------------------------------ *
     *  Check if all charts loaded
     * ------------------------------------------------------------------ */
    const checkAllLoaded = () => {
        if (loadedCharts.demand && loadedCharts.top && loadedCharts.trends && loadedCharts.stock) {
            generateConclusion();
        }
    };

    /* ------------------------------------------------------------------ *
     *  Load Demand Forecast
     * ------------------------------------------------------------------ */
    const loadDemand = (s, e) => {
        loadedCharts.demand = false;
        demandLoading.style.display = 'inline-block';
        fetch(`api/phar_analytics.php?action=daily_trends&start=${s}&end=${e}`)
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(res => {
                if (!res.success) throw new Error(res.error || 'API error');
                const data = res.data || [];

                if (data.length === 0) {
                    if (demandChart) demandChart.destroy();
                    showEmptyState('demandChart', 'No demand data for the selected period');
                    window.demandData = null;
                    loadedCharts.demand = true;
                    checkAllLoaded();
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
                            {
                                label: 'Actual Demand', data: [...demand, ...new Array(7).fill(null)],
                                borderColor: '#1b5e3f', backgroundColor: 'rgba(27,94,63,.1)', fill: true, tension: .4
                            },
                            {
                                label: '7-day MA', data: maData, borderColor: '#10b981',
                                borderDash: [5, 5], tension: .4, pointRadius: 0
                            },
                            {
                                label: 'Forecast (7 days)', data: fcData, borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245,158,11,.1)', fill: true, tension: .4, pointStyle: 'triangle'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } },
                        scales: {
                            y: { beginAtZero: true, title: { display: true, text: 'Units' } },
                            x: { title: { display: true, text: 'Date' } }
                        }
                    }
                });

                // Store for conclusion
                window.demandData = { demand, supply, forecast: fc, dates: labels };
                loadedCharts.demand = true;
                checkAllLoaded();
            })
            .catch(err => {
                console.error('Demand error:', err);
                showToast('Error loading demand forecast: ' + err.message, 'danger');
                if (demandChart) demandChart.destroy();
                showEmptyState('demandChart', 'Error loading data');
                window.demandData = null;
                loadedCharts.demand = true;
                checkAllLoaded();
            })
            .finally(() => demandLoading.style.display = 'none');
    };

    /* ------------------------------------------------------------------ *
     *  Load Top 10 Medicines
     * ------------------------------------------------------------------ */
    const loadTop = (s, e) => {
        loadedCharts.top = false;
        fetch(`api/phar_analytics.php?action=top_medicines&limit=10&start=${s}&end=${e}`)
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(res => {
                if (!res.success) throw new Error(res.error);
                const d = res.data || [];

                if (d.length === 0) {
                    if (topChart) topChart.destroy();
                    showEmptyState('topMedicinesChart', 'No medicine data');
                    window.topMedicines = null;
                    loadedCharts.top = true;
                    checkAllLoaded();
                    return;
                }

                const labels = d.map(x => x.medicine_name);
                const vals = d.map(x => parseFloat(x.total_demand) || 0);

                if (topChart) topChart.destroy();
                const ctx = document.getElementById('topMedicinesChart').getContext('2d');
                topChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Demand',
                            data: vals,
                            backgroundColor: ['#1b5e3f', '#3b82f6', '#10b981', '#f59e0b', '#ef4444',
                                '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#06b6d4'],
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, title: { display: true, text: 'Units' } } }
                    }
                });

                window.topMedicines = d.slice(0, 3); // top 3 for summary
                loadedCharts.top = true;
                checkAllLoaded();
            })
            .catch(err => {
                console.error('Top medicines error:', err);
                showToast('Error loading top medicines: ' + err.message, 'danger');
                window.topMedicines = null;
                loadedCharts.top = true;
                checkAllLoaded();
            });
    };

    /* ------------------------------------------------------------------ *
     *  Load Supply vs Demand
     * ------------------------------------------------------------------ */
    const loadTrends = (s, e) => {
        loadedCharts.trends = false;
        fetch(`api/phar_analytics.php?action=daily_trends&start=${s}&end=${e}`)
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(res => {
                if (!res.success) throw new Error(res.error);
                const d = res.data || [];

                if (d.length === 0) {
                    if (trendsChart) trendsChart.destroy();
                    showEmptyState('trendsChart', 'No trend data');
                    window.supplyDemand = null;
                    loadedCharts.trends = true;
                    checkAllLoaded();
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
                            {
                                label: 'Demand', data: demand, borderColor: '#ef4444',
                                backgroundColor: 'rgba(239,68,68,.1)', fill: true, tension: .4
                            },
                            {
                                label: 'Supply', data: supply, borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'Units' } } }
                    }
                });

                const totalDemand = demand.reduce((a, b) => a + b, 0);
                const totalSupply = supply.reduce((a, b) => a + b, 0);
                window.supplyDemand = { totalDemand, totalSupply, net: totalSupply - totalDemand };
                loadedCharts.trends = true;
                checkAllLoaded();
            })
            .catch(err => {
                console.error('Trends error:', err);
                showToast('Error loading trends: ' + err.message, 'danger');
                window.supplyDemand = null;
                loadedCharts.trends = true;
                checkAllLoaded();
            });
    };

    /* ------------------------------------------------------------------ *
     *  Load Low-Stock Forecast
     *  NOTE: This now reads `low_threshold` / `critical_threshold` back
     *  from the API response. The API itself resolves those values from
     *  the system-wide Settings (set on the Settings page), so whatever
     *  the admin configures there is reflected here automatically — no
     *  query param needs to be sent from this page for that to work.
     * ------------------------------------------------------------------ */
    const loadLowStock = () => {
        loadedCharts.stock = false;
        const params = new URLSearchParams({
            action: 'low_stock_forecast',
            start: startDate.value,
            end: endDate.value
        });
        fetch(`api/phar_analytics.php?${params.toString()}`)
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(res => {
                if (!res.success) throw new Error(res.error);
                const d = (res.data || []).slice(0, 10);

                // Keep the resolved thresholds around for the conclusion panel
                window.lowStockThresholds = {
                    low: res.low_threshold ?? res.threshold ?? null,
                    critical: res.critical_threshold ?? null
                };

                if (d.length === 0) {
                    if (stockChart) stockChart.destroy();
                    showEmptyState('stockForecastChart', 'No medicines are running low in the selected period');
                    window.lowStock = { critical: [], warning: [] };
                    loadedCharts.stock = true;
                    checkAllLoaded();
                    return;
                }

                // Sort by depletion urgency so the shortest forecasts appear first.
                const sorted = [...d].sort((a, b) => {
                    if (a.days_until_empty == null && b.days_until_empty == null) {
                        return a.current_stock - b.current_stock;
                    }
                    if (a.days_until_empty == null) return 1;
                    if (b.days_until_empty == null) return -1;
                    return a.days_until_empty - b.days_until_empty;
                });
                const labels = sorted.map(x => x.name);
                const days = sorted.map(x => parseFloat(x.days_until_empty) || 0);
                const colors = sorted.map(x =>
                    x.status === 'critical' ? '#ef4444' :
                        x.status === 'warning' ? '#f59e0b' : '#10b981'
                );

                if (stockChart) stockChart.destroy();
                const ctx = document.getElementById('stockForecastChart').getContext('2d');

                stockChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Days until empty',
                            data: days,
                            backgroundColor: colors,
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 18 } },
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: c => {
                                        const item = sorted[c.dataIndex];
                                        const daysText = item.days_until_empty == null
                                            ? 'No depletion estimate available'
                                            : `Estimated depletion: ${parseFloat(item.days_until_empty).toFixed(1)} days`;
                                        const statusText = item.status === 'critical'
                                            ? 'Critical action needed'
                                            : item.status === 'warning' ? 'Low stock warning' : 'Stock level normal';
                                        return [
                                            daysText,
                                            `Current stock: ${item.current_stock} units`,
                                            `Average usage: ${parseFloat(item.avg_daily_demand || 0).toFixed(2)} units/day`,
                                            `Status: ${statusText}`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: { display: true, text: 'Days until depletion' }
                            }
                        }
                    }
                });

                window.lowStock = {
                    critical: d.filter(x => x.status === 'critical').map(x => x.name),
                    warning: d.filter(x => x.status === 'warning').map(x => x.name),
                    items: d
                };
                loadedCharts.stock = true;
                checkAllLoaded();
            })
            .catch(err => {
                console.error('Low-stock error:', err);
                showToast('Error loading low-stock forecast: ' + err.message, 'danger');
                if (stockChart) stockChart.destroy();
                showEmptyState('stockForecastChart', 'Error loading data');
                window.lowStock = { critical: [], warning: [] };
                loadedCharts.stock = true;
                checkAllLoaded();
            });
    };

    /* ------------------------------------------------------------------ *
     *  Translate a backend "reason" code into a short human sentence
     * ------------------------------------------------------------------ */
    const describeReason = (reason) => {
        switch (reason) {
            case 'stock_at_or_below_critical':
                return 'stock is at or below your Critical Stock Threshold';
            case 'stock_at_or_below_low':
                return 'stock is at or below your Low Stock Threshold';
            case 'depletes_under_7_days':
                return 'projected to run out in under 7 days at current usage';
            case 'depletes_under_14_days':
                return 'projected to run out in under 14 days at current usage';
            default:
                return reason;
        }
    };

    /* ------------------------------------------------------------------ *
     *  Generate Dynamic Conclusion
     * ------------------------------------------------------------------ */
    const generateConclusion = () => {
        if (!conclusionEl) return; // Only for standalone analytics.js with conclusion element

        const { demandData, supplyDemand, topMedicines, lowStock, lowStockThresholds } = window;
        const start = startDate.value;
        const end = endDate.value;
        const period = start && end ? `${formatDate(start)} to ${formatDate(end)}` : 'selected period';

        let summary = `<h5><i class="bi bi-lightbulb-fill text-warning me-2"></i>Analytics Summary (${period})</h5>`;
        summary += `<p class="text-muted small mb-2">This summary explains what the charts above are showing and why, in plain language.</p>`;
        summary += `<ul class="list-unstyled">`;

        // 1. Supply vs Demand
        if (supplyDemand) {
            const { totalDemand, totalSupply, net } = supplyDemand;
            const diff = net >= 0 ? `surplus of <strong>${net}</strong> units` : `shortfall of <strong>${Math.abs(net)}</strong> units`;
            summary += `<li><i class="bi bi-arrow-left-right text-primary me-2"></i>Supply vs Demand: <strong>${totalSupply}</strong> supplied vs <strong>${totalDemand}</strong> dispensed in this period &rarr; <span class="${net >= 0 ? 'text-success' : 'text-danger'}">${diff}</span>. ${net >= 0 ? 'Restocking is currently keeping pace with usage.' : 'Usage is currently outpacing restocking, which can lead to more low-stock items over time.'}</li>`;
        }

        // 2. Demand Trend
        if (demandData && demandData.forecast.length > 0) {
            const lastActual = demandData.demand[demandData.demand.length - 1];
            const forecastAvg = demandData.forecast.reduce((a, b) => a + b, 0) / 7;
            const trend = forecastAvg > lastActual * 1.1 ? 'increasing' :
                forecastAvg < lastActual * 0.9 ? 'decreasing' : 'stable';
            const icon = trend === 'increasing' ? 'bi-graph-up-arrow text-danger' :
                trend === 'decreasing' ? 'bi-graph-down-arrow text-success' : 'bi-dash-lg text-secondary';
            const trendNote = trend === 'increasing'
                ? 'Expect more items to approach their stock thresholds sooner than usual.'
                : trend === 'decreasing'
                    ? 'Stock should last longer than usual at this pace.'
                    : 'No unusual change in how quickly stock is being used.';
            summary += `<li><i class="${icon} me-2"></i>Demand Forecast: Expected to be <strong>${trend}</strong> (avg ~${Math.round(forecastAvg)} units/day next 7 days). ${trendNote}</li>`;
        }

        // 3. Top Medicines
        if (topMedicines && topMedicines.length > 0) {
            const names = topMedicines.map(m => m.medicine_name).join(', ');
            summary += `<li><i class="bi bi-star-fill text-warning me-2"></i>Top Medicines: <strong>${names}</strong> are in highest demand this period &mdash; worth prioritizing when restocking.</li>`;
        }

        // 4. Low Stock Alert — now explains the thresholds being used and why each item appears
        if (lowStock) {
            const crit = lowStock.critical;
            const warn = lowStock.warning;

            if (lowStockThresholds && (lowStockThresholds.low !== null || lowStockThresholds.critical !== null)) {
                summary += `<li class="mb-2"><i class="bi bi-sliders text-secondary me-2"></i>Using your <strong>System Settings</strong> thresholds: Low Stock Threshold = <strong>${lowStockThresholds.low ?? 'default'}</strong> units, Critical Stock Threshold = <strong>${lowStockThresholds.critical ?? 'default'}</strong> units. Changing these on the Settings page updates this forecast for everyone.</li>`;
            }

            if (crit.length > 0) {
                summary += `<li><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i><strong>Critical Stock Alert:</strong> ${crit.join(', ')} ${crit.length === 1 ? 'is' : 'are'} either at or below the Critical Stock Threshold, or projected to run out in under 7 days. Order immediately.</li>`;
            }
            if (warn.length > 0) {
                summary += `<li><i class="bi bi-exclamation-circle-fill text-warning me-2"></i><strong>Low Stock Warning:</strong> ${warn.join(', ')} ${warn.length === 1 ? 'is' : 'are'} either at or below the Low Stock Threshold, or projected to run out within 7&ndash;14 days.</li>`;
            }
            if (crit.length === 0 && warn.length === 0) {
                summary += `<li><i class="bi bi-check-circle-fill text-success me-2"></i>All medicines are above both stock thresholds and not projected to run out within 14 days (based on the selected forecast period).</li>`;
            }
        }

        summary += `</ul>`;
        conclusionEl.innerHTML = summary;
    };

    const formatDate = (dateStr) => {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    /* ------------------------------------------------------------------ *
     *  Load All + Conclusion
     * ------------------------------------------------------------------ */
    const loadAll = () => {
        const resolved = {
            start: startDate.value,
            end: endDate.value
        };

        if (!resolved.start || !resolved.end || new Date(resolved.start) > new Date(resolved.end)) {
            const fixed = applySavedRangeOrFixed();
            saveDateRange();
            const s = fixed.start;
            const e = fixed.end;
            loadedCharts = { demand: false, top: false, trends: false, stock: false };
            loadDemand(s, e);
            loadTop(s, e);
            loadTrends(s, e);
            loadLowStock();
            showToast('Analytics updated');
            return;
        }

        saveDateRange();
        const s = resolved.start;
        const e = resolved.end;
        loadedCharts = { demand: false, top: false, trends: false, stock: false };

        loadDemand(s, e);
        loadTop(s, e);
        loadTrends(s, e);
        loadLowStock();
        showToast('Analytics updated');
    };

    /* ------------------------------------------------------------------ *
     *  Initial Load
     * ------------------------------------------------------------------ */
    initializeDateRange().then(() => {
        setTimeout(loadAll, 150);
    });

    applyBtn.addEventListener('click', loadAll);

    // Expose for external use
    window.generateConclusion = generateConclusion;
});