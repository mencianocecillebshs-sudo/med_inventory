document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('medicine-table');
    const searchInput = document.getElementById('search');
    const sortSelect = document.getElementById('sort');
    const itemTypeSelect = document.getElementById('filter-item-type');
    const itemsPerPageSelect = document.getElementById('items-per-page');
    const addForm = document.getElementById('add-medicine-form');
    const editForm = document.getElementById('edit-medicine-form');
    const loading = document.getElementById('table-loading');
    const paginationContainer = document.getElementById('pagination');
    const pageInfo = document.getElementById('medicine-page-info');
    const deleteModalEl = document.getElementById('deleteMedicineModal');
    const confirmDeleteBtn = document.getElementById('confirm-delete-medicine-btn');
    const deleteModal = deleteModalEl ? bootstrap.Modal.getOrCreateInstance(deleteModalEl) : null;

    let currentPage = 1;
    let itemsPerPage = (window.RECORDS_PER_PAGE || 10);
    let allMedicineCategories = [];
    const urlParams = new URLSearchParams(window.location.search);
    const dashboardStockStatus = urlParams.get('stock_status') || '';
    const dashboardExpiryStatus = urlParams.get('expiry_status') || '';

    if (itemsPerPageSelect) {
        itemsPerPage = parseInt(itemsPerPageSelect.value) || itemsPerPage;
        itemsPerPageSelect.addEventListener('change', () => {
            itemsPerPage = parseInt(itemsPerPageSelect.value) || window.RECORDS_PER_PAGE || 10;
            currentPage = 1;
            loadMedicines(currentPage);
        });
    }

    let pendingDeleteId = null;

    // ── Toast ────────────────────────────────────────────────────────
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || (() => {
            const div = document.createElement('div');
            div.id = 'toast-container';
            div.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(div);
            return div;
        })();
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0 shadow-lg`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(toast);
        new bootstrap.Toast(toast).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // ── Category Combobox ────────────────────────────────────────────────
    function initCategoryCombobox(comboboxId, displayInputId, hiddenInputId, searchInputId, listId, errorId) {
        const combobox  = document.getElementById(comboboxId);
        const display   = document.getElementById(displayInputId);
        const hidden    = document.getElementById(hiddenInputId);
        const searchInp = document.getElementById(searchInputId);
        const list      = document.getElementById(listId);
        const errorEl   = document.getElementById(errorId);

        let isOpen = false;

        function renderList(filter) {
            list.innerHTML = '';
            const lower    = (filter || '').toLowerCase().trim();
            const filtered = lower
                ? allMedicineCategories.filter(t => t.toLowerCase().includes(lower))
                : [...allMedicineCategories];

            if (filtered.length === 0 && !lower) {
                list.innerHTML = '<div class="category-dropdown-empty">No categories yet. Type below to create one.</div>';
            } else {
                filtered.forEach(category => {
                    const item = document.createElement('div');
                    item.className = 'category-dropdown-item' + (hidden.value === category ? ' active' : '');
                    item.innerHTML = `<span class="category-badge"><i class="bi bi-tag-fill me-1"></i>${category}</span>`;
                    item.addEventListener('click', () => selectCategory(category));
                    list.appendChild(item);
                });
            }

            // "Create new" row — only when typed value doesn't match existing
            if (lower && !allMedicineCategories.some(t => t.toLowerCase() === lower)) {
                const newItem = document.createElement('div');
                newItem.className = 'category-dropdown-item new-category-item';
                newItem.innerHTML = `<i class="bi bi-plus-circle-fill"></i> Create "<strong>${filter}</strong>"`;
                newItem.addEventListener('click', () => selectCategory(filter));
                list.appendChild(newItem);
            }
        }

        function selectCategory(value) {
            display.value = value;
            hidden.value  = value;
            showError(false);
            closeDropdown();
        }

        function openDropdown() {
            if (isOpen) return;
            isOpen = true;
            combobox.classList.add('open');
            searchInp.value = '';
            renderList('');
            // Scroll active item into view
            const active = list.querySelector('.category-dropdown-item.active');
            if (active) active.scrollIntoView({ block: 'nearest' });
        }

        function closeDropdown() {
            if (!isOpen) return;
            isOpen = false;
            combobox.classList.remove('open');
        }

        function showError(show, msg = 'Category is required.') {
            if (show) {
                display.classList.add('is-invalid');
                errorEl.textContent = msg;
                errorEl.style.setProperty('display', 'block', 'important');
            } else {
                display.classList.remove('is-invalid');
                errorEl.style.setProperty('display', 'none', 'important');
            }
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

        combobox.querySelector('.category-dropdown').addEventListener('click', (e) => {
            e.stopPropagation();
        });

        document.addEventListener('click', (e) => {
            if (!combobox.contains(e.target)) closeDropdown();
        });

        combobox._setValue = function (val) {
            display.value = val || '';
            hidden.value  = val || '';
            showError(false);
        };
        combobox._validate = function () {
            if (!hidden.value.trim()) { showError(true); return false; }
            showError(false);
            return true;
        };
        combobox._reset = function () {
            display.value   = '';
            hidden.value    = '';
            searchInp.value = '';
            showError(false);
            closeDropdown();
        };

        return combobox;
    }

    const addCombobox  = initCategoryCombobox('add-category-combobox',  'add-category-display',  'category',      'add-category-search',  'add-category-list',  'add-category-error');
    const editCombobox = initCategoryCombobox('edit-category-combobox', 'edit-category-display', 'edit_category', 'edit-category-search', 'edit-category-list', 'edit-category-error');

    // ── Load Categories ───────────────────────────────────────────────
    function loadMedicineCategories() {
        fetch('api/phar_medicines.php?limit=1000&page=1')
            .then(r => r.json())
            .then(data => {
                if (!data.medicines) return;
                allMedicineCategories = [...new Set(
                    data.medicines.map(m => (m.category || '').trim()).filter(Boolean)
                )].sort();
            })
            .catch(err => console.error('Failed to load inventory item categories:', err));
    }

    // ── Load Inventory Items Table ───────────────────────────────────
    function loadMedicines(page = 1) {
        loading.style.display = 'block';
        const search = searchInput.value;
        const sort   = sortSelect.value;
        const item_type = itemTypeSelect ? itemTypeSelect.value : '';
        let url = `api/phar_medicines.php?search=${encodeURIComponent(search)}&sort=${sort}&page=${page}&limit=${itemsPerPage}&item_type=${item_type}`;
        if (dashboardStockStatus) url += `&stock_status=${encodeURIComponent(dashboardStockStatus)}`;
        if (dashboardExpiryStatus) url += `&expiry_status=${encodeURIComponent(dashboardExpiryStatus)}`;
        fetch(url)
            .then(r => r.json())
            .then(data => {
                tableBody.innerHTML      = '';
                paginationContainer.innerHTML = '';

                if (data && data.medicines && Array.isArray(data.medicines)) {
                    data.medicines.forEach(medicine => {
                        const row = document.createElement('tr');
                        const capItemType = (medicine.item_type === 'non-medicine') ? 'Other Product' : 'Medicine';
                        row.innerHTML = `
                            <td>${medicine.name || 'N/A'}</td>
                            <td>${medicine.barcode || 'N/A'}</td>
                            <td>${medicine.quantity ?? 0}</td>
                            <td>${medicine.category || 'N/A'}</td>
                            <td><span class="badge bg-secondary">${capItemType}</span></td>
                            <td>${medicine.description || 'No description'}</td>
                            <td>${medicine.expiry_date || 'N/A'}</td>
                            <td>${medicine.created_at || 'N/A'}</td>
                            <td>
                                <button class="btn btn-sm btn-warning action-btn edit-btn me-1" data-id="${medicine.id || ''}"><i class="bi bi-pencil"></i> Edit</button>
                                <button class="btn btn-sm btn-danger action-btn delete-btn" data-id="${medicine.id || ''}"><i class="bi bi-trash"></i> Delete</button>
                            </td>`;
                        tableBody.appendChild(row);
                    });

                    const totalPages = Math.ceil(data.total / itemsPerPage);
                    if (pageInfo) {
                        const start = data.total ? ((page - 1) * itemsPerPage) + 1 : 0;
                        const end   = Math.min(page * itemsPerPage, data.total || 0);
                        pageInfo.textContent = data.total ? `Showing ${start}–${end} of ${data.total}` : 'No items';
                    }
                    renderPagination(totalPages, page);
                } else {
                    tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No inventory items found.</td></tr>';
                    if (pageInfo) pageInfo.textContent = '';
                }
                loading.style.display = 'none';
            })
            .catch(() => {
                loading.style.display = 'none';
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load inventory items. Please try again.</td></tr>';
            });
    }

    // ── Pagination ───────────────────────────────────────────────────
    function renderPagination(totalPages, cur) {
        if (!paginationContainer || totalPages <= 1) return;
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';

        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${cur === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#">&laquo;</a>`;
        prevLi.addEventListener('click', e => { e.preventDefault(); if (cur > 1) { cur--; loadMedicines(cur); } });
        ul.appendChild(prevLi);

        for (let i = Math.max(1, cur - 2); i <= Math.min(totalPages, cur + 2); i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === cur ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            const page = i;
            li.addEventListener('click', e => { e.preventDefault(); loadMedicines(page); });
            ul.appendChild(li);
        }

        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${cur === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#">&raquo;</a>`;
        nextLi.addEventListener('click', e => { e.preventDefault(); if (cur < totalPages) { cur++; loadMedicines(cur); } });
        ul.appendChild(nextLi);

        paginationContainer.appendChild(ul);
    }

    // ── Add Form ─────────────────────────────────────────────────────
    addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const categoryValid = addCombobox._validate();
        const itemTypeValid = validateAddItemType();
        if (!addForm.checkValidity() || !categoryValid || !itemTypeValid) {
            addForm.classList.add('was-validated');
            return;
        }
        const formData = new FormData(addForm);
        fetch('api/phar_medicines.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('addMedicineModal')).hide();
                    addForm.classList.remove('was-validated');
                    addForm.reset();
                    addCombobox._reset();
                    loadMedicines(currentPage);
                    loadMedicineCategories();
                } else {
                    alert(`Error adding item: ${data.message || 'Unknown error'}`);
                }
            })
            .catch(() => alert('Error adding item'));
    });

    document.getElementById('addMedicineModal').addEventListener('hidden.bs.modal', () => {
        addCombobox._reset();
        addForm.classList.remove('was-validated');
        addForm.reset();
    });

    // ── Edit Form ────────────────────────────────────────────────────
    editForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const categoryValid = editCombobox._validate();
        if (!editForm.checkValidity() || !categoryValid) {
            editForm.classList.add('was-validated');
            return;
        }
        const formData = new FormData(editForm);
        formData.append('_method', 'PUT');
        fetch('api/phar_medicines.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('editMedicineModal')).hide();
                    editForm.classList.remove('was-validated');
                    editForm.reset();
                    editCombobox._reset();
                    loadMedicines(currentPage);
                    loadMedicineCategories();
                } else {
                    alert(`Error updating item: ${data.message || 'Unknown error'}`);
                }
            })
            .catch(() => alert('Error updating item'));
    });

    document.getElementById('editMedicineModal').addEventListener('hidden.bs.modal', () => {
        editCombobox._reset();
        editForm.classList.remove('was-validated');
        editForm.reset();
    });

    // ── Table Click (Edit / Delete) ──────────────────────────────────
    tableBody.addEventListener('click', function (e) {
        const btn = e.target.closest('button');
        if (!btn) return;

        if (btn.classList.contains('edit-btn')) {
            fetch(`api/phar_medicines.php?id=${btn.dataset.id}`)
                .then(r => r.json())
                .then(data => {
                    if (data && !data.status) {
                        document.getElementById('edit_id').value          = data.id || '';
                        document.getElementById('edit_name').value        = data.name || '';
                        document.getElementById('edit_barcode').value     = data.barcode || '';
                        document.getElementById('edit_quantity').value    = data.quantity ?? 0;
                        document.getElementById('edit_description').value = data.description || '';
                        document.getElementById('edit_expiry_date').value = data.expiry_date || '';
                        if (document.getElementById('edit_item_type')) {
                            document.getElementById('edit_item_type').value = data.item_type || 'medicine';
                        }
                        editCombobox._setValue(data.category || '');
                        setEditModalMode(data.item_type === 'non-medicine' ? 'non-medicine' : 'medicine');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('editMedicineModal')).show();
                    } else {
                        alert(`Error loading item: ${data.message || 'Not found'}`);
                    }
                })
                .catch(() => alert('Error loading item data'));

        } else if (btn.classList.contains('delete-btn')) {
            pendingDeleteId = btn.dataset.id;
            deleteModal?.show();
        }
    });

    // ── Confirm Delete ───────────────────────────────────────────────
    confirmDeleteBtn?.addEventListener('click', () => {
        if (!pendingDeleteId) return;
        const orig = confirmDeleteBtn.innerHTML;
        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
        fetch(`api/phar_medicines.php?id=${pendingDeleteId}`, { method: 'DELETE' })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    deleteModal?.hide();
                    loadMedicines(currentPage);
                } else {
                    alert(`Error deleting: ${data.message || 'Unknown error'}`);
                }
            })
            .catch(() => alert('Error deleting item'))
            .finally(() => {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = orig;
                pendingDeleteId = null;
            });
    });

    // ── Search / Sort / Filter ───────────────────────────────────────
    searchInput.addEventListener('input', () => { currentPage = 1; loadMedicines(currentPage); });
    sortSelect.addEventListener('change', () => { currentPage = 1; loadMedicines(currentPage); });
    if (itemTypeSelect) {
        itemTypeSelect.addEventListener('change', () => { currentPage = 1; loadMedicines(currentPage); });
    }

    // ── Item Type Tabs (switch between Medicine / Other Products views) ──
    const itemTypeTabs = document.querySelectorAll('#item-type-tabs .item-type-tab');
    itemTypeTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            itemTypeTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            if (itemTypeSelect) {
                itemTypeSelect.value = tab.dataset.value || '';
            }
            currentPage = 1;
            loadMedicines(currentPage);
        });
    });

    // ── Add Item modal mode ───────────────────────────────────────────
    const addMedicineModalEl = document.getElementById('addMedicineModal');
    const addMedicineModalTitle = addMedicineModalEl ? addMedicineModalEl.querySelector('.modal-title') : null;
    const addItemTypeSelect = document.getElementById('item_type');
    const addItemTypePicker = document.getElementById('add-item-type-picker');
    const addItemTypeTrigger = document.getElementById('add-item-type-trigger');
    const addItemTypeText = document.getElementById('add-item-type-text');
    const addCategoryDisplay = document.getElementById('add-category-display');
    const addSubmitBtn = document.getElementById('add-medicine-submit-btn');
    const itemTypeLabels = {
        medicine: 'Medicine',
        'non-medicine': 'Other Product'
    };

    function setAddModalMode(mode) {
        if (addItemTypeSelect) addItemTypeSelect.value = mode || '';
        if (addItemTypeText) addItemTypeText.textContent = itemTypeLabels[mode] || 'Choose what to add';
        if (addItemTypeTrigger) {
            addItemTypeTrigger.classList.toggle('has-value', !!mode);
            addItemTypeTrigger.classList.remove('is-invalid');
        }
        if (addMedicineModalTitle) {
            addMedicineModalTitle.textContent = 'Add Inventory Item';
        }
        if (addSubmitBtn) {
            addSubmitBtn.innerHTML = '<i class="bi bi-save me-1"></i> Save Item';
        }
        if (addCategoryDisplay) {
            addCategoryDisplay.placeholder = 'Select or type a category...';
        }
    }

    function closeAddItemTypeMenu() {
        addItemTypePicker?.classList.remove('open');
        addItemTypeTrigger?.setAttribute('aria-expanded', 'false');
    }

    function validateAddItemType() {
        const isValid = !!addItemTypeSelect?.value;
        addItemTypeTrigger?.classList.toggle('is-invalid', !isValid);
        return isValid;
    }

    addItemTypeTrigger?.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = addItemTypePicker?.classList.toggle('open');
        addItemTypeTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    addItemTypePicker?.querySelectorAll('.item-type-option').forEach(option => {
        option.addEventListener('click', (e) => {
            e.stopPropagation();
            setAddModalMode(option.dataset.value || '');
            closeAddItemTypeMenu();
        });
    });

    document.addEventListener('click', (e) => {
        if (addItemTypePicker && !addItemTypePicker.contains(e.target)) closeAddItemTypeMenu();
    });

    document.getElementById('addMedicineModal').addEventListener('hidden.bs.modal', () => {
        setAddModalMode('');
        closeAddItemTypeMenu();
    });

    // ── Edit Inventory Item modal mode ────────────────────────────────
    const editMedicineModalTitle = document.getElementById('edit-medicine-modal-title');
    const editSubmitBtn = document.getElementById('edit-medicine-submit-btn');
    const editCategoryDisplay = document.getElementById('edit-category-display');

    function setEditModalMode(mode) {
        if (editMedicineModalTitle) {
            editMedicineModalTitle.textContent = 'Edit Inventory Item';
        }
        if (editSubmitBtn) {
            editSubmitBtn.innerHTML = '<i class="bi bi-save me-1"></i> Update Item';
        }
        if (editCategoryDisplay) {
            editCategoryDisplay.placeholder = 'Select or type a category...';
        }
    }

    // Also keep the title/button in sync if the admin manually flips the Item Type
    // dropdown while editing, not just when the row was first opened.
    document.getElementById('edit_item_type')?.addEventListener('change', (e) => {
        setEditModalMode(e.target.value);
    });

    // ── Init ─────────────────────────────────────────────────────────
    loadMedicines();
    loadMedicineCategories();
});
