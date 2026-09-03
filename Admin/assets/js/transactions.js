document.addEventListener('DOMContentLoaded', () => {
    const searchInput       = document.getElementById('search');
    const applyFilterBtn    = document.getElementById('apply-transaction-filter');
    const tableBody         = document.getElementById('transaction-table');
    const noDataRow         = document.getElementById('no-data-row');
    const sidebar           = document.getElementById('sidebar');
    const toggleButton      = document.getElementById('toggle-sidebar-mobile');
    const tableLoading      = document.getElementById('table-loading');
    const pagination        = document.getElementById('transaction-pagination');
    const pageInfo          = document.getElementById('transaction-page-info');
    const txnDetailModal    = new bootstrap.Modal(document.getElementById('txnDetailModal'));
    const txnDetailContent  = document.getElementById('txn-detail-content');

    let transactionRows = [];
    let transactionData = []; // parallel array to hold raw objects for modal
    let transactionPage = 1;
    const perPage = window.RECORDS_PER_PAGE || 10;

    // ── Sidebar ────────────────────────────────────────────────────────────
    if (toggleButton && sidebar) {
        if (localStorage.getItem('sidebar') === 'open') sidebar.classList.add('active');
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            localStorage.setItem('sidebar', sidebar.classList.contains('active') ? 'open' : 'closed');
        });
    }

    // ── Theme ──────────────────────────────────────────────────────────────
    if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    });

    // ── Toast ──────────────────────────────────────────────────────────────
    function showToast(message, type = 'success') {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0 shadow-lg`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
        container.appendChild(toast);
        new bootstrap.Toast(toast).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // ── Filters ────────────────────────────────────────────────────────────
    // Consolidated into a single search box (see api/transactions.php `search`
    // param) that matches medicine, user, pharmacist, purchase #, and notes —
    // no separate dropdowns to populate anymore.

    // ── Pagination ─────────────────────────────────────────────────────────
    function renderPagination(totalPages) {
        if (!pagination) return;
        pagination.innerHTML = '';
        if (totalPages <= 1) return;
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        const addPage = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
            li.addEventListener('click', e => {
                e.preventDefault();
                if (!disabled) { transactionPage = page; renderTransactionRows(); }
            });
            ul.appendChild(li);
        };
        addPage('&laquo;', transactionPage - 1, transactionPage === 1);
        for (let i = Math.max(1, transactionPage - 2); i <= Math.min(totalPages, transactionPage + 2); i++) {
            addPage(i, i, false, i === transactionPage);
        }
        addPage('&raquo;', transactionPage + 1, transactionPage === totalPages);
        pagination.appendChild(ul);
    }

    function renderTransactionRows() {
        tableBody.innerHTML = '';
        const totalPages = Math.max(1, Math.ceil(transactionRows.length / perPage));
        if (transactionPage > totalPages) transactionPage = totalPages;
        const start = (transactionPage - 1) * perPage;
        const rows  = transactionRows.slice(start, start + perPage);
        const itemHeader = document.getElementById('item-page-header');
        if (itemHeader) itemHeader.textContent = 'Item';
        if (pageInfo) {
            pageInfo.textContent = transactionRows.length
                ? `Showing ${start + 1}–${Math.min(start + perPage, transactionRows.length)} of ${transactionRows.length}`
                : 'No transactions';
        }
        if (rows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-5">No transactions found.</td></tr>';
            renderPagination(0);
            return;
        }
        rows.forEach(row => tableBody.appendChild(row));
        renderPagination(totalPages);
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    function actionBadge(action) {
        if (action === 'add')    return '<span class="status-badge action-add">Add</span>';
        if (action === 'remove') return '<span class="status-badge action-remove">Remove</span>';
        if (action === 'update') return '<span class="status-badge action-update">Update</span>';
        return `<span class="status-badge bg-secondary text-white">${action ?? '—'}</span>`;
    }

    function safe(val, maxLen = 30) {
        if (!val) return '<span class="text-muted">—</span>';
        const str = String(val);
        const preview = str.length > maxLen ? str.substring(0, maxLen) + '…' : str;
        return `<span title="${str.replace(/"/g, '&quot;')}">${preview}</span>`;
    }

    function esc(val) {
        if (!val) return '';
        return String(val).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Detail Modal ───────────────────────────────────────────────────────
    function openDetailModal(t) {
        const cost = parseFloat(t.total_cost);
        const costStr = (!isNaN(cost) && cost > 0) ? `₱${cost.toFixed(2)}` : null;

        function field(label, value, full = false) {
            const empty = !value;
            return `
                <div class="txn-detail-item${full ? ' txn-detail-full' : ''}">
                    <span class="txn-detail-label">${label}</span>
                    <span class="txn-detail-value${empty ? ' empty' : ''}">${empty ? '—' : esc(value)}</span>
                </div>`;
        }

        function actionField(action) {
            let badge = '';
            if (action === 'add')    badge = `<span class="status-badge action-add">Add</span>`;
            else if (action === 'remove') badge = `<span class="status-badge action-remove">Remove</span>`;
            else if (action === 'update') badge = `<span class="status-badge action-update">Update</span>`;
            else badge = `<span class="status-badge bg-secondary text-white">${esc(action) || '—'}</span>`;
            return `<div class="txn-detail-item">
                        <span class="txn-detail-label">Action</span>
                        <span class="txn-detail-value">${badge}</span>
                    </div>`;
        }

        const date = t.timestamp ? new Date(t.timestamp).toLocaleString() : null;

        txnDetailContent.innerHTML = `
            ${field('Item', t.medicine_name)}
            ${actionField(t.action)}
            ${field('Quantity', t.quantity)}
            ${field('Cost', costStr)}
            <hr class="txn-divider">
            ${field('User', t.username)}
            ${field('Pharmacist', t.pharmacist_name)}
            ${field('Purchase #', t.purchase_number)}
            <hr class="txn-divider">
            ${field('Reason', t.reason, false)}
            ${field('Notes', t.notes, true)}
            <hr class="txn-divider">
            ${field('Date & Time', date, true)}
        `;

        txnDetailModal.show();
    }

    // ── Load transactions ──────────────────────────────────────────────────
    let transactionsAbortController = null;

    function loadTransactions(search = '') {
        // Cancel any request that's still in flight so responses can't pile up
        if (transactionsAbortController) transactionsAbortController.abort();
        transactionsAbortController = new AbortController();

        tableLoading.classList.add('show');
        noDataRow?.classList.add('d-none');

        const url = `api/transactions.php?per_page=9999&search=${encodeURIComponent(search)}`;

        fetch(url, { credentials: 'same-origin', signal: transactionsAbortController.signal })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(t => {
                        throw new Error(`HTTP ${response.status}: ${t.substring(0, 300)}`);
                    });
                }
                return response.text();
            })
            .then(rawText => {
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (e) {
                    console.error('JSON parse failed. Raw response:\n', rawText.substring(0, 500));
                    throw new Error('Server returned invalid JSON. Check browser console for details.');
                }

                if (data.error) throw new Error(data.error);

                const transactions = data.data || [];
                transactionRows = [];
                transactionData = [];

                transactions.forEach((t, idx) => {
                    const cost = parseFloat(t.total_cost);
                    const costDisplay = (!isNaN(cost) && cost > 0)
                        ? `<span class="text-success fw-bold">₱${cost.toFixed(2)}</span>`
                        : '<span class="text-muted">—</span>';
                    const qtyDisplay = (t.quantity !== null && t.quantity !== undefined)
                        ? `<span class="badge bg-info">${t.quantity}</span>`
                        : '<span class="text-muted">—</span>';

                    transactionData.push(t);

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="fw-bold">${t.medicine_name || '<span class="text-muted fst-italic">—</span>'}</td>
                        <td>${actionBadge(t.action)}</td>
                        <td>${qtyDisplay}</td>
                        <td>${t.username || '<span class="text-muted">—</span>'}</td>
                        <td>${t.pharmacist_name   || '<span class="text-muted">—</span>'}</td>
                        <td>${t.purchase_number || '<span class="text-muted">—</span>'}</td>
                        <td>${safe(t.notes, 30)}</td>
                        <td>${costDisplay}</td>
                        <td>${new Date(t.timestamp).toLocaleString()}</td>
                        <td>
                            <button class="btn btn-outline-primary action-btn btn-view-txn" data-idx="${idx}">
                                <i class="bi bi-eye me-1"></i> View
                            </button>
                        </td>
                    `;
                    transactionRows.push(row);
                });

                transactionPage = 1;
                renderTransactionRows();
                tableLoading.classList.remove('show');
            })
            .catch(err => {
                if (err.name === 'AbortError') return; // superseded by a newer request, ignore
                console.error('loadTransactions error:', err);
                showToast('Failed to load transactions: ' + err.message, 'danger');
                tableLoading.classList.remove('show');
                tableBody.innerHTML = `<tr><td colspan="10" class="text-center text-danger py-4">
                    <i class="bi bi-exclamation-triangle me-2"></i>${err.message}
                </td></tr>`;
            });
    }

    // ── View button delegation ─────────────────────────────────────────────
    tableBody.addEventListener('click', e => {
        const btn = e.target.closest('.btn-view-txn');
        if (!btn) return;
        const idx = parseInt(btn.dataset.idx, 10);
        if (!isNaN(idx) && transactionData[idx]) {
            openDetailModal(transactionData[idx]);
        }
    });

    // ── Debounce / throttle ───────────────────────────────────────────────
    function debounce(fn, delay) {
        let timer;
        return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
    }

    function throttle(fn, interval) {
        let last = 0, pendingTimer = null;
        return (...args) => {
            const now = Date.now();
            const remaining = interval - (now - last);
            if (remaining <= 0) {
                last = now;
                fn(...args);
            } else if (!pendingTimer) {
                pendingTimer = setTimeout(() => {
                    last = Date.now();
                    pendingTimer = null;
                    fn(...args);
                }, remaining);
            }
        };
    }

    function triggerLoad() {
        loadTransactions(searchInput.value);
    }

    // ── Real-time refresh ────────────────────────────────────────────────
    // Throttled to at most once every 3s: if api/realtime.php ever fires
    // 'update' in a tight loop, this stops it from flooding the page with
    // overlapping full-table reloads and freezing the tab.
    const throttledTriggerLoad = throttle(triggerLoad, 3000);
    if (typeof EventSource !== 'undefined') {
        const source = new EventSource('api/realtime.php');
        source.addEventListener('update', throttledTriggerLoad);
        source.onerror = () => source.close();
    }

    searchInput.addEventListener('input', debounce(triggerLoad, 300));
    applyFilterBtn?.addEventListener('click', triggerLoad);
    searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); triggerLoad(); }
    });

    document.addEventListener('keydown', e => {
        if (e.ctrlKey && e.key === 'f') { e.preventDefault(); searchInput.focus(); }
    });

    // ── Init ───────────────────────────────────────────────────────────────
    loadTransactions();
});
