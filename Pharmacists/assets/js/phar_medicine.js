document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.getElementById('medicine-table');
    const searchInput = document.getElementById('search');
    const sortSelect = document.getElementById('sort');
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
    let allMedicineTypes = [];

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

    // ── Type Combobox ────────────────────────────────────────────────
    function initTypeCombobox(comboboxId, displayInputId, hiddenInputId, searchInputId, listId, errorId) {
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
                ? allMedicineTypes.filter(t => t.toLowerCase().includes(lower))
                : [...allMedicineTypes];

            if (filtered.length === 0 && !lower) {
                list.innerHTML = '<div class="type-dropdown-empty">No types yet. Type below to create one.</div>';
            } else {
                filtered.forEach(type => {
                    const item = document.createElement('div');
                    item.className = 'type-dropdown-item' + (hidden.value === type ? ' active' : '');
                    item.innerHTML = `<span class="type-badge"><i class="bi bi-tag-fill me-1"></i>${type}</span>`;
                    // Use click instead of mousedown to avoid conflict
                    item.addEventListener('click', () => selectType(type));
                    list.appendChild(item);
                });
            }

            // "Create new" row — only when typed value doesn't match existing
            if (lower && !allMedicineTypes.some(t => t.toLowerCase() === lower)) {
                const newItem = document.createElement('div');
                newItem.className = 'type-dropdown-item new-type-item';
                newItem.innerHTML = `<i class="bi bi-plus-circle-fill"></i> Create "<strong>${filter}</strong>"`;
                newItem.addEventListener('click', () => selectType(filter));
                list.appendChild(newItem);
            }
        }

        function selectType(value) {
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
            const active = list.querySelector('.type-dropdown-item.active');
            if (active) active.scrollIntoView({ block: 'nearest' });
        }

        function closeDropdown() {
            if (!isOpen) return;
            isOpen = false;
            combobox.classList.remove('open');
        }

        function showError(show, msg = 'Type is required.') {
            if (show) {
                display.classList.add('is-invalid');
                errorEl.textContent = msg;
                errorEl.style.setProperty('display', 'block', 'important');
            } else {
                display.classList.remove('is-invalid');
                errorEl.style.setProperty('display', 'none', 'important');
            }
        }

        // Click on the display input — toggle dropdown
        display.addEventListener('click', (e) => {
            e.stopPropagation();
            isOpen ? closeDropdown() : openDropdown();
        });

        // Typing in display input — filter + set value
        display.addEventListener('input', () => {
            hidden.value = display.value;
            showError(false);
            if (!isOpen) openDropdown();
            renderList(display.value);
        });

        // Typing inside the dropdown search box
        searchInp.addEventListener('input', (e) => {
            e.stopPropagation();
            renderList(searchInp.value);
        });

        // Clicking inside the dropdown itself must not close it
        combobox.querySelector('.type-dropdown').addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!combobox.contains(e.target)) closeDropdown();
        });

        // Public API
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

    const addCombobox  = initTypeCombobox('add-type-combobox',  'add-type-display',  'type',      'add-type-search',  'add-type-list',  'add-type-error');
    const editCombobox = initTypeCombobox('edit-type-combobox', 'edit-type-display', 'edit_type', 'edit-type-search', 'edit-type-list', 'edit-type-error');

    // ── Load Types ───────────────────────────────────────────────────
    function loadMedicineTypes() {
        fetch('api/phar_medicines.php?limit=1000&page=1')
            .then(r => r.json())
            .then(data => {
                if (!data.medicines) return;
                allMedicineTypes = [...new Set(
                    data.medicines.map(m => (m.category || '').trim()).filter(Boolean)
                )].sort();
            })
            .catch(err => console.error('Failed to load medicine types:', err));
    }

    // ── Load Medicines Table ─────────────────────────────────────────
    const itemTypeSelect = document.getElementById('filter-item-type');

    function loadMedicines(page = 1) {
        loading.style.display = 'block';
        const search = searchInput.value;
        const sort   = sortSelect.value;
        const item_type = itemTypeSelect ? itemTypeSelect.value : '';
        fetch(`api/phar_medicines.php?search=${encodeURIComponent(search)}&sort=${sort}&page=${page}&limit=${itemsPerPage}&item_type=${item_type}`)
            .then(r => r.json())
            .then(data => {
                tableBody.innerHTML      = '';
                paginationContainer.innerHTML = '';

                if (data && data.medicines && Array.isArray(data.medicines)) {
                    data.medicines.forEach(medicine => {
                        const row = document.createElement('tr');
                        const capItemType = (medicine.item_type === 'non-medicine') ? 'Non-Medicine' : 'Medicine';
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
                        pageInfo.textContent = data.total ? `Showing ${start}–${end} of ${data.total}` : 'No medicines';
                    }
                    renderPagination(totalPages, page);
                } else {
                    tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No medicines found.</td></tr>';
                    if (pageInfo) pageInfo.textContent = '';
                }
                loading.style.display = 'none';
            })
            .catch(() => {
                loading.style.display = 'none';
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">Failed to load medicines. Please try again.</td></tr>';
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
        const typeValid = addCombobox._validate();
        if (!addForm.checkValidity() || !typeValid) {
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
                    loadMedicineTypes();
                } else {
                    alert(`Error adding medicine: ${data.message || 'Unknown error'}`);
                }
            })
            .catch(() => alert('Error adding medicine'));
    });

    document.getElementById('addMedicineModal').addEventListener('hidden.bs.modal', () => {
        addCombobox._reset();
        addForm.classList.remove('was-validated');
        addForm.reset();
    });

    // ── Edit Form ────────────────────────────────────────────────────
    editForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const typeValid = editCombobox._validate();
        if (!editForm.checkValidity() || !typeValid) {
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
                    loadMedicineTypes();
                } else {
                    alert(`Error updating medicine: ${data.message || 'Unknown error'}`);
                }
            })
            .catch(() => alert('Error updating medicine'));
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
                        // Pre-fill the type combobox with the existing value
                        editCombobox._setValue(data.category || '');
                        setEditModalMode(data.item_type === 'non-medicine' ? 'non-medicine' : 'medicine');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('editMedicineModal')).show();
                    } else {
                        alert(`Error loading medicine: ${data.message || 'Not found'}`);
                    }
                })
                .catch(() => alert('Error loading medicine data'));

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
            .catch(() => alert('Error deleting medicine'))
            .finally(() => {
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = orig;
                pendingDeleteId = null;
            });
    });

    // ── Search / Sort ────────────────────────────────────────────────
    searchInput.addEventListener('input', () => { currentPage = 1; loadMedicines(currentPage); });
    sortSelect.addEventListener('change', () => { currentPage = 1; loadMedicines(currentPage); });
    if (itemTypeSelect) {
        itemTypeSelect.addEventListener('change', () => { currentPage = 1; loadMedicines(currentPage); });
    }

    // ── Item Type Tabs (switch between Medicines / Other Products views) ──
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

    // ── Add Medicine / Add Other Product buttons ──────────────────────
    const addMedicineModalEl = document.getElementById('addMedicineModal');
    const addMedicineModalTitle = addMedicineModalEl ? addMedicineModalEl.querySelector('.modal-title') : null;
    const addItemTypeSelect = document.getElementById('item_type');
    const addOtherProductBtn = document.getElementById('add-other-product-btn');
    const addMedicineBtn = document.getElementById('add-medicine-btn');
    const addSubmitBtn = document.getElementById('add-medicine-submit-btn');

    function setAddModalMode(mode) {
        if (addItemTypeSelect) addItemTypeSelect.value = mode;
        if (addMedicineModalTitle) {
            addMedicineModalTitle.textContent = mode === 'non-medicine' ? 'Add Other Product' : 'Add Medicine';
        }
        if (addSubmitBtn) {
            addSubmitBtn.innerHTML = mode === 'non-medicine'
                ? '<i class="bi bi-save me-1"></i> Save Other Products'
                : '<i class="bi bi-save me-1"></i> Save Medicine';
        }
    }

    addOtherProductBtn?.addEventListener('click', () => setAddModalMode('non-medicine'));
    addMedicineBtn?.addEventListener('click', () => setAddModalMode('medicine'));

    addMedicineModalEl?.addEventListener('hidden.bs.modal', () => {
        setAddModalMode('medicine');
    });

    // ── Edit Medicine / Edit Other Product modal mode ──────────────────
    const editMedicineModalTitle = document.getElementById('edit-medicine-modal-title');
    const editSubmitBtn = document.getElementById('edit-medicine-submit-btn');

    function setEditModalMode(mode) {
        if (editMedicineModalTitle) {
            editMedicineModalTitle.textContent = mode === 'non-medicine' ? 'Edit Other Products' : 'Edit Medicine';
        }
        if (editSubmitBtn) {
            editSubmitBtn.innerHTML = mode === 'non-medicine'
                ? '<i class="bi bi-save me-1"></i> Update Products'
                : '<i class="bi bi-save me-1"></i> Update Medicine';
        }
    }

    document.getElementById('edit_item_type')?.addEventListener('change', (e) => {
        setEditModalMode(e.target.value);
    });

    // ── Init ─────────────────────────────────────────────────────────
    loadMedicines();
    loadMedicineTypes();
});