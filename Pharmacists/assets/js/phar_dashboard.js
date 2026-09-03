document.addEventListener('DOMContentLoaded', () => {
    const refreshButton = document.getElementById('refresh-btn');
    const inventoryLoading = document.getElementById('inventory-loading');
    const alertsLoading = document.getElementById('alerts-loading');
    const suppliersLoading = document.getElementById('suppliers-loading');
    const topMedicinesLoading = document.getElementById('top-medicines-loading');
    const toggleButton = document.getElementById('toggle-sidebar-mobile');
    const chartRefs = {};

    document.getElementById('open-analytics-page')?.addEventListener('click', () => {
        window.location.href = 'phar_analytics.php';
    });

    function chartColors() {
        const dark = document.body.classList.contains('dark-mode') || document.documentElement.classList.contains('dark-mode');
        return {
            text: dark ? '#e2e8f0' : '#334155',
            grid: dark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(15, 63, 40, 0.10)',
            green: '#1b5e3f',
            mint: '#6ee7b7',
            warn: '#f59e0b',
            red: '#ef4444',
            blue: '#0891b2'
        };
    }

    function renderChart(id, config) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') return;
        if (chartRefs[id]) chartRefs[id].destroy();
        chartRefs[id] = new Chart(canvas, config);
    }

    function renderEmptyChart(id, message) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        if (chartRefs[id]) chartRefs[id].destroy();
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '14px Inter, sans-serif';
        ctx.fillStyle = chartColors().text;
        ctx.textAlign = 'center';
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
    }

    // -------------------------------------------------------------------
    // Fetch the user's current low_stock_threshold from settings.
    // Returns a Promise that resolves to a number (fallback: 10).
    // -------------------------------------------------------------------
    function fetchLowStockThreshold() {
        return fetch('api/phar_settings.php', { credentials: 'same-origin' })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(res => {
                const val = res?.data?.low_stock_threshold;
                return (res.success && val && !isNaN(val) && parseInt(val) > 0)
                    ? parseInt(val)
                    : 10;
            })
            .catch(err => {
                console.warn('Could not fetch threshold from settings, using default 10:', err);
                return 10;
            });
    }

    // -------------------------------------------------------------------
    // Demand chart
    // -------------------------------------------------------------------
    function loadDashboardDemandChart() {
        fetch('api/phar_analytics.php?action=daily_trends')
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(res => {
                if (!res.success || !Array.isArray(res.data)) {
                    renderEmptyChart('dashboardDemandChart', 'No demand data');
                    return;
                }
                const labels = res.data.map(item => item.date);
                const demand = res.data.map(item => Number(item.total_demand) || 0);
                if (labels.length === 0) {
                    renderEmptyChart('dashboardDemandChart', 'No demand data');
                    return;
                }
                renderChart('dashboardDemandChart', {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Demand',
                            data: demand,
                            borderColor: chartColors().green,
                            backgroundColor: 'rgba(34,197,94,0.14)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { ticks: { color: chartColors().text }, grid: { display: false } },
                            y: { ticks: { color: chartColors().text }, grid: { color: chartColors().grid }, beginAtZero: true }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Dashboard demand chart error:', error);
                renderEmptyChart('dashboardDemandChart', 'Error loading graph');
            });
    }

    function loadDashboardTrendsChart() {
        fetch('api/phar_analytics.php?action=daily_trends')
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(res => {
                if (!res.success || !Array.isArray(res.data)) {
                    renderEmptyChart('dashboardTrendsChart', 'No trend data');
                    return;
                }
                const labels = res.data.map(item => item.date);
                const demand = res.data.map(item => Number(item.total_demand) || 0);
                const supply = res.data.map(item => Number(item.total_supply) || 0);
                if (labels.length === 0) {
                    renderEmptyChart('dashboardTrendsChart', 'No trend data');
                    return;
                }
                renderChart('dashboardTrendsChart', {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: 'Supply',
                                data: supply,
                                borderColor: chartColors().blue,
                                backgroundColor: 'rgba(2,132,199,0.13)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 0
                            },
                            {
                                label: 'Demand',
                                data: demand,
                                borderColor: chartColors().red,
                                backgroundColor: 'rgba(239,68,68,0.13)',
                                fill: true,
                                tension: 0.3,
                                pointRadius: 0
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', labels: { color: chartColors().text } } },
                        scales: {
                            x: { ticks: { color: chartColors().text }, grid: { display: false } },
                            y: { ticks: { color: chartColors().text }, grid: { color: chartColors().grid }, beginAtZero: true }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Dashboard trends chart error:', error);
                renderEmptyChart('dashboardTrendsChart', 'Error loading graph');
            });
    }

    function loadDashboardStockForecastChart() {
        fetchLowStockThreshold()
            .then(threshold => fetch(`api/phar_analytics.php?action=low_stock_forecast&threshold=${threshold}`))
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(res => {
                if (!res.success || !Array.isArray(res.data)) {
                    renderEmptyChart('dashboardStockForecastChart', 'No low-stock data');
                    return;
                }
                const data = res.data.slice(0, 5);
                if (data.length === 0) {
                    renderEmptyChart('dashboardStockForecastChart', 'No low-stock data');
                    return;
                }
                const labels = data.map(item => item.name);
                const values = data.map(item => Number(item.days_until_empty) || 0);
                const colors = data.map(item => item.status === 'critical' ? chartColors().red : chartColors().warn);
                renderChart('dashboardStockForecastChart', {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'Days until empty',
                            data: values,
                            backgroundColor: colors,
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        indexAxis: 'y',
                        scales: {
                            x: { ticks: { color: chartColors().text }, grid: { color: chartColors().grid }, beginAtZero: true },
                            y: { ticks: { color: chartColors().text }, grid: { display: false } }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Dashboard low stock chart error:', error);
                renderEmptyChart('dashboardStockForecastChart', 'Error loading graph');
            });
    }

    // -------------------------------------------------------------------
    // Quick Insights card
    // -------------------------------------------------------------------
    function loadDashboardQuickInsights() {
        try {
            const demandEl = document.getElementById('demand-forecast-value');
            const supplyEl = document.getElementById('supply-demand-value');
            const lowStockEl = document.getElementById('low-stock-forecast-value');
            const topMedEl = document.getElementById('top-medicine-value');

            if (demandEl) demandEl.textContent = 'Loading...';
            if (supplyEl) supplyEl.textContent = 'Loading...';
            if (lowStockEl) lowStockEl.textContent = 'Loading...';
            if (topMedEl) topMedEl.textContent = 'Loading...';

            const loadAnalytics = (action, params = {}) => {
                const query = new URLSearchParams(params);
                const url = `api/phar_analytics.php?action=${encodeURIComponent(action)}&${query.toString()}`;
                return fetch(url)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        return response.json();
                    });
            };

            Promise.all([
                loadAnalytics('daily_trends'),
                fetchLowStockThreshold().then(threshold => loadAnalytics('low_stock_forecast', { threshold })),
                loadAnalytics('top_medicines', { limit: 1 })
            ])
            .then(([trendsRes, lowStockRes, topRes]) => {
                if (trendsRes.success && Array.isArray(trendsRes.data)) {
                    const demandTotal = trendsRes.data.reduce((sum, item) => sum + (Number(item.total_demand) || 0), 0);
                    const supplyTotal = trendsRes.data.reduce((sum, item) => sum + (Number(item.total_supply) || 0), 0);
                    if (demandEl) demandEl.innerHTML = `<strong>${demandTotal}</strong><span class="badge bg-success">Demand</span>`;
                    if (supplyEl) supplyEl.innerHTML = `<strong>${supplyTotal}</strong><span class="badge bg-info">Supply</span>`;
                } else {
                    if (demandEl) demandEl.innerHTML = '<strong>0</strong><span class="badge bg-success">Demand</span>';
                    if (supplyEl) supplyEl.innerHTML = '<strong>0</strong><span class="badge bg-info">Supply</span>';
                }

                if (lowStockRes.success && Array.isArray(lowStockRes.data)) {
                    const count = lowStockRes.data.length;
                    if (lowStockEl) {
                        const label = count === 0 ? 'No urgent items' : `${count} at risk`;
                        lowStockEl.innerHTML = `<strong>${count}</strong><span class="badge bg-warning text-dark">Low Stock</span><br><small>${label}</small>`;
                    }
                } else if (lowStockEl) {
                    lowStockEl.innerHTML = '<strong>0</strong><span class="badge bg-warning text-dark">Low Stock</span><br><small>No urgent items</small>';
                }

                if (topRes.success && Array.isArray(topRes.data) && topRes.data.length > 0) {
                    const medicine = topRes.data[0];
                    if (topMedEl) {
                        topMedEl.innerHTML = `<strong>${medicine.medicine_name || 'Unknown'}</strong><span class="badge bg-primary">Top</span><br><small>${Number(medicine.total_demand || 0)} demand</small>`;
                    }
                } else if (topMedEl) {
                    topMedEl.innerHTML = '<strong>No data</strong><span class="badge bg-secondary">Top</span>';
                }
            })
            .catch(err => {
                console.error('Quick insights analytics error:', err);
                if (demandEl) demandEl.textContent = 'Data unavailable';
                if (supplyEl) supplyEl.textContent = 'Data unavailable';
                if (lowStockEl) lowStockEl.textContent = 'Data unavailable';
                if (topMedEl) topMedEl.textContent = 'Data unavailable';
            });
        } catch (e) {
            console.error('CRITICAL ERROR in loadDashboardQuickInsights:', e);
        }
    }

    // -------------------------------------------------------------------
    // Load inventory totals + expiring medicines (replaces low-stock list)
    // -------------------------------------------------------------------
    function loadInventory() {
        fetch('api/phar_inventory.php')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(res => {
                if (!res.success) throw new Error('API error');

                const totalEl = document.getElementById('total-medicines');
                if (totalEl) {
                    const count = Array.isArray(res.expiringItems) ? res.expiringItems.length : 0;
                    totalEl.textContent = count;
                }

                // ── Expiring Medicines list ──────────────────────────────
                const expiringContainer = document.getElementById('expiring-items');
                if (expiringContainer) {
                    expiringContainer.innerHTML = '';

                    if (Array.isArray(res.expiringItems) && res.expiringItems.length) {
                        res.expiringItems.forEach(item => {
                            let badgeClass, badgeLabel;
                            if (item.status === 'expired') {
                                badgeClass = 'bg-danger';
                                badgeLabel = 'Expired';
                            } else if (item.status === 'critical') {
                                badgeClass = 'bg-danger';
                                badgeLabel = `${item.days}d left`;
                            } else if (item.status === 'warning') {
                                badgeClass = 'bg-warning text-dark';
                                badgeLabel = `${item.days}d left`;
                            } else {
                                badgeClass = 'bg-secondary';
                                badgeLabel = `${item.days}d left`;
                            }

                            // Format expiry date as "MMM YYYY" e.g. "Nov 2026"
                            const expDate = new Date(item.expiry_date);
                            const expFormatted = expDate.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });

                            const div = document.createElement('div');
                            div.className = 'expiring-item';
                            div.innerHTML = `
                                <span class="exp-icon"><i class="bi bi-calendar-x"></i></span>
                                <span class="item-name">${item.name}</span>
                                <span class="exp-date">${expFormatted}</span>
                                <span class="badge ${badgeClass} ms-1">${badgeLabel}</span>
                            `;
                            expiringContainer.appendChild(div);
                        });
                    } else {
                        expiringContainer.innerHTML = '<div class="empty-state"><i class="bi bi-calendar-check"></i><span>No medicines expiring soon</span></div>';
                    }
                }
            })
            .catch(err => {
                console.error('Inventory load error:', err);
                const totalEl = document.getElementById('total-medicines');
                if (totalEl) totalEl.textContent = 'Error';
                const expiringContainer = document.getElementById('expiring-items');
                if (expiringContainer) expiringContainer.innerHTML = '<div class="empty-state danger"><i class="bi bi-exclamation-triangle"></i><span>Error loading data</span></div>';
            })
            .finally(() => {
                if (inventoryLoading) inventoryLoading.style.display = 'none';
            });
    }

    function loadSuppliers() {
        fetch('api/phar_dashboard_suppliers.php')
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(res => {
                if (!res.success) throw new Error('API error');

                const totalEl = document.getElementById('total-suppliers');
                const suppliers = Array.isArray(res.data) ? res.data : [];
                if (totalEl) totalEl.textContent = suppliers.length;

                const active = suppliers.filter(s => s.bought_quantity > 0).length;
                const inactive = suppliers.length - active;
                renderChart('supplierStatusChart', {
                    type: 'doughnut',
                    data: {
                        labels: ['Active', 'Inactive'],
                        datasets: [{
                            data: [active, inactive],
                            backgroundColor: [chartColors().green, chartColors().red],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: chartColors().text } } },
                        cutout: '65%'
                    }
                });

                const container = document.getElementById('supplier-companies');
                if (container) {
                    container.innerHTML = '';
                    if (suppliers.length) {
                        suppliers.forEach(sup => {
                            const div = document.createElement('div');
                            div.className = 'company-item';
                            div.innerHTML = `<strong>${sup.company}</strong><br><small>${sup.name}</small>`;
                            container.appendChild(div);
                        });
                    } else {
                        container.innerHTML = '<div class="empty-state">No suppliers</div>';
                    }
                }
            })
            .catch(err => {
                console.error('Suppliers load error:', err);
                const totalEl = document.getElementById('total-suppliers');
                if (totalEl) totalEl.textContent = 'Error';
                const container = document.getElementById('supplier-companies');
                if (container) container.innerHTML = '<div class="empty-state danger">Error loading suppliers</div>';
            })
            .finally(() => { if (suppliersLoading) suppliersLoading.style.display = 'none'; });
    }

    function loadAlerts() {
        fetch('api/phar_notifications.php?read=0')
            .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
            .then(res => {
                const alertsContainer = document.getElementById('alerts');
                if (!alertsContainer) return;
                alertsContainer.innerHTML = '';

                const items = Array.isArray(res.data) ? res.data : [];
                if (!items.length) {
                    alertsContainer.innerHTML = '<div class="empty-state"><i class="bi bi-bell-slash"></i><span>No alerts</span></div>';
                    return;
                }
                items.forEach(alert => {
                    const div = document.createElement('div');
                    const alertClass = alert.type === 'danger' ? 'alert-danger' : 'alert-warning';
                    div.className = `alert ${alertClass}`;
                    div.textContent = alert.message || 'Alert';
                    alertsContainer.appendChild(div);
                });
            })
            .catch(err => {
                console.error('Alerts load error:', err);
                const alertsContainer = document.getElementById('alerts');
                if (alertsContainer) alertsContainer.innerHTML = '<div class="empty-state danger"><i class="bi bi-exclamation-triangle"></i><span>Error loading alerts</span></div>';
            })
            .finally(() => { if (alertsLoading) alertsLoading.style.display = 'none'; });
    }

    // Load stock ticker
    function loadStockTicker() {
        fetch('api/phar_inventory.php')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(res => {
                const tickerEl = document.getElementById('stock-ticker-content');
                if (!tickerEl) return;
                if (!res.success) throw new Error('API error');

                const parts = [];

                // Low stock segment
                if (Array.isArray(res.lowStockItems) && res.lowStockItems.length > 0) {
                    const names = res.lowStockItems.map(item => `${item.name} (${item.quantity} units)`);
                    parts.push(`⚠ Low stock: ${names.join(', ')}`);
                } else {
                    parts.push('✓ All medicines adequately stocked');
                }

                // Expiring segment
                if (Array.isArray(res.expiringItems) && res.expiringItems.length > 0) {
                    const expNames = res.expiringItems
                        .filter(i => i.status === 'expired' || i.status === 'critical')
                        .map(i => i.name);
                    if (expNames.length > 0) {
                        parts.push(`🗓 Expiring soon: ${expNames.join(', ')}`);
                    }
                }

                tickerEl.textContent = parts.join('   •   ');
            })
            .catch(err => {
                console.error('Stock ticker error:', err);
                const tickerEl = document.getElementById('stock-ticker-content');
                if (tickerEl) tickerEl.textContent = 'Error loading stock data';
            });
    }

    // Update date and time dynamically
    function updateDateTime() {
        const now = new Date();
        const dateTimeEl = document.getElementById('current-date-time');
        if (!dateTimeEl) return;
        dateTimeEl.textContent = now.toLocaleString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    // Load top 10 medicines by demand
    function loadTopMedicines() {
        const topMedicinesEl = document.getElementById('top-medicines');
        const loadingEl = document.getElementById('top-medicines-loading');
        if (!topMedicinesEl) return;
        if (loadingEl) loadingEl.style.display = 'block';

        fetch('api/phar_analytics.php?action=top_medicines&limit=10')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(res => {
                if (loadingEl) loadingEl.style.display = 'none';
                if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                    topMedicinesEl.innerHTML = '<div class="empty-state"><i class="bi bi-box"></i>No medicines found</div>';
                    return;
                }
                let html = '';
                res.data.forEach((med, i) => {
                    const demand = Number(med.total_demand) || 0;
                    html += `<div class="top-med-item"><span class="rank">${i + 1}</span><span class="name">${med.medicine_name || 'Unknown'}</span><span class="badge bg-info">${demand} units</span></div>`;
                });
                topMedicinesEl.innerHTML = html;
            })
            .catch(err => {
                console.error('Top medicines error:', err);
                if (loadingEl) loadingEl.style.display = 'none';
                topMedicinesEl.innerHTML = '<div class="empty-state danger"><i class="bi bi-exclamation-triangle"></i>Error loading</div>';
            });
    }

    // Manual refresh
    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            loadInventory();
            loadSuppliers();
            loadAlerts();
            loadTopMedicines();
            loadDashboardQuickInsights();
            loadDashboardStockForecastChart();
            loadStockTicker();
            showToast('Data refreshed successfully');
        });
    }

    // Navigation
    window.showDetails = (type) => {
        const pages = {
            alerts: 'phar_notifications.php',
            inventory: 'phar_medicine.php',
        };
        if (pages[type]) window.location.href = pages[type];
    };

    // Ctrl+R refresh
    document.addEventListener('keydown', e => {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshButton?.click();
        }
    });

    // Initial data load
    loadInventory();
    loadSuppliers();
    loadAlerts();
    loadTopMedicines();
    loadDashboardQuickInsights();
    loadDashboardDemandChart();
    loadDashboardTrendsChart();
    loadDashboardStockForecastChart();
    loadStockTicker();
    updateDateTime();
});

// Utility function for toasts
function showToast(message) {
    console.log('Toast:', message);
}
