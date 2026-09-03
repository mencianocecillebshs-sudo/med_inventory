document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements
    const dateTimeEl = document.getElementById('current-date-time');
    const alertsListEl = document.getElementById('alerts');
    const alertsCountEl = document.getElementById('alerts-count');
    const topMedsEl = document.getElementById('topMeds');
    const supplierListEl = document.getElementById('supplier-companies');
    const activityListEl = document.getElementById('activity-list');
    const toggleSidebarBtn = document.getElementById('toggle-sidebar-mobile');
    const autoOrderSummaryModalEl = document.getElementById('autoOrderSummaryModal');
    const autoOrderSummaryBody = document.getElementById('auto-order-summary-body');
    const autoOrderSummaryTotal = document.getElementById('auto-order-summary-total');
    const confirmAutoOrderBtn = document.getElementById('confirm-auto-order-btn');
    const autoOrderBanner = document.getElementById('auto-order-banner');
    const autoOrderBannerSub = document.getElementById('auto-order-banner-sub');
    const autoOrderBannerAccept = document.getElementById('auto-order-banner-accept');
    const autoOrderBannerReject = document.getElementById('auto-order-banner-reject');
    const autoOrderSummaryModal = autoOrderSummaryModalEl ? bootstrap.Modal.getOrCreateInstance(autoOrderSummaryModalEl) : null;
    let pendingAutoOrderRequest = null;

    // Chart.js instance ref
    let trendChartInstance = null;

    function currentMonthRange() {
        const now = new Date();
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        const iso = date => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };
        return { start: iso(start), end: iso(end) };
    }

    document.querySelectorAll('.dashboard-link[data-href]').forEach(card => {
        const openCard = () => {
            window.location.href = card.dataset.href;
        };
        card.addEventListener('click', openCard);
        card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openCard();
            }
        });
    });

    // Mobile Sidebar toggle
    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', () => {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
            }
        });
    }

    // 1. Dynamic Clock
    function updateDateTime() {
        if (!dateTimeEl) return;
        const now = new Date();
        dateTimeEl.textContent = now.toLocaleString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Helper: relative time calculation
    function getRelativeTime(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays === 1) return 'yesterday';
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    // Helper: format currency
    function formatCurrency(val) {
        if (val >= 1000000) {
            return '₱' + (val / 1000000).toFixed(1) + 'M';
        } else if (val >= 1000) {
            return '₱' + (val / 1000).toFixed(0) + 'K';
        }
        return '₱' + Math.round(val).toLocaleString();
    }

    function money(value) {
        return 'PHP ' + Number(value || 0).toFixed(2);
    }

    function renderAutoOrderSummary(request) {
        if (!autoOrderSummaryBody || !request) return;
        const orderableItems = (request.items || []).filter(item => item.can_order !== false);
        autoOrderSummaryBody.innerHTML = (request.items || []).map(item => `
            <tr>
                <td><strong>${item.medicine_name || 'Unknown'}</strong></td>
                <td>${Number(item.current_quantity || 0).toLocaleString()} units</td>
                <td style="white-space: normal; min-width: 200px;">
                    ${item.supplier_name || 'No supplier'}
                    ${Number(item.is_preferred || 0) === 1 ? '<span class="badge bg-success ms-2">Preferred</span>' : ''}
                </td>
                <td style="white-space: normal; min-width: 160px;">
                    ${item.can_order === false
                        ? `<span class="badge bg-warning text-dark" style="white-space: normal;">${item.reason || 'Needs attention'}</span>`
                        : '<span class="badge bg-success">Ready to order</span>'}
                </td>
                <td>${Number(item.quantity || 0).toLocaleString()}</td>
                <td>${money(item.unit_price)}</td>
                <td>${money(item.subtotal)}</td>
            </tr>
        `).join('');
        if (autoOrderSummaryTotal) autoOrderSummaryTotal.textContent = money(request.total_amount);
        if (confirmAutoOrderBtn) {
            confirmAutoOrderBtn.disabled = orderableItems.length === 0;
            confirmAutoOrderBtn.innerHTML = orderableItems.length
                ? '<i class="bi bi-check2-circle me-1"></i>Create Orders'
                : '<i class="bi bi-exclamation-triangle me-1"></i>No Orderable Items';
        }
    }

    function handleAutoOrderAction(action) {
        if (!pendingAutoOrderRequest?.id) return;
        const body = new FormData();
        body.append('action', action);
        body.append('request_id', pendingAutoOrderRequest.id);

        if (action === 'accept' && confirmAutoOrderBtn) {
            confirmAutoOrderBtn.disabled = true;
            confirmAutoOrderBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
        }

        fetch('api/auto_order_requests.php', { method: 'POST', body })
            .then(r => r.json())
            .then(res => {
                if (!res.success) throw new Error(res.message || 'Auto-order request failed.');
                pendingAutoOrderRequest = null;
                autoOrderBanner?.classList.remove('show');
                autoOrderSummaryModal?.hide();
                fetchAlerts();
                fetchDashboardMetrics();
            })
            .catch(err => {
                alert(err.message);
            })
            .finally(() => {
                if (confirmAutoOrderBtn) {
                    confirmAutoOrderBtn.disabled = false;
                    confirmAutoOrderBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Create Orders';
                }
            });
    }

    function renderAutoOrderAlert(request) {
        if (!alertsListEl || !request || !Array.isArray(request.items) || request.items.length === 0) return false;
        pendingAutoOrderRequest = request;
        const row = document.createElement('div');
        const orderableCount = request.items.filter(item => item.can_order !== false).length;
        if (autoOrderBanner && autoOrderBannerSub) {
            autoOrderBannerSub.textContent = `${request.items.length} low-stock item(s), ${orderableCount} ready to order, ${money(request.total_amount)} estimated total.`;
            autoOrderBanner.classList.add('show');
        }
        row.className = 'alert-row auto-order';
        row.innerHTML = `
            <i class="bi bi-cart-plus-fill text-primary mt-1"></i>
            <div>
                <div class="a-title">You need to order now</div>
                <div class="a-sub">${request.items.length} low-stock item(s), ${orderableCount} ready to order, ${money(request.total_amount)} estimated total</div>
            </div>
            <div class="auto-order-actions">
                <button type="button" class="btn btn-primary btn-sm" id="auto-order-accept-btn">
                    <i class="bi bi-check2-circle me-1"></i>Accept
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="auto-order-reject-btn">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
            </div>
        `;
        alertsListEl.prepend(row);
        document.getElementById('auto-order-accept-btn')?.addEventListener('click', () => {
            pendingAutoOrderRequest = request;
            renderAutoOrderSummary(request);
            autoOrderSummaryModal?.show();
        });
        document.getElementById('auto-order-reject-btn')?.addEventListener('click', () => {
            pendingAutoOrderRequest = request;
            handleAutoOrderAction('reject');
        });
        return true;
    }

    // 2. Fetch main dashboard metrics (KPIs, suppliers, activity)
    function fetchDashboardMetrics() {
        fetch('api/dashboard_metrics.php')
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(res => {
                if (!res.success) throw new Error('API reported failure');

                // A. KPIs
                const kpis = res.kpis;
                
                // Current month sales
                const salesValEl = document.getElementById('kpi-sales-value');
                const salesDeltaEl = document.getElementById('kpi-sales-delta');
                if (salesValEl) salesValEl.textContent = formatCurrency(kpis.month_sales || 0);
                if (salesDeltaEl) {
                    const delta = kpis.sales_change_percent;
                    if (delta > 0) {
                        salesDeltaEl.className = 'delta up';
                        salesDeltaEl.innerHTML = `<i class="bi bi-arrow-up-short"></i>${delta.toFixed(0)}% vs next month forecast`;
                    } else if (delta < 0) {
                        salesDeltaEl.className = 'delta down';
                        salesDeltaEl.innerHTML = `<i class="bi bi-arrow-down-short"></i>${Math.abs(delta).toFixed(0)}% vs next month forecast`;
                    } else {
                        salesDeltaEl.className = 'delta flat';
                        salesDeltaEl.innerHTML = `<i class="bi bi-dash"></i>tracks next month forecast`;
                    }
                }

                // Inventory Value
                const invValEl = document.getElementById('kpi-inventory-value');
                if (invValEl) invValEl.textContent = formatCurrency(kpis.inventory_value);

                // Low Stock
                const lowValEl = document.getElementById('kpi-low-stock-value');
                const lowDeltaEl = document.getElementById('kpi-low-stock-delta');
                const lowCard = document.getElementById('kpi-low-stock');
                if (lowValEl) lowValEl.textContent = kpis.low_stock_count;
                if (lowDeltaEl) lowDeltaEl.textContent = `threshold: ${kpis.low_stock_threshold} units`;
                if (lowCard) {
                    if (kpis.low_stock_count > 0) {
                        lowCard.classList.add('danger');
                    } else {
                        lowCard.classList.remove('danger');
                    }
                }

                // Expiring in 30 days
                const expValEl = document.getElementById('kpi-expiring-value');
                const expDeltaEl = document.getElementById('kpi-expiring-delta');
                const expLabelEl = document.getElementById('kpi-expiring-label');
                const expCard = document.getElementById('kpi-expiring');
                if (expValEl) expValEl.textContent = kpis.expiring_count_30;
                if (expLabelEl) expLabelEl.textContent = `Expiring in ${kpis.expiry_alert_days} days`;
                if (expDeltaEl) expDeltaEl.textContent = `across ${kpis.expiring_suppliers_count} suppliers`;
                if (expCard) {
                    if (kpis.expiring_count_30 > 0) {
                        expCard.classList.add('warn');
                    } else {
                        expCard.classList.remove('warn');
                    }
                }

                // Pending Orders
                const pendValEl = document.getElementById('kpi-pending-value');
                const pendDeltaEl = document.getElementById('kpi-pending-delta');
                if (pendValEl) pendValEl.textContent = kpis.pending_orders_count;
                if (pendDeltaEl) {
                    if (kpis.pending_orders_awaiting_approval > 0) {
                        pendDeltaEl.textContent = `${kpis.pending_orders_awaiting_approval} awaiting approval`;
                    } else {
                        pendDeltaEl.textContent = 'steady';
                    }
                }

                // B. Supplier Status Chips
                if (supplierListEl) {
                    supplierListEl.innerHTML = '';
                    const suppliers = res.supplier_status || [];
                    
                    const activeCountEl = document.getElementById('active-suppliers-count');
                    if (activeCountEl) activeCountEl.textContent = `${suppliers.length} active`;

                    if (suppliers.length > 0) {
                        suppliers.forEach(sup => {
                            const chip = document.createElement('div');
                            chip.className = 'supplier-chip';
                            chip.innerHTML = `
                                <span>${sup.company}</span>
                                <span class="status ${sup.class}">${sup.status}</span>
                            `;
                            supplierListEl.appendChild(chip);
                        });
                    } else {
                        supplierListEl.innerHTML = '<div class="empty-state">No suppliers registered</div>';
                    }
                }

                // C. Recent Activity list
                if (activityListEl) {
                    activityListEl.innerHTML = '';
                    const activities = res.recent_activity || [];
                    if (activities.length > 0) {
                        activities.forEach(act => {
                            const actVerb = act.action === 'add' ? 'restocked' : (act.action === 'remove' ? 'sold' : 'modified');
                            const sign = act.action === 'add' ? '+' : (act.action === 'remove' ? '-' : '');
                            const qtyText = act.quantity ? ` (${sign}${act.quantity} units)` : '';
                            const user = act.user_name || 'System';
                            const timeText = getRelativeTime(act.timestamp);

                            const row = document.createElement('div');
                            row.className = 'activity-row';
                            row.innerHTML = `
                                <div class="activity-dot"></div>
                                <div>
                                    <div><span class="a-user">${user}</span> ${actVerb} ${act.medicine_name}${qtyText}</div>
                                    <div class="a-time">${timeText}</div>
                                </div>
                            `;
                            activityListEl.appendChild(row);
                        });
                    } else {
                        activityListEl.innerHTML = '<div class="empty-state">No recent activities</div>';
                    }
                }
            })
            .catch(err => {
                console.error('Failed to load dashboard metrics:', err);
            });
    }

    // 3. Fetch top medicines
    function fetchTopMedicines() {
        if (!topMedsEl) return;
        const range = currentMonthRange();
        fetch(`api/analytics.php?action=top_medicines&limit=10&start=${range.start}&end=${range.end}`)
            .then(r => r.json())
            .then(res => {
                topMedsEl.innerHTML = '';
                if (res.success && Array.isArray(res.data) && res.data.length > 0) {
                    res.data.forEach((med, i) => {
                        const row = document.createElement('div');
                        row.className = 'med-row';
                        row.innerHTML = `
                            <span class="rank">${i + 1}</span>
                            <span class="name">${med.medicine_name || 'Unknown'}</span>
                            <span class="qty">${Number(med.total_demand).toLocaleString()} units</span>
                        `;
                        topMedsEl.appendChild(row);
                    });
                } else {
                    topMedsEl.innerHTML = '<div class="empty-state"><i class="bi bi-trophy"></i>No sales demand recorded</div>';
                }
            })
            .catch(err => {
                console.error('Top medicines fetch error:', err);
                topMedsEl.innerHTML = '<div class="empty-state">Failed to load top medicines</div>';
            });
    }

    // 4. Fetch alerts
    function fetchAlerts() {
        if (!alertsListEl) return;
        fetch('api/notifications.php?read=0')
            .then(r => r.json())
            .then(res => {
                alertsListEl.innerHTML = '';
                const alerts = res.data || [];
                
                let autoOrderVisible = false;

                if (alerts.length > 0) {
                    alerts.forEach(alert => {
                        // Classify severity
                        const isCritical = alert.type === 'danger' || 
                                           alert.message.toLowerCase().includes('out of stock') || 
                                           alert.message.toLowerCase().includes('expires soon') ||
                                           alert.message.toLowerCase().includes('nearing expiry within 30');
                        
                        const alertClass = isCritical ? 'critical' : 'warning';
                        const icon = isCritical ? 'bi-x-octagon-fill text-danger' : 'bi-exclamation-triangle-fill text-warning';

                        // Parse Title and Subtext
                        let title = alert.message;
                        let subText = 'System alert';
                        
                        if (alert.message.includes('alert:')) {
                            const p = alert.message.split('alert:');
                            title = p[0].trim() + ' Alert';
                            subText = p[1].trim();
                        } else if (alert.message.includes(':')) {
                            const p = alert.message.split(':');
                            title = p[0].trim();
                            subText = p[1].trim();
                        } else if (alert.message.includes('—')) {
                            const p = alert.message.split('—');
                            title = p[0].trim();
                            subText = p[1].trim();
                        }

                        const row = document.createElement('div');
                        row.className = `alert-row ${alertClass}`;
                        row.innerHTML = `
                            <i class="bi ${icon} mt-1"></i>
                            <div>
                                <div class="a-title">${title}</div>
                                <div class="a-sub">${subText} · ${getRelativeTime(alert.created_at)}</div>
                            </div>
                        `;
                        alertsListEl.appendChild(row);
                    });
                } else {
                    alertsListEl.innerHTML = '<div class="empty-state"><i class="bi bi-bell-slash"></i>No alerts at this time</div>';
                }

                fetch('api/auto_order_requests.php')
                    .then(r => r.json())
                    .then(autoRes => {
                        if (autoRes.success && autoRes.data) {
                            if (!alerts.length) alertsListEl.innerHTML = '';
                            autoOrderVisible = renderAutoOrderAlert(autoRes.data);
                        } else {
                            pendingAutoOrderRequest = null;
                            autoOrderBanner?.classList.remove('show');
                        }
                        if (alertsCountEl) alertsCountEl.textContent = alerts.length + (autoOrderVisible ? 1 : 0);
                    })
                    .catch(() => {
                        if (alertsCountEl) alertsCountEl.textContent = alerts.length;
                    });
            })
            .catch(err => {
                console.error('Alerts load error:', err);
                alertsListEl.innerHTML = '<div class="empty-state">Failed to load alerts</div>';
            });
    }

    confirmAutoOrderBtn?.addEventListener('click', () => handleAutoOrderAction('accept'));
    autoOrderBannerAccept?.addEventListener('click', () => {
        if (!pendingAutoOrderRequest) return;
        renderAutoOrderSummary(pendingAutoOrderRequest);
        autoOrderSummaryModal?.show();
    });
    autoOrderBannerReject?.addEventListener('click', () => handleAutoOrderAction('reject'));

    // 5. Render trend chart (14 Days Sales vs Forecast)
    function loadTrendChart() {
        const canvas = document.getElementById('trendChart');
        if (!canvas) return;

        const range = currentMonthRange();
        fetch(`api/analytics.php?action=daily_trends&start=${range.start}&end=${range.end}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                    renderEmptyChart('trendChart', 'No trend data available');
                    return;
                }

                // Format labels and extract data
                const labels = [];
                const salesData = [];
                const forecastData = [];

                res.data.forEach(item => {
                    // format date to "MMM D"
                    const dateObj = new Date(item.date);
                    const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    labels.push(formattedDate);
                    
                    const sales = Number(item.total_demand) || 0;
                    salesData.push(sales);
                });

                // Generate forecast values dynamically (simple projection moving average)
                let runningSum = 0;
                salesData.forEach((val, idx) => {
                    runningSum += val;
                    const avg = runningSum / (idx + 1);
                    forecastData.push(Math.round(avg * 1.05 + 1));
                });

                const isDark = document.body.classList.contains('dark-mode') || document.documentElement.classList.contains('dark-mode');
                const primaryColor = '#1b5e3f';
                const secondaryColor = '#2ecc71';
                const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)';
                const textColor = isDark ? '#94a3b8' : '#64748b';

                if (trendChartInstance) {
                    trendChartInstance.destroy();
                }

                trendChartInstance = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Sales (units)',
                                data: salesData,
                                borderColor: primaryColor,
                                backgroundColor: 'rgba(27, 94, 63, 0.08)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 2
                            },
                            {
                                label: 'Demand Forecast',
                                data: forecastData,
                                borderColor: secondaryColor,
                                borderDash: [5, 4],
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 10,
                                    font: { size: 11, family: 'Inter' },
                                    color: textColor
                                }
                            }
                        },
                        scales: {
                            y: {
                                grid: { color: gridColor },
                                ticks: { font: { size: 10 }, color: textColor }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: textColor }
                            }
                        }
                    }
                });
            })
            .catch(err => {
                console.error('Trend chart fetch error:', err);
                renderEmptyChart('trendChart', 'Failed to load graph');
            });
    }

    function renderEmptyChart(id, message) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '14px Inter, sans-serif';
        ctx.fillStyle = '#64748b';
        ctx.textAlign = 'center';
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
    }

    // Initial Load
    fetchDashboardMetrics();
    fetchTopMedicines();
    fetchAlerts();
    loadTrendChart();

    // Listen to dark mode changes if any
    const observer = new MutationObserver(() => {
        loadTrendChart();
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
});