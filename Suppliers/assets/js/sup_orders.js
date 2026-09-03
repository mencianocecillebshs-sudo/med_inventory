document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('orders-table');
    const paginationEl = document.getElementById('orders-pagination');
    const ordersCountEl = document.getElementById('orders-count-num');
    const filterLabel = document.getElementById('filter-status-label');
    const tableWrap = document.getElementById('orders-table-wrap');
    const actionModalEl = document.getElementById('order-action-modal');
    const actionModal = actionModalEl ? new bootstrap.Modal(actionModalEl) : null;

    if (!tbody) return;

    const U = window.SupUtils;
    let currentFilter = '';
    let currentPage = 1;

    const paymentLabels = {
        cash: 'Cash',
        gcash: 'GCash',
        bank_transfer: 'Bank Transfer',
        check: 'Check/PDC'
    };

    const filterLabels = {
        '': 'All Orders',
        active: 'Active Orders',
        pending: 'Pending',
        accepted: 'Accepted',
        shipped: 'Shipped',
        out_for_delivery: 'Out for Delivery',
        delivered: 'Delivered',
        fulfilled: 'Completed',
        declined: 'Declined',
        cancelled: 'Cancelled by Pharmacy'
    };

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show`;
        toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;';
        const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'x-circle' : 'info-circle';
        toast.innerHTML = `<i class="bi bi-${icon} me-2"></i>${U.escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function openOrderActionModal({ title, message, confirmText, confirmClass, onConfirm }) {
        if (!actionModalEl || !actionModal) {
            if (window.confirm(message)) {
                onConfirm();
            }
            return;
        }

        const modalTitleEl = document.getElementById('order-action-modal-title');
        const modalBodyEl = document.getElementById('order-action-modal-body');
        const modalConfirmBtn = document.getElementById('order-action-confirm-btn');

        if (!modalTitleEl || !modalBodyEl || !modalConfirmBtn) {
            if (window.confirm(message)) {
                onConfirm();
            }
            return;
        }

        modalTitleEl.textContent = title;
        modalBodyEl.textContent = message;
        modalConfirmBtn.textContent = confirmText;
        modalConfirmBtn.className = `btn ${confirmClass}`;

        const handleConfirm = () => {
            actionModal.hide();
            onConfirm();
        };

        modalConfirmBtn.onclick = handleConfirm;
        actionModal.show();
    }

    function getProgressInfo(order) {
        const status = order.status || 'pending';
        const delivery = order.delivery_status || '';

        if (status === 'declined') {
            return { label: 'Declined', css: 's-declined', hint: 'Order was declined' };
        }
        if (status === 'cancelled') {
            return { label: 'Cancelled by Pharmacy', css: 's-declined', hint: 'The pharmacy cancelled this order before it was accepted' };
        }
        if (status === 'fulfilled') {
            return { label: 'Completed', css: 's-fulfilled', hint: 'Admin confirmed receipt' };
        }
        if (status === 'pending') {
            return { label: 'Awaiting Response', css: 's-pending', hint: 'Accept or decline this order' };
        }
        if (delivery === 'delivered' && status === 'accepted') {
            return { label: 'Delivered — Awaiting Admin', css: 's-delivered', hint: 'Waiting for admin to confirm receipt' };
        }
        if (delivery === 'out_for_delivery') {
            return { label: 'Out for Delivery', css: 's-out_for_delivery', hint: 'Package is on the way' };
        }
        if (delivery === 'shipped') {
            return { label: 'Shipped', css: 's-shipped', hint: 'Order has been shipped' };
        }
        if (delivery === 'accepted' || status === 'accepted') {
            return { label: 'Preparing Order', css: 's-accepted', hint: 'Accepted — update delivery when ready' };
        }
        return { label: status.replace(/_/g, ' '), css: 's-' + status, hint: '' };
    }

    function renderActions(order) {
        const status = order.status || 'pending';
        const delivery = order.delivery_status || '';

        if (status === 'pending') {
            return `
                <button class="btn btn-sm btn-accept action-btn me-1" onclick="SupOrders.process(${order.id}, 'accept')">
                    <i class="bi bi-check-lg"></i> Accept
                </button>
                <button class="btn btn-sm btn-decline action-btn" onclick="SupOrders.process(${order.id}, 'decline')">
                    <i class="bi bi-x-lg"></i> Decline
                </button>`;
        }

        if (status === 'accepted' && delivery !== 'delivered') {
            const steps = [
                { val: 'shipped', label: 'Mark as Shipped' },
                { val: 'out_for_delivery', label: 'Out for Delivery' },
                { val: 'delivered', label: 'Mark as Delivered' }
            ];
            const rank = { accepted: 0, shipped: 1, out_for_delivery: 2, delivered: 3 };
            const currentRank = rank[delivery] ?? 0;

            let options = '<option value="" disabled selected>Update delivery...</option>';
            steps.forEach((step) => {
                const stepRank = rank[step.val];
                if (stepRank > currentRank) {
                    options += `<option value="${step.val}">${step.label}</option>`;
                }
            });

            if (currentRank >= 3) {
                return '<span class="text-muted small">No further updates</span>';
            }

            return `<select class="delivery-select" onchange="SupOrders.updateDelivery(${order.id}, this.value, this)">${options}</select>`;
        }

        if (delivery === 'delivered' && status === 'accepted') {
            return '<span class="status-badge s-delivered"><i class="bi bi-hourglass-split me-1"></i>Awaiting Admin</span>';
        }
        if (status === 'fulfilled') {
            return '<span class="status-badge s-fulfilled"><i class="bi bi-check2-all me-1"></i>Done</span>';
        }
        if (status === 'declined') {
            return '<span class="text-muted small"><i class="bi bi-x-circle me-1"></i>Declined</span>';
        }
        return '<span class="text-muted small">—</span>';
    }

    function renderOrderRow(order) {
        const progress = getProgressInfo(order);
        const payLabel = paymentLabels[order.payment_method] || order.payment_method || '—';
        const completedOn = order.fulfilled_date || order.delivered_at;
        const hintAttr = progress.hint ? ` title="${U.escapeHtml(progress.hint)}"` : '';

        return `
            <tr>
                <td><span class="badge bg-info">#${order.id}</span></td>
                <td>${U.escapeHtml(order.medicine_name || 'N/A')}</td>
                <td><strong>${order.quantity ?? '—'}</strong></td>
                <td class="text-success fw-bold">${U.formatCurrency(order.total_cost || order.total_amount || 0)}</td>
                <td${hintAttr}><span class="status-badge ${progress.css}">${U.escapeHtml(progress.label)}</span></td>
                <td>${U.formatDate(order.order_date)}</td>
                <td class="col-detail">${U.formatCurrency(order.unit_price || 0)}</td>
                <td class="col-detail"><span class="pay-badge">${U.escapeHtml(payLabel)}</span></td>
                <td class="col-detail">${U.formatDate(order.expected_delivery, 'ASAP')}</td>
                <td class="col-detail">${completedOn ? U.formatDateTime(completedOn) : '<span class="text-muted">Not yet</span>'}</td>
                <td>${renderActions(order)}</td>
            </tr>`;
    }

    function renderOrders(orders, pagination) {
        const total = pagination?.total_items ?? orders.length;
        if (ordersCountEl) ordersCountEl.textContent = total;

        if (!orders.length) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-5"><i class="bi bi-inbox display-4 d-block mb-2 opacity-50"></i>No orders found for this filter</td></tr>';
            U.renderPagination(paginationEl, pagination, currentPage, (page) => {
                currentPage = page;
                loadOrders();
            });
            return;
        }

        tbody.innerHTML = orders.map(renderOrderRow).join('');
        U.renderPagination(paginationEl, pagination, currentPage, (page) => {
            currentPage = page;
            loadOrders();
        });
    }

    function loadOrders() {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4"><div class="spinner-border text-success" role="status"></div><p class="mt-2 text-muted">Loading orders...</p></td></tr>';

        const params = new URLSearchParams({
            page: String(currentPage),
            limit: String(U.getRecordsPerPage())
        });
        if (currentFilter) params.set('status', currentFilter);

        fetch('api/sup_orders.php?' + params.toString(), { credentials: 'include' })
            .then((r) => r.json())
            .then((data) => {
                if (data.success) {
                    renderOrders(data.data || [], data.pagination);
                } else {
                    tbody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">${U.escapeHtml(data.message || 'Failed to load orders')}</td></tr>`;
                }
            })
            .catch((err) => {
                showToast('Failed to load orders: ' + err.message, 'danger');
                tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-danger">Network error</td></tr>';
            });
    }

    window.SupOrders = {
        process(orderId, action) {
            const verb = action === 'accept' ? 'accept' : 'decline';
            openOrderActionModal({
                title: action === 'accept' ? 'Accept Order' : 'Decline Order',
                message: `Are you sure you want to ${verb.toUpperCase()} Order #${orderId}?`,
                confirmText: action === 'accept' ? 'Accept Order' : 'Decline Order',
                confirmClass: action === 'accept' ? 'btn-success' : 'btn-danger',
                onConfirm: () => {
                    const formData = new FormData();
                    formData.append('action', action);
                    formData.append('order_id', orderId);

                    fetch('api/sup_orders.php', { method: 'POST', body: formData, credentials: 'include' })
                        .then((r) => r.json())
                        .then((data) => {
                            if (data.success) {
                                showToast(data.message || `Order ${verb}ed successfully`, 'success');
                                setTimeout(loadOrders, 600);
                            } else {
                                showToast(data.message || 'Action failed', 'danger');
                            }
                        })
                        .catch((err) => showToast('Error: ' + err.message, 'danger'));
                }
            });
        },

        updateDelivery(orderId, newStatus, selectEl) {
            if (!newStatus) return;

            const labels = {
                shipped: 'SHIPPED',
                out_for_delivery: 'OUT FOR DELIVERY',
                delivered: 'DELIVERED'
            };
            const labelText = labels[newStatus] || newStatus.toUpperCase();

            openOrderActionModal({
                title: 'Update Delivery Status',
                message: `Update Order #${orderId} to ${labelText}?`,
                confirmText: 'Confirm Update',
                confirmClass: 'btn-primary',
                onConfirm: () => {
                    selectEl.disabled = true;
                    const formData = new FormData();
                    formData.append('action', newStatus === 'delivered' ? 'deliver' : 'update_delivery');
                    formData.append('order_id', orderId);
                    formData.append('delivery_status', newStatus);

                    fetch('api/sup_orders.php', { method: 'POST', body: formData, credentials: 'include' })
                        .then((r) => r.json())
                        .then((data) => {
                            if (data.success) {
                                showToast(data.message || `Order #${orderId} updated`, 'success');
                                setTimeout(loadOrders, 600);
                            } else {
                                showToast(data.message || 'Update failed', 'danger');
                                selectEl.disabled = false;
                                selectEl.value = '';
                            }
                        })
                        .catch((err) => {
                            showToast('Error: ' + err.message, 'danger');
                            selectEl.disabled = false;
                        });
                }
            });
        },

        refresh: loadOrders
    };

    document.querySelectorAll('[data-order-filter]').forEach((item) => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            currentFilter = item.dataset.orderFilter;
            currentPage = 1;
            if (filterLabel) filterLabel.textContent = filterLabels[currentFilter] || 'All Orders';
            loadOrders();
        });
    });

    U.initExpandableTable('#orders-table-wrap', '#toggle-order-columns');
    window.loadOrders = loadOrders;
    loadOrders();
    setInterval(loadOrders, 30000);
});
