document.addEventListener('DOMContentLoaded', () => {
    const salesTableBody = document.getElementById('sales-table-body');
    const searchInput = document.getElementById('search-input');
    const filterPayment = document.getElementById('filter-payment');
    const createSaleForm = document.getElementById('create-sale-form');
    const saleMedicineContainer = document.getElementById('sale-medicine-container');
    const addMedicineBtn = document.getElementById('add-sale-medicine-row-btn');
    const orderDropdown = document.getElementById('order-dropdown');

    let medicineRowCounter = 0;
    let allMedicines = [];
    let currentOrderId = 0;
    let currentPage = 1;
    const U = window.SupUtils;
    const sym = () => U ? U.currencySymbol() : '₱';
    const fmt = (v) => U ? U.formatCurrency(v) : sym() + parseFloat(v || 0).toFixed(2);

    // Toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        toast.style.zIndex = '9999';
        toast.style.minWidth = '350px';
        toast.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // Safe JSON fetch wrapper
    function fetchJson(url, options = {}) {
        return fetch(url, options)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            });
    }

    function updateSalesPagination(pagination) {
        const paginationEl = document.getElementById('sales-pagination');
        if (U && paginationEl) {
            U.renderPagination(paginationEl, pagination, currentPage, (page) => {
                currentPage = page;
                loadSales();
            });
        }
    }

    function updateRevenueSummary(summary) {
        const totalEl = document.getElementById('sales-total-revenue');
        const profitEl = document.getElementById('sales-total-profit');
        const filteredEl = document.getElementById('sales-filtered-revenue');
        const countEl = document.getElementById('sales-total-count');
        const filteredCountEl = document.getElementById('sales-filtered-count');
        if (!totalEl || !profitEl || !countEl || !summary) return;

        totalEl.textContent = fmt(summary.total_revenue || 0);
        profitEl.textContent = fmt(summary.total_profit || 0);
        if (filteredEl) {
            filteredEl.textContent = fmt(summary.filtered_revenue ?? summary.total_revenue ?? 0);
        }
        countEl.textContent = String(summary.sale_count || 0);
        if (filteredCountEl) {
            const filteredCount = summary.filtered_count ?? summary.sale_count ?? 0;
            const totalCount = summary.sale_count || 0;
            filteredCountEl.textContent = filteredCount === totalCount
                ? `${filteredCount} sales`
                : `${filteredCount} of ${totalCount} shown`;
        }
    }

    // Load sales/invoices with pagination
    function loadSales() {
        const search = searchInput ? searchInput.value.trim() : '';
        const payment = filterPayment ? filterPayment.value : '';
        const params = new URLSearchParams({
            page: String(currentPage),
            limit: String(U ? U.getRecordsPerPage() : (window.RECORDS_PER_PAGE || 25))
        });
        if (search) params.set('search', search);
        if (payment) params.set('payment_method', payment);

        fetchJson('api/sup_sales.php?' + params.toString())
            .then(data => {
                if (data.success && data.data) {
                    renderSales(data.data);
                    updateSalesPagination(data.pagination);
                    updateRevenueSummary(data.summary);
                } else {
                    salesTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No sales found</td></tr>';
                    updateSalesPagination(data.pagination || { total_items: 0, total_pages: 1, current_page: currentPage });
                    updateRevenueSummary(data.summary || { total_revenue: 0, total_profit: 0, filtered_revenue: 0, sale_count: 0, filtered_count: 0 });
                }
            })
            .catch(error => {
                console.error('Error loading sales:', error);
                showToast('Failed to load sales', 'danger');
            });
    }

    // Render sales table
    function renderSales(sales) {
        if (!sales || sales.length === 0) {
            salesTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No sales found</td></tr>';
            return;
        }

        salesTableBody.innerHTML = sales.map(sale => {
            const paymentBadge = getPaymentBadge(sale.payment_method);
            const date = U ? U.formatDateTime(sale.created_at) : new Date(sale.created_at).toLocaleString();
            const customer = sale.customer_name || 'Walk-in Customer';
            
            return `
                <tr>
                    <td><strong>${escapeHtml(sale.invoice_number)}</strong></td>
                    <td>${escapeHtml(customer)}</td>
                    <td><span class="badge bg-info">${sale.item_count || 0} items</span></td>
                    <td>${paymentBadge}</td>
                    <td><strong>${fmt(sale.total_amount || 0)}</strong></td>
                    <td>${escapeHtml(sale.cashier_name || 'Unknown')}</td>
                    <td class="date-info">${date}</td>
                    <td class="actions-col">
                        <button class="btn btn-info btn-sm action-btn" onclick="viewInvoice(${sale.id})">
                            <i class="bi bi-eye"></i> View
                        </button>
                        <button class="btn btn-danger btn-sm action-btn" onclick="voidSale(${sale.id})">
                            <i class="bi bi-x-circle"></i> Void
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Get payment method badge
    function getPaymentBadge(method) {
        const badges = {
            'cash': '<span class="payment-badge payment-cash">Cash</span>',
            'credit_card': '<span class="payment-badge payment-credit-card">Credit Card</span>',
            'debit_card': '<span class="payment-badge payment-debit-card">Debit Card</span>',
            'gcash': '<span class="payment-badge payment-gcash">GCash</span>',
            'maya': '<span class="payment-badge payment-maya">Maya</span>',
            'insurance': '<span class="payment-badge payment-insurance">Insurance</span>'
        };
        return badges[method] || '<span class="payment-badge payment-cash">Cash</span>';
    }

    // Load medicines for selection
    function loadMedicines(search = '') {
        fetchJson(`api/sup_sales.php?action=get_medicines&search=${encodeURIComponent(search)}`)
            .then(data => {
                if (data.success && data.data) {
                    allMedicines = data.data;
                    updateMedicineDatalist();
                } else {
                    allMedicines = [];
                }
            })
            .catch(error => {
                console.error('Error loading medicines:', error);
                allMedicines = [];
            });
    }

    // Update datalist for medicine autocomplete
    function updateMedicineDatalist() {
        const datalist = document.getElementById('medicine-list');
        if (datalist) {
            datalist.innerHTML = allMedicines.map(med => `<option value="${escapeHtml(med.name)}" data-id="${med.id}" data-price="${med.selling_price || 0}">`).join('');
        }
    }

    // Load accepted orders for dropdown
    function loadAcceptedOrders() {
        return fetchJson('api/sup_sales.php?action=get_accepted_orders')
            .then(data => {
                if (data.success && data.data) {
                    orderDropdown.innerHTML = '<option value="">Walk-in Customer</option>' + 
                        data.data.map(order => `<option value="${order.id}">#${order.id} - ${escapeHtml(order.ordered_by || 'Unknown')} - Total: ${fmt(order.total_amount)} (${new Date(order.order_date).toLocaleDateString()})</option>`).join('');
                } else {
                    orderDropdown.innerHTML = '<option value="">No accepted orders</option>';
                }
            })
            .catch(error => {
                console.error('Error loading orders:', error);
                showToast('Failed to load orders', 'danger');
            });
    }

    // Populate form with order details
    function populateOrderDetails(orderId) {
        const orderInfoDiv = document.getElementById('order-info');
        if (!orderId) {
            currentOrderId = 0;
            saleMedicineContainer.innerHTML = '';
            medicineRowCounter = 0;
            addMedicineRow(); // Add empty row for walk-in
            calculateTotals();
            if (orderInfoDiv) orderInfoDiv.style.display = 'none';
            return;
        }

        fetchJson(`api/sup_sales.php?action=get_order_details&order_id=${orderId}`)
            .then(data => {
                if (data.success && data.data) {
                    currentOrderId = orderId;
                    saleMedicineContainer.innerHTML = '';
                    medicineRowCounter = 0;
                    data.data.items.forEach((item, index) => {
                        addMedicineRowFromOrder(item, index + 1);
                    });
                    // Force recalculation after DOM update
                    setTimeout(calculateTotals, 0);
                    // Display additional order info
                    if (orderInfoDiv) {
                        orderInfoDiv.innerHTML = `
                            <strong>Order Details:</strong><br>
                            Customer: ${escapeHtml(data.data.customer_name)}<br>
                            Order Date: ${new Date(data.data.order_date).toLocaleString()}<br>
                            Expected Delivery: ${data.data.expected_delivery ? new Date(data.data.expected_delivery).toLocaleDateString() : 'N/A'}
                        `;
                        orderInfoDiv.style.display = 'block';
                    }
                    showToast(`Loaded ${data.data.items.length} items from Order #${orderId}. Total: ${fmt(data.data.total)}`, 'info');
                } else {
                    showToast('Failed to load order details', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to load order details', 'danger');
            });
    }

    // Add medicine row from order
    function addMedicineRowFromOrder(item, rowNum) {
        medicineRowCounter++;
        const row = document.createElement('div');
        row.className = 'medicine-row';
        row.id = `medicine-row-${medicineRowCounter}`;
        
        row.innerHTML = `
            <button type="button" class="remove-row" onclick="removeMedicineRow(${medicineRowCounter})" style="display: none;"> <!-- Hidden for order fulfillment -->
                <i class="bi bi-x"></i>
            </button>
            <div class="medicine-row-header">Medicine #${rowNum} (Order Item)</div>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Medicine</label>
                    <input type="text" class="form-control" value="${escapeHtml(item.medicine_name)}" readonly>
                    <input type="hidden" class="medicine-id" data-row="${medicineRowCounter}" value="${item.medicine_id}">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control medicine-quantity" value="${item.quantity}" readonly>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Selling Price</label>
                    <div class="input-group">
                        <span class="input-group-text">${sym()}</span>
                        <input type="number" class="form-control medicine-price" value="${parseFloat(item.unit_price).toFixed(2)}" readonly aria-label="Selling price">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label">Line Total</label>
                    <div class="input-group">
                        <span class="input-group-text">${sym()}</span>
                        <input type="number" class="form-control medicine-line-total" value="${parseFloat(item.line_total).toFixed(2)}" readonly>
                    </div>
                </div>
            </div>
        `;
        saleMedicineContainer.appendChild(row);
    }

    // Add medicine row for walk-in
    function addMedicineRow() {
        medicineRowCounter++;
        const row = document.createElement('div');
        row.className = 'medicine-row';
        row.id = `medicine-row-${medicineRowCounter}`;
        
        row.innerHTML = `
            <button type="button" class="remove-row" onclick="removeMedicineRow(${medicineRowCounter})">
                <i class="bi bi-x"></i>
            </button>
            <div class="medicine-row-header">Medicine #${medicineRowCounter}</div>
            <div class="row">
                <div class="col-md-6 mb-2">
                    <label class="form-label">Medicine</label>
                    <input type="text" class="form-control medicine-name" list="medicine-list" data-row="${medicineRowCounter}" required placeholder="Search medicine...">
                    <datalist id="medicine-list"></datalist>
                    <input type="hidden" class="medicine-id" data-row="${medicineRowCounter}">
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control medicine-quantity" data-row="${medicineRowCounter}" min="1" value="1" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Selling Price</label>
                    <div class="input-group">
                        <span class="input-group-text">${sym()}</span>
                        <input type="number" class="form-control medicine-price" data-row="${medicineRowCounter}" min="0" step="0.01" value="0.00" required aria-label="Selling price">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <label class="form-label">Line Total</label>
                    <div class="input-group">
                        <span class="input-group-text">${sym()}</span>
                        <input type="number" class="form-control medicine-line-total" data-row="${medicineRowCounter}" readonly>
                    </div>
                </div>
            </div>
        `;
        saleMedicineContainer.appendChild(row);

        // Add event listeners for this row
        const nameInput = row.querySelector('.medicine-name');
        const qtyInput = row.querySelector('.medicine-quantity');
        const priceInput = row.querySelector('.medicine-price');
        const lineTotalInput = row.querySelector('.medicine-line-total');
        const hiddenId = row.querySelector('.medicine-id');

        nameInput.addEventListener('input', function() {
            const selected = allMedicines.find(med => med.name.toLowerCase() === this.value.toLowerCase());
            if (selected) {
                hiddenId.value = selected.id;
                priceInput.value = selected.selling_price || 0;
                updateLineTotal(qtyInput, priceInput, lineTotalInput);
            } else {
                hiddenId.value = '';
                priceInput.value = 0;
                updateLineTotal(qtyInput, priceInput, lineTotalInput);
            }
        });

        [qtyInput, priceInput].forEach(input => {
            input.addEventListener('input', () => updateLineTotal(qtyInput, priceInput, lineTotalInput));
        });
    }

    // Update line total for a row
    function updateLineTotal(qtyInput, priceInput, lineTotalInput) {
        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        lineTotalInput.value = (qty * price).toFixed(2);
        calculateTotals();
    }

    // Remove medicine row
    window.removeMedicineRow = function(rowNum) {
        const row = document.getElementById(`medicine-row-${rowNum}`);
        if (row) {
            row.remove();
            calculateTotals();
        }
    };

    // Calculate totals
    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('.medicine-row .medicine-line-total').forEach(input => {
            subtotal += parseFloat(input.value) || 0;
        });

        const discountInput = document.getElementById('sale-discount');
        const taxInput = document.getElementById('sale-tax');
        const discount = parseFloat(discountInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;

        const discountAmount = (subtotal * discount) / 100;
        const subtotalAfterDiscount = subtotal - discountAmount;
        const taxAmount = (subtotalAfterDiscount * tax) / 100;
        const total = subtotalAfterDiscount + taxAmount;

        const subtotalInput = document.getElementById('sale-subtotal');
        const totalDisplay = document.getElementById('sale-total-amount-display');
        const totalInput = document.getElementById('sale-total-amount');

        if (subtotalInput) subtotalInput.value = subtotal.toFixed(2);
        if (totalDisplay) totalDisplay.textContent = total.toFixed(2);
        if (totalInput) totalInput.value = total.toFixed(2);
        calculateChange();
    }

    // Calculate change
    function calculateChange() {
        const amountPaid = parseFloat(document.getElementById('sale-amount-paid').value) || 0;
        const total = parseFloat(document.getElementById('sale-total-amount').value) || 0;
        const changeGiven = document.getElementById('sale-change-given');
        if (changeGiven) changeGiven.value = (amountPaid - total).toFixed(2);
    }

    // Form submit
    if (createSaleForm) {
        createSaleForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const medicines = [];
            document.querySelectorAll('.medicine-row').forEach(row => {
                const medicineId = row.querySelector('.medicine-id').value;
                const quantity = parseInt(row.querySelector('.medicine-quantity').value);
                const price = parseFloat(row.querySelector('.medicine-price').value);
                if (medicineId && quantity > 0 && price >= 0) {
                    medicines.push({ medicine_id: parseInt(medicineId), quantity, selling_price: price });
                }
            });

            if (medicines.length === 0) {
                showToast('At least one medicine is required.', 'danger');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'create_sale');
            formData.append('order_id', currentOrderId);
            formData.append('medicines', JSON.stringify(medicines));
            formData.append('payment_method', document.getElementById('sale-payment-method').value);
            formData.append('amount_paid', document.getElementById('sale-amount-paid').value);
            formData.append('discount', document.getElementById('sale-discount').value);
            formData.append('tax', document.getElementById('sale-tax').value);

            fetch('api/sup_sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    createSaleForm.reset();
                    saleMedicineContainer.innerHTML = '';
                    medicineRowCounter = 0;
                    addMedicineRow();
                    calculateTotals();
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createSaleModal'));
                    if (modal) modal.hide();
                    loadSales();
                } else {
                    showToast(data.errors.join('<br>'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
        });
    }

    // Order dropdown change
    if (orderDropdown) {
        orderDropdown.addEventListener('change', (e) => {
            const orderId = parseInt(e.target.value);
            populateOrderDetails(orderId);
        });
    }

    // View invoice - FIXED: Populate existing elements instead of overwriting
    window.viewInvoice = function(invoiceId) {
        fetchJson(`api/sup_sales.php?action=get_invoice&invoice_id=${invoiceId}`)
            .then(data => {
                if (data.success && data.data) {
                    const invoice = data.data;
                    const discountAmount = (parseFloat(invoice.subtotal) * parseFloat(invoice.discount)) / 100;
                    const taxAmount = ((parseFloat(invoice.subtotal) - discountAmount) * parseFloat(invoice.tax)) / 100;

                    // Populate header
                    document.getElementById('view-invoice-number').textContent = invoice.invoice_number;

                    // Populate details
                    document.getElementById('view-customer').textContent = invoice.customer_name || 'Walk-in Customer';
                    document.getElementById('view-payment-method').innerHTML = getPaymentBadge(invoice.payment_method);
                    document.getElementById('view-cashier').textContent = invoice.cashier_name || 'Unknown';
                    document.getElementById('view-date').textContent = new Date(invoice.created_at).toLocaleString();

                    // Populate items
                    const tbody = document.getElementById('view-items-body');
                    tbody.innerHTML = invoice.items.map(item => `
                        <tr>
                            <td>${escapeHtml(item.medicine_name)}</td>
                            <td>${item.quantity}</td>
                            <td>${fmt(item.selling_price)}</td>
                            <td>${fmt(item.line_total)}</td>
                        </tr>
                    `).join('');

                    // Populate summary
                    document.getElementById('view-subtotal').textContent = fmt(invoice.subtotal);
                    document.getElementById('view-discount').textContent = `${fmt(discountAmount)} (${invoice.discount}%)`;
                    document.getElementById('view-tax').textContent = `${fmt(taxAmount)} (${invoice.tax}%)`;
                    document.getElementById('view-total').textContent = fmt(invoice.total_amount);
                    document.getElementById('view-paid').textContent = fmt(invoice.amount_paid);
                    document.getElementById('view-change').textContent = fmt(invoice.change_given);

                    new bootstrap.Modal(document.getElementById('viewInvoiceModal')).show();
                } else {
                    showToast('Failed to load invoice', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to load invoice', 'danger');
            });
    };

    // Void sale
    window.voidSale = function(invoiceId) {
        if (confirm('Are you sure you want to void this sale? This will reverse the stock.')) {
            const formData = new FormData();
            formData.append('action', 'void_sale');
            formData.append('invoice_id', invoiceId);

            fetch('api/sup_sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadSales();
                } else {
                    showToast(data.errors.join('<br>'), 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to void sale', 'danger');
            });
        }
    };

    // Print invoice
    document.getElementById('print-invoice-btn')?.addEventListener('click', () => {
        window.print();
    });

    // Filter and search (server-side via API)
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            currentPage = 1;
            loadSales();
        }, 300));
    }

    if (filterPayment) {
        filterPayment.addEventListener('change', () => {
            currentPage = 1;
            loadSales();
        });
    }

    // Add medicine button
    if (addMedicineBtn) {
        addMedicineBtn.addEventListener('click', () => {
            if (currentOrderId === 0) {
                addMedicineRow();
            } else {
                showToast('Cannot add items to order fulfillment', 'warning');
            }
        });
    }

    // Discount and tax change handlers
    document.getElementById('sale-discount')?.addEventListener('input', calculateTotals);
    document.getElementById('sale-tax')?.addEventListener('input', calculateTotals);
    document.getElementById('sale-amount-paid')?.addEventListener('input', calculateChange);

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Modal event - load medicines and orders when opened
    document.getElementById('createSaleModal')?.addEventListener('show.bs.modal', () => {
        loadMedicines();
        loadAcceptedOrders();
        if (saleMedicineContainer.children.length === 0) {
            addMedicineRow();
        }
        currentOrderId = 0; // Reset
    });

    // Initialize
    loadSales();
    loadAcceptedOrders().then(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const orderId = urlParams.get('order_id');
        const fromAccept = urlParams.get('from_accept');
        if (orderId && fromAccept && orderDropdown) {
            orderDropdown.value = orderId;
            populateOrderDetails(orderId);
            const modal = new bootstrap.Modal(document.getElementById('createSaleModal'));
            modal.show();
        }
    });
});