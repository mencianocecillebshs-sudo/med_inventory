document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('medicine-table');
    const searchInput = document.getElementById('search');
    const stockFilter = document.getElementById('stock-filter');
    const sortSelect = document.getElementById('sort');
    const pagination = document.getElementById('pagination');
    const medicineCount = document.getElementById('medicine-count');
    const addNewMedicineForm = document.getElementById('add-new-medicine-form');
    const editMedicineForm = document.getElementById('edit-medicine-form');
    const addSupplyForm = document.getElementById('add-supply-form');
    const addSupplyModal = document.getElementById('addSupplyModal');
    const deleteModalEl = document.getElementById('deleteMedicineModal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-medicine-btn');

    const peso = window.SupUtils ? window.SupUtils.currencySymbol() : '\u20b1';
    let currentPage = 1;
    let currentSort = 'name';
    let currentSearch = '';
    let currentStockFilter = '';
    let pendingDeleteId = null;
    let allMedicineTypes = [];

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
        return container;
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        toast.style.zIndex = '9999';
        toast.style.minWidth = '350px';
        toast.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'x-circle' : 'info-circle'} me-2"></i>
            <span class="toast-message"></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        toast.querySelector('.toast-message').textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'include',
            ...options
        });
        const text = await response.text();
        let payload;

        try {
            payload = text ? JSON.parse(text) : {};
        } catch (error) {
            throw new Error(`Invalid server response: ${text.slice(0, 120)}`);
        }

        if (!response.ok || payload.status === 'error' || payload.success === false) {
            throw new Error(payload.message || payload.error || `Request failed: ${response.status}`);
        }

        return payload;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function truncate(text, length) {
        const value = String(text ?? '');
        return value.length <= length ? value : `${value.substring(0, length)}...`;
    }

    function initTypeCombobox(comboboxId, displayInputId, hiddenInputId, searchInputId, listId, errorId) {
        const combobox = document.getElementById(comboboxId);
        const display = document.getElementById(displayInputId);
        const hidden = document.getElementById(hiddenInputId);
        const searchInp = document.getElementById(searchInputId);
        const list = document.getElementById(listId);
        const errorEl = document.getElementById(errorId);

        if (!combobox || !display || !hidden || !searchInp || !list) return null;

        let isOpen = false;

        function renderList(filter = '') {
            const lower = filter.toLowerCase().trim();
            const filtered = lower
                ? allMedicineTypes.filter((type) => type.toLowerCase().includes(lower))
                : [...allMedicineTypes];

            list.innerHTML = '';

            if (filtered.length === 0 && !lower) {
                list.innerHTML = '<div class="type-dropdown-empty">No types yet. Type to create one.</div>';
            } else {
                filtered.forEach((type) => {
                    const item = document.createElement('div');
                    item.className = `type-dropdown-item${hidden.value === type ? ' active' : ''}`;
                    item.textContent = type;
                    item.addEventListener('click', () => selectType(type));
                    list.appendChild(item);
                });
            }

            if (lower && !allMedicineTypes.some((type) => type.toLowerCase() === lower)) {
                const newItem = document.createElement('div');
                newItem.className = 'type-dropdown-item';
                newItem.innerHTML = `<i class="bi bi-plus-circle-fill"></i> Create "<strong>${escapeHtml(filter)}</strong>"`;
                newItem.addEventListener('click', () => selectType(filter));
                list.appendChild(newItem);
            }
        }

        function selectType(value) {
            display.value = value;
            hidden.value = value;
            showError(false);
            closeDropdown();
        }

        function openDropdown() {
            if (isOpen) return;
            isOpen = true;
            combobox.classList.add('open');
            searchInp.value = '';
            renderList(display.value);
        }

        function closeDropdown() {
            if (!isOpen) return;
            isOpen = false;
            combobox.classList.remove('open');
        }

        display.addEventListener('click', (e) => {
            e.stopPropagation();
            isOpen ? closeDropdown() : openDropdown();
        });

        display.addEventListener('input', () => {
            hidden.value = display.value;
            showError(false);
            if (!isOpen) openDropdown();
            renderList(display.value);
        });

        searchInp.addEventListener('input', (e) => {
            e.stopPropagation();
            renderList(searchInp.value);
        });

        combobox.querySelector('.type-dropdown').addEventListener('click', (e) => {
            e.stopPropagation();
        });

        document.addEventListener('click', (e) => {
            if (!combobox.contains(e.target)) closeDropdown();
        });

        combobox._setValue = (value) => {
            display.value = value || '';
            hidden.value = value || '';
            showError(false);
            closeDropdown();
        };

        combobox._validate = () => {
            if (!hidden.value.trim()) {
                showError(true);
                return false;
            }
            showError(false);
            return true;
        };

        combobox._reset = () => {
            display.value = '';
            hidden.value = '';
            searchInp.value = '';
            showError(false);
            closeDropdown();
        };

        function showError(show, message = 'Type is required.') {
            if (show) {
                display.classList.add('is-invalid');
                if (errorEl) {
                    errorEl.textContent = message;
                    errorEl.style.setProperty('display', 'block', 'important');
                }
            } else {
                display.classList.remove('is-invalid');
                if (errorEl) {
                    errorEl.style.setProperty('display', 'none', 'important');
                }
            }
        }

        return combobox;
    }

    const addTypeCombobox = initTypeCombobox(
        'add-type-combobox',
        'add-type-display',
        'type',
        'add-type-search',
        'add-type-list',
        'add-type-error'
    );
    const editTypeCombobox = initTypeCombobox(
        'edit-type-combobox',
        'edit-type-display',
        'edit_type',
        'edit-type-search',
        'edit-type-list',
        'edit-type-error'
    );

    function renderEmptyState(message = 'No medicines saved under your supplier account yet.') {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e0;"></i>
                    <p class="text-muted mt-3">${escapeHtml(message)}</p>
                </td>
            </tr>
        `;
        pagination.innerHTML = '';
        medicineCount.textContent = '0 medicines';
        if (window.SupUtils) {
            window.SupUtils.renderPagination(pagination, { total_items: 0, total_pages: 1, current_page: currentPage }, currentPage, (page) => {
                currentPage = page;
                loadMedicines();
            });
        }
    }

    function renderPagination(paginationData) {
        if (!window.SupUtils) return;
        window.SupUtils.renderPagination(pagination, paginationData, currentPage, (page) => {
            currentPage = page;
            loadMedicines();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    function renderMedicines(medicines, paginationData) {
        tableBody.innerHTML = '';

        if (!Array.isArray(medicines) || medicines.length === 0) {
            renderEmptyState();
            return;
        }

        medicines.forEach((med) => {
            const stockQty = parseInt(med.quantity, 10) || 0;
            const price = parseFloat(med.unit_price) || 0;
            const minOrder = parseInt(med.min_order_quantity, 10) || 1;
            const lowThreshold = window.SupUtils ? window.SupUtils.getLowStockThreshold() : (window.LOW_STOCK_THRESHOLD || 10);
            const criticalThreshold = window.SupUtils ? window.SupUtils.getCriticalStockThreshold() : (window.CRITICAL_STOCK_THRESHOLD || 5);

            let stockBadge;
            if (stockQty <= 0) {
                stockBadge = '<span class="stock-badge stock-out">No Stock</span>';
            } else if (stockQty <= criticalThreshold) {
                stockBadge = `<span class="stock-badge stock-out">${stockQty} units (Critical)</span>`;
            } else if (stockQty <= lowThreshold) {
                stockBadge = `<span class="stock-badge bg-warning text-dark">${stockQty} units (Low)</span>`;
            } else {
                stockBadge = `<span class="stock-badge stock-in">${stockQty} units</span>`;
            }

            const statusIcon = stockQty > 0
                ? '<i class="bi bi-check-circle-fill text-success"></i> Available for Orders'
                : '<i class="bi bi-x-circle-fill text-danger"></i> Not Available';

            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${escapeHtml(med.name)}</strong></td>
                <td class="col-detail">${escapeHtml(med.type || '-')}</td>
                <td class="col-detail">${escapeHtml(truncate(med.description || '-', 50))}</td>
                <td>${stockBadge}</td>
                <td>${price > 0 ? (window.SupUtils ? window.SupUtils.formatCurrency(price) : `${peso}${price.toFixed(2)}`) : '<span class="text-muted">Not set</span>'}</td>
                <td class="col-detail">${minOrder}</td>
                <td><small class="text-muted">${statusIcon}</small></td>
                <td>
                    <button class="btn btn-sm btn-success action-btn add-supply-btn" data-id="${med.id}" data-name="${escapeHtml(med.name)}">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Add Supply
                    </button>
                    <button class="btn btn-sm btn-warning action-btn edit-medicine-btn" data-id="${med.id}">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                    </button>
                    <button class="btn btn-sm btn-danger action-btn delete-medicine-btn"
                        data-id="${med.id}"
                        data-name="${escapeHtml(med.name)}">
                        <i class="bi bi-trash me-1"></i>Remove
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });

        allMedicineTypes = [...new Set([
            ...allMedicineTypes,
            ...medicines.map((med) => String(med.type || '').trim()).filter(Boolean)
        ])].sort();

        renderPagination(paginationData);
    }

    async function loadMedicines() {
        const itemsPerPage = window.SupUtils ? window.SupUtils.getRecordsPerPage() : (window.RECORDS_PER_PAGE || 25);
        const params = new URLSearchParams({
            page: currentPage,
            limit: itemsPerPage,
            sort: currentSort,
            search: currentSearch,
            stock_filter: currentStockFilter
        });

        // If page was loaded in debug mode, include debug & supplier_id so API can return data without session (dev only)
        if (window.debugMode) {
            params.set('debug', '1');
            if (window.supplierId) params.set('supplier_id', String(window.supplierId));
        }

        try {
            const data = await fetchJson(`api/sup_medicines.php?${params}`);
            renderMedicines(data.data, data.pagination);
            medicineCount.textContent = `${data.pagination?.total_items || 0} medicines`;
        } catch (error) {
            console.error('Load error:', error);
            showToast(error.message || 'Failed to load medicines', 'danger');
            renderEmptyState('Failed to load medicines.');
        }
    }

    async function openEditMedicine(medicineId) {
        try {
            let url = `api/sup_medicines.php?id=${encodeURIComponent(medicineId)}`;
            if (window.debugMode && window.supplierId) url += `&debug=1&supplier_id=${encodeURIComponent(window.supplierId)}`;
            const response = await fetchJson(url);
            const med = response.data || {};

            document.getElementById('edit_medicine_id').value = med.id || medicineId;
            document.getElementById('edit_name').value = med.name || '';
            document.getElementById('edit_barcode').value = med.barcode || '';
            if (editTypeCombobox) {
                editTypeCombobox._setValue(med.type || '');
            } else {
                document.getElementById('edit_type').value = med.type || '';
            }
            document.getElementById('edit_expiry_date').value = med.expiry_date || '';
            document.getElementById('edit_description').value = med.description || '';
            document.getElementById('edit_quantity').value = parseInt(med.quantity, 10) || 0;
            document.getElementById('edit_unit_price').value = parseFloat(med.unit_price) > 0 ? parseFloat(med.unit_price).toFixed(2) : '';
            document.getElementById('edit_min_order').value = parseInt(med.min_order_quantity, 10) || 1;
            document.getElementById('edit_preferred').checked = parseInt(med.preferred, 10) === 1;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('editMedicineModal')).show();
        } catch (error) {
            console.error('Edit load error:', error);
            showToast(error.message || 'Failed to load medicine', 'danger');
        }
    }

    function validateStockForm(formData) {
        const quantity = parseInt(formData.get('quantity'), 10);
        const unitPrice = parseFloat(formData.get('unit_price'));

        if (!Number.isFinite(quantity) || quantity < 0) {
            showToast('Quantity cannot be negative', 'warning');
            return false;
        }

        if (!Number.isFinite(unitPrice) || unitPrice <= 0) {
            showToast('Unit price must be greater than 0', 'warning');
            return false;
        }

        return true;
    }

    tableBody.addEventListener('click', (e) => {
        const addSupplyBtn = e.target.closest('.add-supply-btn');
        const editBtn = e.target.closest('.edit-medicine-btn');
        const deleteBtn = e.target.closest('.delete-medicine-btn');

        if (addSupplyBtn) {
            document.getElementById('supply_medicine_id').value = addSupplyBtn.dataset.id;
            document.getElementById('supply_medicine_name').textContent = addSupplyBtn.dataset.name || '';
            document.getElementById('supply_quantity').value = '';
            document.getElementById('supply_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('supply_notes').value = '';
            addSupplyForm?.classList.remove('was-validated');
            bootstrap.Modal.getOrCreateInstance(addSupplyModal).show();
            return;
        }

        if (editBtn) {
            openEditMedicine(parseInt(editBtn.dataset.id, 10));
            return;
        }

        if (deleteBtn) {
            pendingDeleteId = parseInt(deleteBtn.dataset.id, 10);
            document.getElementById('delete_medicine_name').textContent = deleteBtn.dataset.name || 'this medicine';
            bootstrap.Modal.getOrCreateInstance(deleteModalEl).show();
        }
    });

    addSupplyForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!addSupplyForm.checkValidity()) {
            addSupplyForm.classList.add('was-validated');
            return;
        }

        const submitBtn = addSupplyForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Recording...';

        try {
            const data = await fetchJson('api/sup_medicines.php', {
                method: 'POST',
                body: new FormData(addSupplyForm)
            });
            showToast(data.message || 'Supply recorded successfully', 'success');
            bootstrap.Modal.getInstance(addSupplyModal)?.hide();
            addSupplyForm.reset();
            loadMedicines();
        } catch (error) {
            console.error('Add supply error:', error);
            showToast(error.message || 'Failed to record supply', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearch = e.target.value;
                currentPage = 1;
                loadMedicines();
            }, 500);
        });
    }

    stockFilter?.addEventListener('change', (e) => {
        currentStockFilter = e.target.value;
        currentPage = 1;
        loadMedicines();
    });

    sortSelect?.addEventListener('change', (e) => {
        currentSort = e.target.value;
        currentPage = 1;
        loadMedicines();
    });

    addNewMedicineForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(addNewMedicineForm);
        const typeValid = addTypeCombobox ? addTypeCombobox._validate() : true;
        if (!addNewMedicineForm.checkValidity() || !typeValid) {
            addNewMedicineForm.classList.add('was-validated');
            return;
        }
        if (!validateStockForm(formData)) return;

        const submitBtn = addNewMedicineForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';

        try {
            const data = await fetchJson('api/sup_medicines.php', {
                method: 'POST',
                body: formData
            });
            showToast(data.message || 'Medicine added successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addNewMedicineModal')).hide();
            addNewMedicineForm.classList.remove('was-validated');
            addNewMedicineForm.reset();
            addTypeCombobox?._reset();
            loadMedicines();
        } catch (error) {
            console.error('Add medicine error:', error);
            showToast(error.message || 'Failed to add medicine', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    document.getElementById('addNewMedicineModal')?.addEventListener('hidden.bs.modal', () => {
        addNewMedicineForm?.classList.remove('was-validated');
        addNewMedicineForm?.reset();
        addTypeCombobox?._reset();
    });

    editMedicineForm?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(editMedicineForm);
        const typeValid = editTypeCombobox ? editTypeCombobox._validate() : true;
        if (!editMedicineForm.checkValidity() || !typeValid) {
            editMedicineForm.classList.add('was-validated');
            return;
        }
        if (!validateStockForm(formData)) return;

        const submitBtn = editMedicineForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        try {
            const data = await fetchJson('api/sup_medicines.php', {
                method: 'POST',
                body: formData
            });
            showToast(data.message || 'Medicine updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editMedicineModal')).hide();
            editMedicineForm.classList.remove('was-validated');
            editTypeCombobox?._reset();
            loadMedicines();
        } catch (error) {
            console.error('Edit medicine error:', error);
            showToast(error.message || 'Failed to update medicine', 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    document.getElementById('editMedicineModal')?.addEventListener('hidden.bs.modal', () => {
        editMedicineForm?.classList.remove('was-validated');
        editMedicineForm?.reset();
        editTypeCombobox?._reset();
    });

    confirmDeleteBtn?.addEventListener('click', async () => {
        if (!pendingDeleteId) return;

        const originalText = confirmDeleteBtn.innerHTML;
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Removing...';

        try {
            const formData = new FormData();
            formData.append('action', 'delete_medicine');
            formData.append('medicine_id', pendingDeleteId);

            const data = await fetchJson('api/sup_medicines.php', {
                method: 'POST',
                body: formData
            });
            showToast(data.message || 'Medicine removed from your inventory', 'success');
            bootstrap.Modal.getInstance(deleteModalEl).hide();
            pendingDeleteId = null;
            loadMedicines();
        } catch (error) {
            console.error('Delete medicine error:', error);
            showToast(error.message || 'Failed to remove medicine', 'danger');
        } finally {
            confirmDeleteBtn.disabled = false;
            confirmDeleteBtn.innerHTML = originalText;
        }
    });

    if (window.SupUtils) {
        window.SupUtils.initExpandableTable('#medicine-table-wrap', '#toggle-medicine-columns');
    }

    loadMedicines();
});
