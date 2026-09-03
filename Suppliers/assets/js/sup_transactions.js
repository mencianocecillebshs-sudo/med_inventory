document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('transaction-table');
    const paginationEl = document.getElementById('transactions-pagination');
    const U = window.SupUtils;
    let lastLoadTime = null;
    let isLoading = false;
    let currentPage = 1;
    let refreshToastTimer = null;

    function showRefreshToast() {
        const container = document.getElementById('toast-container') || createToastContainer();
        const existingToast = container.querySelector('.refresh-toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = 'refresh-toast';
        toast.style.cssText = `
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 14px 18px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 260px;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 10px;
            animation: slideIn 0.25s ease-out;
        `;

        toast.innerHTML = `
            <i class="bi bi-arrow-clockwise" style="font-size: 18px;"></i>
            <span>Transactions refreshed</span>
        `;

        container.appendChild(toast);
        clearTimeout(refreshToastTimer);
        refreshToastTimer = setTimeout(() => toast.remove(), 2500);
    }

    // Escape HTML helper
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text || '').replace(/[&<>"']/g, m => map[m]);
    }

    function createToastContainer() {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    function updateTransactionsPagination(pagination) {
        if (U && paginationEl) {
            U.renderPagination(paginationEl, pagination, currentPage, (page) => {
                currentPage = page;
                loadTransactions();
            });
        }
    }

    function loadTransactions(silent = false, isRefresh = false) {
        if (isLoading) {
            console.log('Already loading transactions, skipping...');
            return;
        }
        
        isLoading = true;
        console.log('Loading sales transactions...');
        
        if (!silent) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2 mb-0">Loading sales transactions...</p>
                    </td>
                </tr>
            `;
        }
        
        const params = new URLSearchParams({
            page: String(currentPage),
            limit: String(U ? U.getRecordsPerPage() : (window.RECORDS_PER_PAGE || 25)),
            t: String(Date.now())
        });

        fetch('api/sup_transactions.php?' + params.toString(), { credentials: 'include' })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                isLoading = false;
                tbody.innerHTML = '';
                
                if (!data.success) {
                    console.error('API returned error:', data.error);
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-danger py-4">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error: ${escapeHtml(data.error || 'Failed to load sales transactions')}
                            </td>
                        </tr>
                    `;
                    updateTransactionsPagination(data.pagination || { total_items: 0, total_pages: 1, current_page: currentPage });
                    return;
                }
                
                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-3 mb-2"><strong>No sales transactions yet</strong></p>
                                <small>Sales appear after completing orders or walk-ins</small>
                            </td>
                        </tr>
                    `;
                    updateTransactionsPagination(data.pagination || { total_items: 0, total_pages: 1, current_page: currentPage });
                    return;
                }

                console.log(`Displaying ${data.data.length} sales transactions`);
                
                // Check for new transactions since last load
                const hasNewTransactions = lastLoadTime && data.timestamp > lastLoadTime;
                lastLoadTime = data.timestamp;
                
                data.data.forEach((transaction, index) => {
                    const row = document.createElement('tr');
                    const date = new Date(transaction.timestamp);
                    const formattedDate = U ? U.formatDate(transaction.timestamp) : date.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    const formattedTime = date.toLocaleTimeString('en-US', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: true
                    });
                    
                    // Determine order display
                    const orderDisplay = transaction.order_id 
                        ? `<strong class="text-primary">#${transaction.order_id}</strong>` 
                        : '<span class="badge bg-secondary">Walk-in</span>';
                    
                    // Format total cost
                    const lineTotal = parseFloat(transaction.total_cost || 0);
                    const unitPrice = lineTotal / (transaction.quantity || 1);
                    const fmt = U ? U.formatCurrency.bind(U) : (v) => '₱' + parseFloat(v || 0).toFixed(2);
                    
                    // Cashier display
                    const cashierDisplay = transaction.cashier_name 
                        ? escapeHtml(transaction.cashier_name) 
                        : '<span class="text-muted">Unknown</span>';
                    
                    // Highlight new rows (first 3 rows on update), especially for fulfillments
                    const isNew = hasNewTransactions && index < 3;
                    if (isNew) {
                        row.style.animation = 'highlightRow 2s ease-out';
                    }
                    
                    row.innerHTML = `
                        <td>${orderDisplay}</td>
                        <td>
                            <strong>${escapeHtml(transaction.medicine_name || 'Unknown')}</strong>
                        </td>
                        <td>
                            <span class="status-badge status-remove">
                                SALE
                            </span>
                        </td>
                        <td><strong class="text-primary">${transaction.quantity || 0}</strong></td>
                        <td>
                            <strong class="text-success">${fmt(lineTotal)}</strong>
                            <br>
                            <small class="text-muted">
                                (${fmt(unitPrice)}/unit)
                            </small>
                        </td>
                        <td>${cashierDisplay}</td>
                        <td>
                            <div><strong>${formattedDate}</strong></div>
                            <small class="text-muted">${formattedTime}</small>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                if (isRefresh) {
                    showRefreshToast();
                }

                updateTransactionsPagination(data.pagination);
            })
            .catch(err => {
                console.error('Error loading sales transactions:', err);
                isLoading = false;
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Failed to load sales transactions</strong>
                            <p class="mb-0 mt-2 small">Error: ${escapeHtml(err.message)}</p>
                            <p class="mb-0 mt-1 small text-muted">Please check console for details or refresh the page</p>
                        </td>
                    </tr>
                `;
            });
    }

    // Add highlight animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes highlightRow {
            0% {
                background-color: #fef3c7;
            }
            100% {
                background-color: transparent;
            }
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);

    // Load transactions on page load
    loadTransactions(false);

    // Auto-refresh every 10 seconds, but only show one refresh notification at a time
    setInterval(() => loadTransactions(true, true), 10000);
    
    // Listen for storage events from other tabs/windows (for cross-tab communication)
    window.addEventListener('storage', (e) => {
        if (e.key === 'transaction_update') {
            console.log('Transaction update detected from another tab');
            loadTransactions(true, true);
        }
    });
    
    // Add manual refresh button if it exists
    const refreshBtn = document.getElementById('refresh-transactions-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            loadTransactions(false, true);
        });
    }
    
    console.log('Sales transaction loader initialized with auto-refresh every 5 seconds');
});