document.addEventListener('DOMContentLoaded', () => {
    const U = window.SupUtils;
    const peso = U ? U.currencySymbol() : '₱';

    function getElement(id) {
        return document.getElementById(id);
    }

    async function fetchJson(url, options = {}) {
        try {
            const response = await fetch(url, {
                credentials: 'include',
                ...options
            });
            const text = await response.text();

            let payload;
            try {
                payload = text ? JSON.parse(text) : {};
            } catch (error) {
                console.error(`Invalid JSON from ${url}:`, text.slice(0, 120));
                return { success: false, error: 'Invalid response format' };
            }

            if (!response.ok || payload.success === false) {
                console.error(`API Error from ${url}:`, payload.message || payload.error);
                return payload;
            }

            return payload;
        } catch (error) {
            console.error('Fetch error:', error);
            return { success: false, error: error.message };
        }
    }

    function formatCurrency(value) {
        if (U) return U.formatCurrency(value);
        const amount = Number(value) || 0;
        return `${peso}${amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}`;
    }

    function showToast(message, type = 'success') {
        try {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} border-0`;
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body"></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            toast.querySelector('.toast-body').textContent = message;

            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            container.appendChild(toast);
            document.body.appendChild(container);

            const toastInstance = new bootstrap.Toast(toast);
            toast.addEventListener('hidden.bs.toast', () => container.remove(), { once: true });
            toastInstance.show();
        } catch (e) {
            console.error('Toast error:', e);
        }
    }

    function getRelativeTime(timestamp) {
        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) return timestamp || 'recently';

        const diffMinutes = Math.floor((Date.now() - date.getTime()) / 60000);
        if (diffMinutes < 1) return 'just now';
        if (diffMinutes < 60) return `${diffMinutes}m ago`;
        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) return `${diffHours}h ago`;
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays === 1) return 'yesterday';
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // Load KPI Metrics
    async function loadKPIMetrics() {
        try {
            // Load inventory data for total products and low stock
            const inventoryData = await fetchJson('api/sup_inventory.php');
            
            if (inventoryData.success !== false) {
                const totalProducts = Number(inventoryData.total_medicines) || 0;
                const lowStockCount = (Array.isArray(inventoryData.lowStockItems) ? inventoryData.lowStockItems.length : 0) + 
                                     (Array.isArray(inventoryData.zeroStockItems) ? inventoryData.zeroStockItems.length : 0);

                const kpiInventory = getElement('kpi-inventory-value');
                if (kpiInventory) kpiInventory.textContent = totalProducts;

                const kpiLowStock = getElement('kpi-low-stock-value');
                if (kpiLowStock) kpiLowStock.textContent = lowStockCount;

                const lowStockCard = getElement('kpi-low-stock');
                if (lowStockCard) lowStockCard.classList.toggle('danger', lowStockCount > 0);
            }

            // Load transactions for revenue
            const transactionData = await fetchJson('api/sup_transactions.php');
            
            if (transactionData.success !== false) {
                const summary = transactionData.summary || {};
                const transactions = Array.isArray(transactionData.data) ? transactionData.data : [];
                
                let totalRevenue = Number(summary.total_revenue) || 0;
                
                if (totalRevenue === 0 && Array.isArray(transactions)) {
                    totalRevenue = transactions.reduce((sum, transaction) => {
                        return sum + (Number(transaction.total_cost) || Number(transaction.line_total) || 0);
                    }, 0);
                }

                const kpiRevenue = getElement('kpi-revenue-value');
                if (kpiRevenue) kpiRevenue.textContent = formatCurrency(totalRevenue);
            }

            // Load orders for pending count
            const ordersData = await fetchJson('api/sup_orders.php');
            
            if (ordersData.success !== false) {
                let pendingCount = 0;
                if (Array.isArray(ordersData.data)) {
                    pendingCount = ordersData.data.filter(o => 
                        o.status === 'pending' || o.status === 'processing' || o.status === 'Pending' || o.status === 'Processing'
                    ).length;
                }

                const kpiPending = getElement('kpi-pending-value');
                if (kpiPending) kpiPending.textContent = pendingCount;
            }
        } catch (error) {
            console.error('KPI metrics error:', error);
        }
    }

    // Load Top Products
    async function loadTopProducts() {
        const container = getElement('topProducts');
        if (!container) return;

        try {
            const response = await fetchJson('api/sup_analytics.php?action=top_stocks&limit=10');
            
            if (response.success === false) {
                container.innerHTML = '<div class="text-muted text-center py-3">No data available</div>';
                return;
            }

            const products = Array.isArray(response.data) ? response.data : [];

            if (products.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-3">No product data available</div>';
                return;
            }

            container.innerHTML = '';
            products.forEach((product) => {
                const item = document.createElement('div');
                item.className = 'top-med-item';

                const nameEl = document.createElement('span');
                nameEl.className = 'name';
                nameEl.textContent = product.medicine_name || 'Unnamed product';

                const stockEl = document.createElement('span');
                stockEl.className = 'stock';
                stockEl.textContent = `${Number(product.quantity) || 0} units`;

                item.append(nameEl, stockEl);
                container.appendChild(item);
            });
        } catch (error) {
            console.error('Top products error:', error);
            container.innerHTML = '<div class="text-muted text-center py-3">Unable to load products</div>';
        }
    }

    // Load Alerts
    async function loadAlerts() {
        const container = getElement('alerts');
        if (!container) return;

        try {
            const data = await fetchJson('api/sup_notifications.php?read=0&limit=5&page=1');
            
            if (data.success === false) {
                container.innerHTML = '<div class="text-muted text-center py-3">No alerts at this time</div>';
                return;
            }

            const alerts = Array.isArray(data.notifications) ? data.notifications : [];

            if (alerts.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-3">No alerts at this time</div>';
                return;
            }

            const alertsCount = getElement('alerts-count');
            if (alertsCount) alertsCount.textContent = Number(data.total) || alerts.length;

            container.innerHTML = '';
            alerts.slice(0, 5).forEach((alert) => {
                const item = document.createElement('div');
                const message = String(alert.message || 'System alert');
                const isCritical = alert.type === 'danger'
                    || alert.type === 'error'
                    || /out of stock|critical|expired/i.test(message);
                item.className = `alert-row ${isCritical ? 'critical' : 'warning'}`;

                const typeEl = document.createElement('div');
                typeEl.className = 'a-title';
                const separator = message.indexOf(':');
                typeEl.textContent = separator > 0 ? message.slice(0, separator).trim() : (alert.type || 'ALERT');

                const messageEl = document.createElement('div');
                messageEl.className = 'a-sub';
                const separatorText = separator > 0 ? message.slice(separator + 1).trim() : message;
                messageEl.textContent = `${separatorText} - ${getRelativeTime(alert.created_at)}`;

                const icon = document.createElement('i');
                icon.className = `bi ${isCritical ? 'bi-x-octagon-fill text-danger' : 'bi-exclamation-triangle-fill text-warning'} mt-1`;
                const content = document.createElement('div');
                content.append(typeEl, messageEl);
                item.append(icon, content);
                container.appendChild(item);
            });
        } catch (error) {
            console.error('Alerts error:', error);
            container.innerHTML = '<div class="text-muted text-center py-3">Unable to load alerts</div>';
        }
    }

    // Load Recent Activity
    async function loadActivityLog() {
        const container = getElement('activity-list');
        if (!container) return;

        try {
            const response = await fetchJson('api/sup_analytics.php?action=activity');
            
            if (response.success === false) {
                container.innerHTML = '<div class="text-muted text-center py-3">No recent activity</div>';
                return;
            }

            const activities = Array.isArray(response.data) ? response.data : [];

            if (activities.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-3">No recent activity</div>';
                return;
            }

            container.innerHTML = '';
            activities.slice(0, 6).forEach((activity) => {
                const item = document.createElement('div');
                item.className = 'alert-item';

                const typeEl = document.createElement('div');
                typeEl.className = 'alert-type';
                typeEl.textContent = activity.action || activity.type || 'ACTION';

                const messageEl = document.createElement('div');
                messageEl.className = 'alert-message';
                messageEl.textContent = activity.details || activity.description || 'Activity recorded';

                const timeEl = document.createElement('div');
                timeEl.className = 'alert-time';
                timeEl.textContent = activity.timestamp || new Date().toLocaleString();

                item.append(typeEl, messageEl, timeEl);
                container.appendChild(item);
            });
        } catch (error) {
            console.error('Activity log error:', error);
            container.innerHTML = '<div class="text-muted text-center py-3">Unable to load activity</div>';
        }
    }

    // Load Trend Chart
    async function loadTrendChart() {
        const chartCanvas = getElement('trendChart');
        if (!chartCanvas) return;

        try {
            const data = await fetchJson('api/sup_chart_data.php?chart_type=sales_trend');
            
            if (data.success === false || !data.data) {
                console.warn('Chart data not available');
                return;
            }

            const chartData = data.data || {};

            if (!chartData.labels || !chartData.datasets || chartData.datasets.length === 0) {
                console.warn('Invalid chart data structure');
                return;
            }

            const ctx = chartCanvas.getContext('2d');
            
            // Destroy existing chart if any
            if (chartCanvas.chart) {
                chartCanvas.chart.destroy();
            }

            chartCanvas.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Sales (This Month)',
                        data: chartData.datasets[0]?.data || [],
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#1b5e3f',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            labels: {
                                font: { size: 12, weight: 'bold' }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return peso + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Chart loading error:', error);
        }
    }

    // Update DateTime
    function updateDateTime() {
        try {
            const now = new Date();
            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
            const datetimeEl = getElement('current-date-time');
            if (datetimeEl) {
                datetimeEl.textContent = now.toLocaleDateString('en-US', options);
            }
        } catch (e) {
            console.error('DateTime update error:', e);
        }
    }

    // Initialize on load
    try {
        updateDateTime();
        setInterval(updateDateTime, 60000);

        // Load all data
        loadKPIMetrics();
        loadTopProducts();
        loadAlerts();
        loadActivityLog();
        loadTrendChart();

        // Setup refresh button
        const refreshButton = getElement('refresh-btn');
        if (refreshButton) {
            refreshButton.addEventListener('click', () => {
                loadKPIMetrics();
                loadTopProducts();
                loadAlerts();
                loadActivityLog();
                showToast('Dashboard refreshed successfully!');
            });
        }

        // Make KPI cards clickable
        const kpiCards = document.querySelectorAll('.kpi-card.dashboard-link');
        kpiCards.forEach(card => {
            card.addEventListener('click', function() {
                const href = this.getAttribute('data-href');
                if (href) window.location.href = href;
            });
            card.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const href = this.getAttribute('data-href');
                    if (href) window.location.href = href;
                }
            });
        });
    } catch (error) {
        console.error('Dashboard initialization error:', error);
    }
});