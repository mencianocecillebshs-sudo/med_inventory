document.addEventListener('DOMContentLoaded', () => {
    // Initialize elements
    const tbody = document.getElementById('sales-table');
    const filterPayment = document.getElementById('filter-payment');
    const searchInput = document.getElementById('search-input');
    const pagination = document.getElementById('sales-pagination');
    const pageInfo = document.getElementById('sales-page-info');
    const medicineContainer = document.getElementById('sale-medicine-container');
    const addMedicineRowBtn = document.getElementById('add-sale-medicine-row-btn');
    const subtotalInput = document.getElementById('sale-subtotal');
    const discountInput = document.getElementById('sale-discount');
    const taxInput = document.getElementById('sale-tax');
    const totalAmountInput = document.getElementById('sale-total-amount');
    const amountPaidInput = document.getElementById('sale-amount-paid');
    const changeGivenInput = document.getElementById('sale-change-given');
    const createSaleModalElement = document.getElementById('createSaleModal');
    const viewInvoiceModalElement = document.getElementById('viewInvoiceModal');
    const voidSaleModalElement = document.getElementById('voidSaleModal');
    const confirmVoidSaleBtn = document.getElementById('confirm-void-sale-btn');
    let createSaleModal, viewInvoiceModal, voidSaleModal;
    let pendingVoidInvoiceId = null;
    let allInvoices = [];
    let salesSummary = { total_revenue: 0, sale_count: 0, total_profit: 0 };
    let salesPage = 1;
    const perPage = window.RECORDS_PER_PAGE || 10;

    // Toast Notification System
    function createToastContainer() {
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
    }

    function showToast(message, type = 'success') {
        createToastContainer();
        const container = document.getElementById('toast-container');
        
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        
        const icons = {
            success: '<i class="bi bi-check-circle-fill"></i>',
            error: '<i class="bi bi-x-circle-fill"></i>',
            warning: '<i class="bi bi-exclamation-triangle-fill"></i>',
            info: '<i class="bi bi-info-circle-fill"></i>'
        };
        
        const colors = {
            success: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
            error: 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)',
            warning: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            info: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)'
        };
        
        toast.style.cssText = `
            background: ${colors[type]};
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease-out;
            min-width: 300px;
            font-weight: 500;
            font-size: 14px;
        `;
        
        toast.innerHTML = `
            <span style="font-size: 20px;">${icons[type]}</span>
            <span style="flex: 1;">${message}</span>
            <button onclick="this.parentElement.remove()" style="
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                transition: background 0.2s;
            " onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">×</button>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Initialize modals
    if (createSaleModalElement && typeof bootstrap !== 'undefined') {
        createSaleModal = new bootstrap.Modal(createSaleModalElement, { backdrop: 'static', keyboard: true });
        createSaleModalElement.addEventListener('hidden.bs.modal', () => {
            document.getElementById('create-sale-form').reset();
            document.getElementById('create-sale-form').classList.remove('was-validated');
            medicineContainer.innerHTML = '';
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '';
        });
    }
    if (viewInvoiceModalElement && typeof bootstrap !== 'undefined') {
        viewInvoiceModal = new bootstrap.Modal(viewInvoiceModalElement, { backdrop: 'static', keyboard: true });
        viewInvoiceModalElement.addEventListener('hidden.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '';
        });
    }
    if (voidSaleModalElement && typeof bootstrap !== 'undefined') {
        voidSaleModal = new bootstrap.Modal(voidSaleModalElement, { backdrop: 'static', keyboard: true });
    }

    // Escape HTML
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatMoney(amount) {
        return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Robust JSON parsing: strip BOM and trim before parsing
    function safeJsonParse(text) {
        try {
            if (typeof text !== 'string') return null;
            const cleaned = text.replace(/^\uFEFF/, '').trim();
            return JSON.parse(cleaned);
        } catch (e) {
            console.error('safeJsonParse failed:', e, 'raw:', text && text.slice ? text.slice(0,120) : text);
            throw e;
        }
    }

    function updateRevenueSummary(filtered) {
        const totalEl = document.getElementById('sales-total-revenue');
        const filteredEl = document.getElementById('sales-filtered-revenue');
        const countEl = document.getElementById('sales-total-count');
        const filteredCountEl = document.getElementById('sales-filtered-count');
        const totalProfitEl = document.getElementById('sales-total-profit');
        const filteredProfitMetaEl = document.getElementById('sales-filtered-profit-meta');
        if (!totalEl) return;

        const totalRevenue = salesSummary.total_revenue || allInvoices.reduce((sum, inv) => sum + parseFloat(inv.total_amount || 0), 0);
        const filteredRevenue = filtered.reduce((sum, inv) => sum + parseFloat(inv.total_amount || 0), 0);
        const totalCount = salesSummary.sale_count || allInvoices.length;
        const totalProfit = salesSummary.total_profit || allInvoices.reduce((sum, inv) => sum + parseFloat(inv.invoice_profit || 0), 0);
        const netProfit = salesSummary.net_profit ?? totalProfit;

        totalEl.textContent = formatMoney(totalRevenue);
        filteredEl.textContent = formatMoney(filteredRevenue);
        countEl.textContent = String(totalCount);
        if (totalProfitEl) totalProfitEl.textContent = formatMoney(netProfit);
        if (filteredProfitMetaEl) filteredProfitMetaEl.textContent = 'Based on completed customer sales';
        if (filteredCountEl) {
            filteredCountEl.textContent = filtered.length === totalCount
                ? `${filtered.length} sales`
                : `${filtered.length} of ${totalCount} shown`;
        }
    }

    function filteredInvoices() {
        const payment = filterPayment.value;
        const search = (searchInput.value || '').toLowerCase();
        return allInvoices.filter(invoice => {
            const text = `${invoice.invoice_number || ''} ${invoice.purchase_number || ''} ${invoice.cashier_name || ''} ${invoice.payment_method || ''}`.toLowerCase();
            return (!payment || invoice.payment_method === payment) && (!search || text.includes(search));
        });
    }

    function renderSalesPagination(totalPages) {
        if (!pagination) return;
        pagination.innerHTML = '';
        if (totalPages <= 1) return;
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        const add = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
            li.addEventListener('click', e => {
                e.preventDefault();
                if (!disabled) {
                    salesPage = page;
                    renderSales();
                }
            });
            ul.appendChild(li);
        };
        add('&laquo;', salesPage - 1, salesPage === 1);
        for (let i = Math.max(1, salesPage - 2); i <= Math.min(totalPages, salesPage + 2); i++) add(i, i, false, i === salesPage);
        add('&raquo;', salesPage + 1, salesPage === totalPages);
        pagination.appendChild(ul);
    }

    function renderSales() {
        const filtered = filteredInvoices();
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (salesPage > totalPages) salesPage = totalPages;
        const start = (salesPage - 1) * perPage;
        const rows = filtered.slice(start, start + perPage);
        tbody.innerHTML = '';
        if (pageInfo) pageInfo.textContent = filtered.length ? `Showing ${start + 1}-${Math.min(start + perPage, filtered.length)} of ${filtered.length}` : 'No sales';
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center">No sales found.</td></tr>';
            renderSalesPagination(0);
            updateRevenueSummary(filtered);
            return;
        }
        rows.forEach(invoice => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${escapeHtml(invoice.invoice_number)}</td>
                <td>${invoice.purchase_number ? escapeHtml(invoice.purchase_number) : '<span class="text-muted">Walk-in</span>'}</td>
                <td>${invoice.item_count || 0}</td>
                <td>PHP ${parseFloat(invoice.total_amount || 0).toFixed(2)}</td>
                <td><span class="payment-badge payment-${escapeHtml(invoice.payment_method.toLowerCase().replace('_', '-'))}">${escapeHtml(invoice.payment_method.replace('_', ' ').toUpperCase())}</span></td>
                <td>${escapeHtml(invoice.cashier_name || 'Unknown')}</td>
                <td class="date-info">${escapeHtml(invoice.created_at)}</td>
                <td class="actions-col">
                    <button class="btn btn-sm btn-info action-btn view-btn" data-invoice-id="${invoice.id}"><i class="bi bi-eye"></i> View</button>
                    <button class="btn btn-sm btn-danger action-btn void-btn" data-invoice-id="${invoice.id}"><i class="bi bi-x-circle"></i> Void</button>
                </td>
            `;
            tbody.appendChild(row);
        });
        renderSalesPagination(totalPages);
        updateRevenueSummary(filtered);
    }

    // Load sales/invoices
    function loadSales() {
        const monthFilterEl = document.getElementById('sales-month-filter');
        const month = monthFilterEl ? monthFilterEl.value : '';
        const url = 'api/phar_sales.php' + (month ? '?month=' + encodeURIComponent(month) : '');
        fetch(url, { method: 'GET' })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    tbody.innerHTML = '';
                    if (data.success) {
                        allInvoices = data.data || [];
                        salesSummary = data.summary || salesSummary;
                        salesPage = 1;
                        renderSales();
                        populateMonthFilterOptions();
                    } else {
                        showToast('Error loading sales: ' + (data.errors ? data.errors.join(', ') : 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('Failed to parse JSON:', text);
                    showToast('Error: Invalid response from server', 'error');
                }
            })
            .catch(error => {
                showToast('Error loading sales: ' + error.message, 'error');
                console.error('Error:', error);
            });
    }

    // Load medicines with search
    let searchTimeout;
    function loadMedicines(selectElement, searchTerm = '') {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const url = searchTerm 
                ? `api/phar_sales.php?action=get_medicines&search=${encodeURIComponent(searchTerm)}`
                : 'api/phar_sales.php?action=get_medicines';
                
            fetch(url)
                .then(response => response.text())
                .then(text => {
                    try {
                    const data = safeJsonParse(text);
                    if (data && data.success) {
                            const currentValue = selectElement.value;
                            selectElement.innerHTML = '<option value="">Select Medicine</option>';
                            data.data.forEach(med => {
                                const option = document.createElement('option');
                                option.value = med.id;
                                option.textContent = `${med.name} (Stock: ${med.quantity}) - Sell ₱${parseFloat(med.selling_price).toFixed(2)}`;
                                option.dataset.price = med.selling_price;
                                option.dataset.buyingPrice = med.buying_price;
                                option.dataset.stock = med.quantity;
                                if (med.id === currentValue) option.selected = true;
                                selectElement.appendChild(option);
                            });
                        }
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                    }
                })
                .catch(error => console.error('Error:', error));
        }, 300);
    }

    // Add medicine row to sale. Pass `locked` (from a selected pending purchase) to pre-fill
    // and lock the medicine/quantity instead of leaving them free-text — this mirrors the
    // exact-match check create_sale performs server-side, so the form can't submit something
    // the backend would reject anyway.
    function addSaleMedicineRow(locked = null) {
        const medicineCount = medicineContainer.children.length + 1;
        const row = document.createElement('div');
        row.className = 'medicine-row';
        row.innerHTML = `
            <div class="medicine-row-header">Item ${medicineCount}</div>
            <button type="button" class="remove-row" aria-label="Remove"><i class="bi bi-x"></i></button>
            <div class="row">
                <div class="col-md-5 mb-2">
                    <label class="form-label">Medicine <span class="text-danger">*</span></label>
                    <select class="form-select medicine-select" name="medicine_id[]" required>
                        <option value="">Select Medicine</option>
                    </select>
                    <small class="text-muted stock-info"></small>
                    <div class="invalid-feedback">Please select a medicine.</div>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Qty <span class="text-danger">*</span></label>
                    <input type="number" class="form-control quantity" name="quantity[]" min="1" required>
                    <div class="invalid-feedback">Quantity required.</div>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label">Selling Price <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" class="form-control unit-price" name="selling_price[]" min="0.01" required>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label">Total</label>
                    <input type="text" class="form-control row-total" readonly>
                </div>
            </div>
        `;
        medicineContainer.appendChild(row);

        const select = row.querySelector('.medicine-select');
        const qtyInput = row.querySelector('.quantity');
        select.disabled = false;
        if (qtyInput) qtyInput.readOnly = false;

        const isValidLockedPurchase = locked && locked.medicine_id && locked.quantity !== undefined && locked.quantity !== null;
        if (isValidLockedPurchase) {
            // This item is tied to a pending purchase — show only that medicine, already
            // selected, and lock the quantity so the sale can't drift from what was ordered.
            const option = document.createElement('option');
            option.value = locked.medicine_id;
            option.textContent = locked.medicine_name;
            option.dataset.price = locked.selling_price;
            option.dataset.buyingPrice = locked.buying_price || 0;
            option.selected = true;
            select.appendChild(option);
            select.disabled = true;

            const qtyInput = row.querySelector('.quantity');
            qtyInput.value = locked.quantity;
            qtyInput.max = locked.quantity;
            qtyInput.readOnly = true;

            row.querySelector('.unit-price').value = parseFloat(locked.selling_price || 0).toFixed(2);
            row.querySelector('.stock-info').textContent = `Buying: ₱${parseFloat(locked.buying_price || 0).toFixed(2)} / unit · quantity fixed at ${locked.quantity}`;
            row.querySelector('.remove-row').remove();

            calculateRowTotal({ target: row });
            return row;
        }

        loadMedicines(select);

        select.addEventListener('change', (e) => {
            const option = e.target.selectedOptions[0];
            if (option.value) {
                row.querySelector('.unit-price').value = parseFloat(option.dataset.price).toFixed(2);
                row.querySelector('.stock-info').textContent = `Buying: ₱${parseFloat(option.dataset.buyingPrice || 0).toFixed(2)} / unit · Available: ${option.dataset.stock}`;
                row.querySelector('.quantity').max = option.dataset.stock;
            } else {
                row.querySelector('.unit-price').value = '';
                row.querySelector('.stock-info').textContent = '';
            }
            calculateRowTotal({ target: row });
        });

        row.querySelector('.quantity').addEventListener('input', calculateRowTotal);
        row.querySelector('.unit-price').addEventListener('input', calculateRowTotal);
        row.querySelector('.remove-row').addEventListener('click', () => {
            row.remove();
            updateMedicineNumbers();
            calculateSaleTotals();
        });
    }

    function updateMedicineNumbers() {
        const rows = medicineContainer.querySelectorAll('.medicine-row');
        rows.forEach((row, index) => {
            const header = row.querySelector('.medicine-row-header');
            if (header) {
                header.textContent = `Item ${index + 1}`;
            }
        });
    }

    function calculateRowTotal(e) {
        const row = e.target.closest('.medicine-row');
        const qty = parseInt(row.querySelector('.quantity').value) || 0;
        const price = parseFloat(row.querySelector('.unit-price').value) || 0;
        const total = qty * price;
        row.querySelector('.row-total').value = '₱' + total.toFixed(2);
        calculateSaleTotals();
    }

    function calculateSaleTotals() {
        let subtotal = 0;
        document.querySelectorAll('.medicine-row').forEach(row => {
            const qty = parseInt(row.querySelector('.quantity').value) || 0;
            const price = parseFloat(row.querySelector('.unit-price').value) || 0;
            subtotal += qty * price;
        });

        const discount = parseFloat(discountInput.value) || 0;
        const tax = parseFloat(taxInput.value) || 0;

        const discountAmount = (subtotal * discount) / 100;
        const subtotalAfterDiscount = subtotal - discountAmount;
        const taxAmount = (subtotalAfterDiscount * tax) / 100;
        const totalAmount = subtotalAfterDiscount + taxAmount;

        subtotalInput.value = subtotal.toFixed(2);
        totalAmountInput.value = totalAmount.toFixed(2);

        calculateChange();
    }

    function calculateChange() {
        const totalAmount = parseFloat(totalAmountInput.value) || 0;
        const amountPaid = parseFloat(amountPaidInput.value) || 0;
        const change = amountPaid - totalAmount;
        changeGivenInput.value = change >= 0 ? change.toFixed(2) : '0.00';
        
        if (change < 0) {
            changeGivenInput.classList.add('text-danger');
            changeGivenInput.classList.remove('text-success');
        } else {
            changeGivenInput.classList.remove('text-danger');
            changeGivenInput.classList.add('text-success');
        }
    }

    // Event listeners
    addMedicineRowBtn.addEventListener('click', addSaleMedicineRow);
    discountInput.addEventListener('input', calculateSaleTotals);
    taxInput.addEventListener('input', calculateSaleTotals);
    amountPaidInput.addEventListener('input', calculateChange);
    filterPayment.addEventListener('change', () => {
        salesPage = 1;
        renderSales();
    });

    // Search functionality
    searchInput.addEventListener('input', (e) => {
        salesPage = 1;
        renderSales();
    });

    // Create sale form submission
    document.getElementById('create-sale-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const form = e.target;
        form.classList.add('was-validated');
        
        if (!form.checkValidity()) {
            e.stopPropagation();
            return;
        }

        const rows = medicineContainer.querySelectorAll('.medicine-row');
        if (rows.length === 0) {
            showToast('Please add at least one medicine to the sale', 'warning');
            return;
        }

        const medicines = [];
        let valid = true;

        rows.forEach(row => {
            const medicineId = row.querySelector('.medicine-select').value;
            const quantity = parseInt(row.querySelector('.quantity').value) || 0;
            const sellingPrice = parseFloat(row.querySelector('.unit-price').value) || 0;
            const maxStock = parseInt(row.querySelector('.quantity').max) || 0;

            if (!medicineId || quantity <= 0 || sellingPrice <= 0) {
                valid = false;
                row.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.add('is-invalid'));
            } else if (quantity > maxStock) {
                valid = false;
                showToast(`Quantity exceeds available stock (${maxStock})`, 'error');
                row.querySelector('.quantity').classList.add('is-invalid');
            } else {
                medicines.push({
                    medicine_id: medicineId,
                    quantity: quantity,
                    selling_price: sellingPrice
                });
            }
        });

        if (!valid) {
            showToast('Please fill in all required fields correctly', 'warning');
            return;
        }

        const totalAmount = parseFloat(totalAmountInput.value) || 0;
        const amountPaid = parseFloat(amountPaidInput.value) || 0;

        if (amountPaid < totalAmount) {
            showToast('Amount paid is insufficient', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'create_sale');
        formData.append('purchase_id', document.getElementById('sale-purchase').value || '');
        formData.append('payment_method', document.getElementById('sale-payment-method').value);
        formData.append('amount_paid', amountPaid);
        formData.append('discount', discountInput.value || 0);
        formData.append('tax', taxInput.value || 0);
        
        medicines.forEach((med, index) => {
            formData.append(`medicines[${index}][medicine_id]`, med.medicine_id);
            formData.append(`medicines[${index}][quantity]`, med.quantity);
            formData.append(`medicines[${index}][selling_price]`, med.selling_price);
        });

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

        fetch('api/phar_sales.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = safeJsonParse(text);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Complete Sale';
                    
                    if (data.success) {
                        showToast(`Sale completed! Invoice: ${data.data.invoice_number}`, 'success');
                        if (createSaleModal) createSaleModal.hide();
                        loadSales();
                    } else {
                        showToast('Error: ' + (data.errors ? data.errors.join(', ') : 'Unknown error'), 'error');
                    }
                } catch (e) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Complete Sale';
                    console.error('Failed to parse JSON:', text);
                    showToast('Error: Invalid response from server', 'error');
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Complete Sale';
                showToast('Error: ' + error.message, 'error');
                console.error('Error:', error);
            });
    });

    // View and void invoice handlers
    tbody.addEventListener('click', (e) => {
        const viewBtn = e.target.closest('.view-btn');
        const voidBtn = e.target.closest('.void-btn');

        if (viewBtn) {
            const invoiceId = viewBtn.dataset.invoiceId;
            fetch(`api/phar_sales.php?action=get_invoice&invoice_id=${invoiceId}`)
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = safeJsonParse(text);
                        if (data && data.success) {
                            const invoice = data.data;
                            document.getElementById('view-invoice-number').textContent = invoice.invoice_number;
                            document.getElementById('view-purchase').textContent = invoice.purchase_number || 'Walk-in';
                            document.getElementById('view-cashier').textContent = invoice.cashier_name;
                            document.getElementById('view-date').textContent = invoice.created_at;
                            document.getElementById('view-payment-method').textContent = invoice.payment_method.toUpperCase();
                            
                            const itemsBody = document.getElementById('view-items-body');
                            itemsBody.innerHTML = '';
                            invoice.items.forEach(item => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                                    <td>${escapeHtml(item.medicine_name)}</td>
                                    <td>${item.quantity}</td>
                                    <td>₱${parseFloat(item.selling_price).toFixed(2)}</td>
                                    <td>₱${(item.quantity * item.selling_price).toFixed(2)}</td>
                                `;
                                itemsBody.appendChild(row);
                            });
                            
                            document.getElementById('view-subtotal').textContent = '₱' + parseFloat(invoice.subtotal).toFixed(2);
                            document.getElementById('view-discount').textContent = invoice.discount + '%';
                            document.getElementById('view-tax').textContent = invoice.tax + '%';
                            document.getElementById('view-total').textContent = '₱' + parseFloat(invoice.total_amount).toFixed(2);
                            document.getElementById('view-paid').textContent = '₱' + parseFloat(invoice.amount_paid).toFixed(2);
                            document.getElementById('view-change').textContent = '₱' + parseFloat(invoice.change_given).toFixed(2);
                            
                            if (viewInvoiceModal) viewInvoiceModal.show();
                        } else {
                            showToast('Error: ' + (data.errors ? data.errors.join(', ') : 'Unknown error'), 'error');
                        }
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                        showToast('Error: Invalid response from server', 'error');
                    }
                })
                .catch(error => {
                    showToast('Error: ' + error.message, 'error');
                    console.error('Error:', error);
                });
        } else if (voidBtn) {
            pendingVoidInvoiceId = voidBtn.dataset.invoiceId;
            voidSaleModal?.show();
        }
    });

    confirmVoidSaleBtn?.addEventListener('click', () => {
        if (!pendingVoidInvoiceId) return;
        const originalHtml = confirmVoidSaleBtn.innerHTML;
        confirmVoidSaleBtn.disabled = true;
        confirmVoidSaleBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Voiding...';

        const formData = new FormData();
        formData.append('action', 'void_sale');
        formData.append('invoice_id', pendingVoidInvoiceId);

        fetch('api/phar_sales.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = safeJsonParse(text);
                    if (data && data.success) {
                        showToast(data.message || 'Sale voided successfully!', 'success');
                        voidSaleModal?.hide();
                        loadSales();
                    } else {
                        showToast('Error: ' + (data.errors ? data.errors.join(', ') : 'Unknown error'), 'error');
                    }
                } catch (e) {
                    console.error('Failed to parse JSON:', text);
                    showToast('Error: Invalid response from server', 'error');
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message, 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                confirmVoidSaleBtn.disabled = false;
                confirmVoidSaleBtn.innerHTML = originalHtml;
                pendingVoidInvoiceId = null;
            });
    });

    // Print invoice
    document.getElementById('print-invoice-btn').addEventListener('click', () => {
        window.print();
    });

    // Load purchases for dropdown
    let purchasesById = {};
    fetch('api/phar_sales.php?action=get_purchases')
        .then(response => response.text())
        .then(text => {
            try {
                const data = safeJsonParse(text);
                if (data && data.success) {
                    const select = document.getElementById('sale-purchase');
                    data.data.forEach(p => {
                        purchasesById[p.id] = p;
                        const option = document.createElement('option');
                        option.value = p.id;
                        option.textContent = `${p.purchase_number} (${p.purchase_date}) — ${p.medicine_name} x${p.quantity}`;
                        select.appendChild(option);
                    });
                }
            } catch (e) {
                console.error('Failed to parse JSON:', text);
            }

            // Coming from the Purchases page's "Ring Up" button — open the sale form
            // pre-loaded with that purchase instead of leaving the pharmacist to find it.
            const urlPurchaseId = new URLSearchParams(window.location.search).get('purchase_id');
            if (urlPurchaseId && purchasesById[urlPurchaseId]) {
                createSaleModal?.show();
                const select = document.getElementById('sale-purchase');
                select.value = urlPurchaseId;
                applySalePurchaseSelection(urlPurchaseId);
                history.replaceState(null, '', window.location.pathname);
            }
        });

    // When a pending purchase is selected, replace the item list with a single locked row
    // matching it — when cleared, go back to a normal free-entry row. Keeps what the
    // pharmacist sees in sync with what create_sale will actually accept.
    function applySalePurchaseSelection(purchaseId) {
        medicineContainer.innerHTML = '';
        const purchase = purchaseId ? purchasesById[purchaseId] : null;
        const isValidLockedPurchase = purchase && purchase.medicine_id && purchase.quantity !== undefined && purchase.quantity !== null;

        if (isValidLockedPurchase) {
            addSaleMedicineRow(purchase);
            addMedicineRowBtn.disabled = true;
        } else {
            addSaleMedicineRow();
            addMedicineRowBtn.disabled = false;
        }
        calculateSaleTotals();
    }

    document.getElementById('sale-purchase')?.addEventListener('change', (e) => {
        applySalePurchaseSelection(e.target.value);
    });

    // ── Month filter ────────────────────────────────────────────────
    const salesMonthFilter = document.getElementById('sales-month-filter');
    let monthOptionsPopulated = false;

    function populateMonthFilterOptions() {
        if (!salesMonthFilter || monthOptionsPopulated || salesMonthFilter.value) return;
        const months = [...new Set(allInvoices.map(inv => (inv.created_at || '').slice(0, 7)).filter(Boolean))]
            .sort()
            .reverse();
        if (months.length === 0) return;
        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        months.forEach(m => {
            const [y, mo] = m.split('-');
            const opt = document.createElement('option');
            opt.value = m;
            opt.textContent = `${monthNames[parseInt(mo, 10) - 1]} ${y}`;
            salesMonthFilter.appendChild(opt);
        });
        monthOptionsPopulated = true;
    }

    salesMonthFilter?.addEventListener('change', () => {
        salesPage = 1;
        loadSales();
    });

    // ── Revenue / Profit drill-down modal ──────────────────────────
    const summaryDrilldownModalEl = document.getElementById('summaryDrilldownModal');
    const summaryDrilldownModal = summaryDrilldownModalEl ? bootstrap.Modal.getOrCreateInstance(summaryDrilldownModalEl) : null;

    function openSummaryDrilldown(kind) {
        const title = document.getElementById('summary-drilldown-title');
        const body = document.getElementById('summary-drilldown-body');
        const totalRevEl = document.getElementById('summary-drilldown-total-revenue');
        const totalProfitEl = document.getElementById('summary-drilldown-total-profit');
        if (!body) return;

        const monthLabel = salesMonthFilter && salesMonthFilter.value
            ? (salesMonthFilter.selectedOptions[0]?.textContent || '')
            : 'All Time';
        if (title) {
            title.innerHTML = '<i class="bi bi-bar-chart-line me-2"></i>' +
                (kind === 'profit' ? 'Profit' : 'Revenue') + ' Breakdown — ' + monthLabel;
        }

        const sorted = [...allInvoices].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
        let totalRev = 0, totalProfit = 0;
        body.innerHTML = sorted.map(inv => {
            const rev = parseFloat(inv.total_amount || 0);
            const profit = parseFloat(inv.invoice_profit || 0);
            totalRev += rev;
            totalProfit += profit;
            return `<tr>
                <td>${inv.invoice_number || '—'}</td>
                <td>${inv.created_at || '—'}</td>
                <td>${inv.cashier_name || '—'}</td>
                <td>${inv.item_count || 0}</td>
                <td class="text-end">${formatMoney(rev)}</td>
                <td class="text-end ${profit < 0 ? 'text-danger' : 'text-success'}">${formatMoney(profit)}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">No sales in this period.</td></tr>';

        if (totalRevEl) totalRevEl.textContent = formatMoney(totalRev);
        if (totalProfitEl) totalProfitEl.textContent = formatMoney(totalProfit);

        summaryDrilldownModal?.show();
    }

    function wireClickableCard(id, kind) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', () => openSummaryDrilldown(kind));
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openSummaryDrilldown(kind); }
        });
    }
    wireClickableCard('revenue-card', 'revenue');
    wireClickableCard('profit-card', 'profit');

    loadSales();

    // Sidebar toggle
    const sidebarToggle = document.getElementById('toggle-sidebar-mobile');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
            }
        });
    }
});