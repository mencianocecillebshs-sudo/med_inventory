document.addEventListener('DOMContentLoaded', () => {
    // Escape HTML to prevent XSS
    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Show notification function
    function showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 6000);
    }

    // Debounce helper (used by filters)
    function debounce(fn, delay) {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    }

    // Sidebar toggle for mobile
    const toggleButton = document.getElementById('toggle-sidebar-mobile');
    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
                const mainContent = document.querySelector('.main-content');
                if (mainContent) {
                    mainContent.style.marginLeft = sidebar.classList.contains('active') ? '250px' : '0';
                }
            }
        });
    }

    // Initialize modals
    const editModalElement = document.getElementById('editSupplierModal');
    const viewMedicinesModalElement = document.getElementById('viewMedicinesModal');
    const addModalElement = document.getElementById('addSupplierModal');
    const editMedicineModalElement = document.getElementById('editSupplierMedicineModal');
    const preferredSupplierModalEl = document.getElementById('preferredSupplierModal');

    let editModal, viewMedicinesModal, addModal, editSupplierMedicineModal, preferredSupplierModal;

    if (editModalElement && typeof bootstrap !== 'undefined') {
        editModal = new bootstrap.Modal(editModalElement, { backdrop: 'static', keyboard: true });
        editModalElement.addEventListener('hidden.bs.modal', cleanupModal);
    }
    if (addModalElement && typeof bootstrap !== 'undefined') {
        addModal = new bootstrap.Modal(addModalElement, { backdrop: 'static', keyboard: true });
        addModalElement.addEventListener('hidden.bs.modal', cleanupModal);
    }
    if (viewMedicinesModalElement && typeof bootstrap !== 'undefined') {
        viewMedicinesModal = new bootstrap.Modal(viewMedicinesModalElement, { backdrop: 'static', keyboard: true });
        viewMedicinesModalElement.addEventListener('hidden.bs.modal', cleanupModal);
    }
    if (editMedicineModalElement && typeof bootstrap !== 'undefined') {
        editSupplierMedicineModal = new bootstrap.Modal(editMedicineModalElement, { backdrop: 'static', keyboard: true });
        editMedicineModalElement.addEventListener('hidden.bs.modal', cleanupModal);
    }
    if (preferredSupplierModalEl && typeof bootstrap !== 'undefined') {
        preferredSupplierModal = new bootstrap.Modal(preferredSupplierModalEl);
        preferredSupplierModalEl.addEventListener('hidden.bs.modal', cleanupModal);
    }

    const preferredSupplierMessage = document.getElementById('preferred-supplier-message');
    const confirmPreferredSupplierBtn = document.getElementById('confirm-preferred-supplier-btn');
    let pendingPreferredButton = null;

    document.querySelectorAll('.preferred-toggle').forEach(button => {
        button.addEventListener('click', () => {
            pendingPreferredButton = button;
            const willPrefer = button.dataset.preferred !== '1';
            if (preferredSupplierMessage) {
                preferredSupplierMessage.textContent = willPrefer
                    ? `Mark ${button.dataset.name || 'this supplier'} as a preferred supplier?`
                    : `Remove ${button.dataset.name || 'this supplier'} from preferred suppliers?`;
            }
            preferredSupplierModal?.show();
        });
    });

    confirmPreferredSupplierBtn?.addEventListener('click', () => {
        if (!pendingPreferredButton) return;
        const button = pendingPreferredButton;
        const willPrefer = button.dataset.preferred !== '1';
        const original = confirmPreferredSupplierBtn.innerHTML;
        confirmPreferredSupplierBtn.disabled = true;
        confirmPreferredSupplierBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        const fd = new FormData();
        fd.append('action', 'toggle_preferred');
        fd.append('id', button.dataset.id || '');
        fd.append('preferred', willPrefer ? '1' : '0');

        fetch('api/suppliers.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    button.dataset.preferred = willPrefer ? '1' : '0';
                    button.classList.toggle('active', willPrefer);
                    button.title = willPrefer ? 'Preferred supplier' : 'Mark as preferred supplier';
                    button.innerHTML = `<i class="bi ${willPrefer ? 'bi-check-circle-fill' : 'bi-circle'}"></i>`;
                    showNotification(data.message || 'Supplier preference updated.', 'success');
                    renderSuppliers();
                } else {
                    showNotification(data.errors?.join(', ') || 'Could not update preferred supplier.', 'danger');
                }
            })
            .catch(err => showNotification('Failed to update preferred supplier: ' + err.message, 'danger'))
            .finally(() => {
                confirmPreferredSupplierBtn.disabled = false;
                confirmPreferredSupplierBtn.innerHTML = original;
                preferredSupplierModal?.hide();
                pendingPreferredButton = null;
            });
    });

    // ── Delete supplier medicine modal ─────────────────────────────
    const deleteSupplierMedicineModalEl = document.getElementById('deleteSupplierMedicineModal');
    const deleteSupplierMedicineModal = deleteSupplierMedicineModalEl ? new bootstrap.Modal(deleteSupplierMedicineModalEl) : null;
    let pendingDeleteMedicineBtn = null;

    document.getElementById('confirm-delete-supplier-medicine-btn')?.addEventListener('click', () => {
        const btn = pendingDeleteMedicineBtn;
        if (!btn) return;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        const fd = new FormData();
        fd.append('action', 'delete_supplier_medicine');
        fd.append('supplier_id', currentSupplierId);
        fd.append('medicine_id', btn.dataset.medicineId);

        fetch('api/suppliers.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    showNotification(d.message || 'Item removed successfully!', 'success');
                    document.querySelector(`.view-medicines-btn[data-id="${currentSupplierId}"]`).click();
                } else {
                    showNotification(d.errors?.join(', ') || 'Error', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = orig;
                }
            })
            .catch(() => {
                showNotification('Network error', 'danger');
                btn.disabled = false;
                btn.innerHTML = orig;
            })
            .finally(() => {
                deleteSupplierMedicineModal?.hide();
                pendingDeleteMedicineBtn = null;
            });
    });

    function cleanupModal() {
        document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    // ── Edit supplier button ─────────────────────────────────────────
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('edit_id').value = button.dataset.id || '';
            document.getElementById('edit_name').value = button.dataset.name || '';
            document.getElementById('edit_company').value = button.dataset.company || '';
            document.getElementById('edit_address').value = button.dataset.address || '';
            document.getElementById('edit_contact').value = button.dataset.contact || '';
            document.getElementById('edit_total_buy').value = parseFloat(button.dataset.totalBuy || 0).toFixed(2);
            document.getElementById('edit_total_paid').value = parseFloat(button.dataset.totalPaid || 0).toFixed(2);
            document.getElementById('edit_total_due').value = parseFloat(button.dataset.totalDue || 0).toFixed(2);
            document.getElementById('edit_representative').value = button.dataset.representative || '';
            document.getElementById('edit_lead_time').value = button.dataset.leadTime || '0';
            if (editModal) editModal.show();
        });
    });

    // ── Delete supplier button ───────────────────────────────────────
    const deleteSupplierModalEl = document.getElementById('deleteSupplierModal');
    const deleteSupplierModal = deleteSupplierModalEl ? new bootstrap.Modal(deleteSupplierModalEl) : null;
    const confirmDeleteSupplierBtn = document.getElementById('confirm-delete-supplier-btn');
    let pendingDeleteButton = null;

    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            pendingDeleteButton = button;
            document.getElementById('delete-supplier-blocked-alert')?.classList.add('d-none');
            deleteSupplierModal?.show();
        });
    });

    confirmDeleteSupplierBtn?.addEventListener('click', () => {
        if (!pendingDeleteButton) return;
        const button = pendingDeleteButton;
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        confirmDeleteSupplierBtn.disabled = true;
        confirmDeleteSupplierBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
        document.getElementById('delete-supplier-blocked-alert')?.classList.add('d-none');

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', button.dataset.id); // ← 'id' to match PHP fix

        fetch('api/suppliers.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    deleteSupplierModal?.hide();
                    showNotification(data.message || 'Supplier deleted successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    const reason = data.errors ? data.errors.join(', ') : 'Error deleting supplier';
                    showNotification(reason, 'danger');
                    const blockedAlert = document.getElementById('delete-supplier-blocked-alert');
                    const blockedMessage = document.getElementById('delete-supplier-blocked-message');
                    if (blockedAlert && blockedMessage) {
                        blockedMessage.textContent = reason;
                        blockedAlert.classList.remove('d-none');
                    }
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            })
            .catch(() => {
                showNotification('Failed to delete supplier. Please try again.', 'danger');
                button.disabled = false;
                button.innerHTML = originalText;
            })
            .finally(() => {
                confirmDeleteSupplierBtn.disabled = false;
                confirmDeleteSupplierBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirm Delete';
                pendingDeleteButton = null;
            });
    });

    // ── Add / Edit supplier forms ────────────────────────────────────
    ['add-supplier-form', 'edit-supplier-form'].forEach(formId => {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();
            form.classList.remove('was-validated');
            if (!form.checkValidity()) { e.stopPropagation(); form.classList.add('was-validated'); return; }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

            // Contact validation
            const contactField = formId === 'add-supplier-form' ? 'contact' : 'edit_contact';
            const contactEl = document.getElementById(contactField);
            const contact = (contactEl?.value || '').trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phoneRegex = /^\+?[0-9\s-]{7,15}$/;
            if (contact && !emailRegex.test(contact) && !phoneRegex.test(contact)) {
                if (contactEl) contactEl.classList.add('is-invalid');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showNotification('Contact must be a valid email or phone number.', 'danger');
                return;
            }

            if (formId === 'edit-supplier-form') {
                const totalBuy = parseFloat(document.getElementById('edit_total_buy')?.value || 0);
                const totalDue = parseFloat(document.getElementById('edit_total_due')?.value || 0);
                if (totalDue > totalBuy) {
                    document.getElementById('edit_total_due')?.classList.add('is-invalid');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    showNotification('Total Due cannot exceed Total Buy.', 'danger');
                    return;
                }
            }

            const formData = new FormData(form);
            fetch('api/suppliers.php', { method: 'POST', body: formData })
                .then(r => {
                    if (!r.ok) throw new Error('Network error: ' + r.status);
                    return r.text().then(text => {
                        try { return JSON.parse(text); }
                        catch (err) { throw new Error('Invalid JSON response from server'); }
                    });
                })
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    if (data.success) {
                        showNotification(data.message || 'Supplier saved successfully!', 'success');
                        form.reset();
                        form.classList.remove('was-validated');
                        if (formId === 'edit-supplier-form' && editModal) editModal.hide();
                        else if (formId === 'add-supplier-form' && addModal) addModal.hide();
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        showNotification(data.errors ? data.errors.join(', ') : 'Error saving supplier', 'danger');
                    }
                })
                .catch(error => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                    showNotification('Failed to save supplier: ' + error.message, 'danger');
                });
        });
    });

    // ── Filter / pagination ──────────────────────────────────────────
    const filterForm = document.getElementById('filter-supplier-form');
    const supplierTable = document.getElementById('supplier-table');       // ← declared OUTSIDE if-block
    const supplierPagination = document.getElementById('supplier-pagination');
    const supplierPageInfo = document.getElementById('supplier-page-info');
    const supplierTableBody = document.getElementById('supplier-table-body');
    const allSupplierRows = Array.from(document.querySelectorAll('#supplier-table-body tr')).filter(r => r.id !== 'no-results-row');
    const rowsPerPage = window.RECORDS_PER_PAGE || 10;
    let supplierPage = 1;

    function currentSupplierFilters() {
        return {
            search: (document.getElementById('filter_search')?.value || '').trim().toLowerCase()
        };
    }

    function filteredSupplierRows() {
        const f = currentSupplierFilters();
        if (!f.search) return allSupplierRows;
        return allSupplierRows.filter(row => {
            const name = row.cells[1]?.textContent.toLowerCase() || '';
            const company = row.cells[2]?.textContent.toLowerCase() || '';
            const contact = row.cells[4]?.textContent.toLowerCase() || '';
            const representative = row.cells[8]?.textContent.toLowerCase() || '';
            const medicines = row.cells[11]?.textContent.toLowerCase() || '';
            return name.includes(f.search)
                || company.includes(f.search)
                || contact.includes(f.search)
                || representative.includes(f.search)
                || medicines.includes(f.search);
        });
    }

    function renderSupplierPagination(totalPages) {
        if (!supplierPagination) return;
        supplierPagination.innerHTML = '';
        if (totalPages < 1) return;
        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        const add = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
            li.addEventListener('click', e => { e.preventDefault(); if (!disabled) { supplierPage = page; renderSuppliers(); } });
            ul.appendChild(li);
        };
        add('&laquo;', supplierPage - 1, supplierPage === 1);
        for (let i = Math.max(1, supplierPage - 2); i <= Math.min(totalPages, supplierPage + 2); i++) add(i, i, false, i === supplierPage);
        add('&raquo;', supplierPage + 1, supplierPage === totalPages);
        supplierPagination.appendChild(ul);
    }

    function renderSuppliers() {
        const tbody = supplierTableBody;
        if (!tbody) return;
        const noResultsRow = document.getElementById('no-results-row');
        const rows = filteredSupplierRows();
        const totalPages = Math.max(1, Math.ceil(rows.length / rowsPerPage));
        if (supplierPage > totalPages) supplierPage = totalPages;
        const start = (supplierPage - 1) * rowsPerPage;
        const visible = rows.slice(start, start + rowsPerPage);
        allSupplierRows.forEach(row => row.style.display = 'none');
        visible.forEach(row => row.style.display = '');
        if (noResultsRow) noResultsRow.style.display = rows.length === 0 ? '' : 'none';
        if (supplierPageInfo) supplierPageInfo.textContent = rows.length
            ? `Showing ${start + 1}-${Math.min(start + rowsPerPage, rows.length)} of ${rows.length}`
            : 'No suppliers';
        renderSupplierPagination(rows.length ? totalPages : 0);
    }

    if (filterForm) {
        filterForm.addEventListener('submit', e => e.preventDefault());
        filterForm.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', debounce(() => { supplierPage = 1; renderSuppliers(); }, 200));
        });

        document.getElementById('apply-supplier-filter')?.addEventListener('click', () => {
            supplierPage = 1;
            renderSuppliers();
        });

        document.getElementById('reset-filter')?.addEventListener('click', () => {
            filterForm.reset();
            filterForm.classList.remove('was-validated');
            supplierPage = 1;
            renderSuppliers();
            showNotification('Filters cleared', 'info');
        });
    }

    renderSuppliers();

    // ── Show / Hide optional columns ─────────────────────────────────
    // FIX: supplierTable is now in outer scope so this always works
    document.getElementById('toggle-supplier-columns')?.addEventListener('click', function () {
        if (!supplierTable) return;
        const showAll = supplierTable.classList.toggle('show-all');
        this.innerHTML = showAll
            ? '<i class="bi bi-layout-sidebar"></i> Key Columns'
            : '<i class="bi bi-layout-three-columns"></i> Show All Columns';
    });

    // ── View supplier items button ───────────────────────────────────
    let currentSupplierId = null;

    document.querySelectorAll('.view-medicines-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            currentSupplierId = button.dataset.id;
            const supplierName = escapeHtml(button.dataset.name);

            document.getElementById('viewMedicinesModalLabel').innerHTML =
                `<i class="bi bi-box-seam me-2"></i>Items from ${supplierName}`;

            const loading = document.getElementById('medicines-loading');
            const errorDiv = document.getElementById('medicines-error');
            const errorSpan = errorDiv?.querySelector('span');
            const tbody = document.getElementById('medicines-table-body');
            const noDataMsg = document.getElementById('no-medicines-message');
            const tableContainer = document.getElementById('medicines-table-container');

            loading.style.display = 'block';
            errorDiv?.classList.add('d-none');
            tbody.innerHTML = '';
            noDataMsg.classList.add('d-none');
            tableContainer.style.display = 'none';

            fetch(`api/suppliers.php?supplier_id=${currentSupplierId}`)
                .then(r => { if (!r.ok) throw new Error('Network error: ' + r.status); return r.json(); })
                .then(data => {
                    loading.style.display = 'none';
                    if (data.success && data.data && data.data.length > 0) {
                        tableContainer.style.display = 'block';
                        data.data.forEach(medicine => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td><strong>${escapeHtml(medicine.name)}</strong></td>
                                <td>₱${parseFloat(medicine.unit_price || 0).toFixed(2)}</td>
                                <td>${medicine.min_order_quantity ? medicine.min_order_quantity + ' units' : '<span class="text-muted">N/A</span>'}</td>
                                <td>
                                    ${medicine.preferred
                                    ? '<span class="badge bg-success rounded-pill"><i class="bi bi-check"></i> Yes</span>'
                                    : '<span class="badge bg-secondary rounded-pill"><i class="bi bi-x"></i> No</span>'}
                                </td>
                                <td><strong>${medicine.quantity_supplied || 0}</strong></td>
                                <td>
                                    <button class="btn btn-warning btn-sm action-btn edit-medicine-btn"
                                        data-medicine-id="${medicine.medicine_id}"
                                        data-medicine-name="${escapeHtml(medicine.name)}"
                                        data-unit-price="${medicine.unit_price || 0}"
                                        data-min-order="${medicine.min_order_quantity || 0}"
                                        data-quantity-supplied="${medicine.quantity_supplied || 0}"
                                        data-preferred="${medicine.preferred ? '1' : '0'}">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm action-btn delete-medicine-btn"
                                        data-medicine-id="${medicine.medicine_id}"
                                        data-medicine-name="${escapeHtml(medicine.name)}">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                        attachMedicineHandlers();
                    } else {
                        noDataMsg.classList.remove('d-none');
                    }
                    if (viewMedicinesModal) viewMedicinesModal.show();
                })
                .catch(error => {
                    loading.style.display = 'none';
                    errorDiv?.classList.remove('d-none');
                    if (errorSpan) errorSpan.textContent = 'Failed to load items: ' + error.message;
                });
        });
    });

    function attachMedicineHandlers() {
        // Edit item
        document.querySelectorAll('.edit-medicine-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('edit_sm_supplier_id').value = currentSupplierId;
                document.getElementById('edit_sm_medicine_id').value = btn.dataset.medicineId;
                document.getElementById('edit_sm_medicine_name').textContent = btn.dataset.medicineName;
                document.getElementById('edit_sm_unit_price').value = parseFloat(btn.dataset.unitPrice).toFixed(2);
                document.getElementById('edit_sm_min_order_quantity').value = btn.dataset.minOrder || '';
                document.getElementById('edit_sm_quantity_supplied').value = btn.dataset.quantitySupplied || '0';
                document.getElementById('edit_sm_preferred').checked = btn.dataset.preferred === '1';
                if (editSupplierMedicineModal) editSupplierMedicineModal.show();
            });
        });

        // Delete item - uses a modal confirmation instead of a browser confirm() dialog
        document.querySelectorAll('.delete-medicine-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                pendingDeleteMedicineBtn = btn;
                document.getElementById('delete-sm-medicine-name').textContent = btn.dataset.medicineName || '';
                deleteSupplierMedicineModal?.show();
            });
        });
    }

    // ── Edit supplier medicine form submit ───────────────────────────
    const editSupplierMedicineForm = document.getElementById('edit-supplier-medicine-form');
    if (editSupplierMedicineForm) {
        editSupplierMedicineForm.addEventListener('submit', e => {
            e.preventDefault();
            if (!editSupplierMedicineForm.checkValidity()) {
                editSupplierMedicineForm.classList.add('was-validated');
                return;
            }
            const btn = editSupplierMedicineForm.querySelector('button[type="submit"]');
            const txt = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

            const fd = new FormData(editSupplierMedicineForm);
            fd.set('preferred', document.getElementById('edit_sm_preferred').checked ? '1' : '0');
            // Ensure action is correct
            fd.set('action', 'edit_supplier_medicine');

            fetch('api/suppliers.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    btn.disabled = false;
                    btn.innerHTML = txt;
                    if (d.success) {
                        showNotification(d.message || 'Updated successfully!', 'success');
                        editSupplierMedicineForm.reset();
                        editSupplierMedicineForm.classList.remove('was-validated');
                        if (editSupplierMedicineModal) editSupplierMedicineModal.hide();
                        // Refresh the medicines list
                        document.querySelector(`.view-medicines-btn[data-id="${currentSupplierId}"]`).click();
                    } else {
                        showNotification(d.errors?.join(', ') || 'Error', 'danger');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = txt;
                    showNotification('Failed: ' + err.message, 'danger');
                });
        });
    }

    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            try { new bootstrap.Alert(alert).close(); } catch (e) { }
        });
    }, 5000);
});
