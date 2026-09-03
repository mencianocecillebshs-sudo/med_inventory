document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('search');
    const medicineFilter = document.getElementById('medicine-filter');
    const statusFilter = document.getElementById('status-filter');
    const tableBody = document.getElementById('purchase-table');
    const noDataRow = document.getElementById('no-data-row');
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggle-sidebar-mobile');
    const tableLoading = document.getElementById('table-loading');
    const pagination = document.getElementById('purchase-pagination');
    const pageInfo = document.getElementById('purchase-page-info');

    // ── Modal instances ──────────────────────────────────────────────
    const deletePurchaseModalEl = document.getElementById('deletePurchaseModal');
    const deletePurchaseModal = deletePurchaseModalEl
        ? bootstrap.Modal.getOrCreateInstance(deletePurchaseModalEl) : null;
    const confirmDeletePurchaseBtn = document.getElementById('confirm-delete-purchase-btn');

    // "Fill" used to be a self-contained modal here — purchases are now only fulfilled
    // through Sales checkout, so this page just hands off to it. See handleRingUp() below.

    const removeNoteModalEl = document.getElementById('removeNoteModal');
    const removeNoteModal = removeNoteModalEl
        ? bootstrap.Modal.getOrCreateInstance(removeNoteModalEl) : null;
    const confirmRemoveNoteBtn = document.getElementById('confirm-remove-note-btn');

    const removeMedRowModalEl = document.getElementById('removeMedRowModal');
    const removeMedRowModal = removeMedRowModalEl
        ? bootstrap.Modal.getOrCreateInstance(removeMedRowModalEl) : null;
    const confirmRemoveMedRowBtn = document.getElementById('confirm-remove-med-row-btn');

    // ── State ────────────────────────────────────────────────────────
    let medicines = [];
    let purchaseRows = [];
    let purchasePage = 1;
    let pendingDeletePurchaseId = null;
    let pendingRemoveNoteRow = null;
    let pendingRemoveMedRow = null;
    const perPage = window.RECORDS_PER_PAGE || 10;

    // ── Sidebar & Theme ──────────────────────────────────────────────
    if (toggleButton && sidebar) {
        if (localStorage.getItem('sidebar') === 'open') sidebar.classList.add('active');
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            localStorage.setItem('sidebar', sidebar.classList.contains('active') ? 'open' : 'closed');
        });
    }
    if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    });

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

    // ── Load medicines ───────────────────────────────────────────────
    async function loadMedicines() {
        try {
            const res = await fetch('../Admin/api/medicines.php?limit=9999');
            const data = await res.json();
            medicines = data.medicines || [];
            const optionHtml = medicines.map(m =>
                `<option value="${m.id}" data-price="${parseFloat(m.selling_price || 0).toFixed(2)}">${m.name}${m.selling_price > 0
                    ? ' - ₱' + parseFloat(m.selling_price).toFixed(2) : ''}</option>`
            ).join('');
            const full = '<option value="">Select Medicine</option>' + optionHtml;
            const filter = '<option value="">All Medicines</option>' + optionHtml;
            document.querySelectorAll('#edit_medicine_id').forEach(el => el.innerHTML = full);
            if (medicineFilter) medicineFilter.innerHTML = filter;
            resetMedItems();
        } catch (err) {
            console.error(err);
            showToast('Failed to load medicines', 'danger');
        }
    }

    // ── Auto-calculate total cost (edit modal) ───────────────────────
    function calculateTotalCost(medId, qtyId, totalId) {
        const med = medicines.find(m => m.id == document.getElementById(medId)?.value);
        const qty = parseInt(document.getElementById(qtyId)?.value) || 0;
        const price = med ? parseFloat(med.selling_price || 0) : 0;
        if (document.getElementById(totalId)) {
            document.getElementById(totalId).value = (qty * price).toFixed(2);
        }
    }

    function attachCalculators() {
        document.getElementById('edit_medicine_id')?.addEventListener('change',
            () => calculateTotalCost('edit_medicine_id', 'edit_quantity', 'edit_total_cost'));
        document.getElementById('edit_quantity')?.addEventListener('input',
            () => calculateTotalCost('edit_medicine_id', 'edit_quantity', 'edit_total_cost'));
    }

    // ════════════════════════════════════════════════════════════════
    //  MEDICINE COMBOBOX — mirrors the Type Combobox from medicines.js
    // ════════════════════════════════════════════════════════════════

    /**
     * Wire a combobox-style medicine picker onto a table row.
     * Matches the exact open/close/select/render pattern of the
     * medicine-type combobox used in medicines.js.
     *
     * DOM structure expected inside `tr`:
     *   .med-combobox
     *     .med-input-wrapper
     *       input.med-display-input   (visible, typed into)
     *       i.med-chevron
     *     .med-dropdown
     *       .med-dropdown-list         (items rendered here)
     *   input.med-row-hidden-id        (submitted value)
     *   input.med-row-hidden-price
     *   .med-selected-label
     *   .med-invalid
     */
    function wireMedCombobox(tr) {
        const combobox = tr.querySelector('.med-combobox');
        const display = tr.querySelector('.med-display-input');
        const chevron = tr.querySelector('.med-chevron');
        const dropdown = tr.querySelector('.med-dropdown');
        const list = tr.querySelector('.med-dropdown-list');
        const hiddenId = tr.querySelector('.med-row-hidden-id');
        const hiddenPrice = tr.querySelector('.med-row-hidden-price');
        const selectedLbl = tr.querySelector('.med-selected-label');
        const invalidEl = tr.querySelector('.med-invalid');

        let isOpen = false;

        // ── Render dropdown items ────────────────────────────────────
        function renderList(filter) {
            list.innerHTML = '';
            const q = (filter || '').trim().toLowerCase();
            const filtered = q
                ? medicines.filter(m => m.name.toLowerCase().includes(q))
                : [...medicines];

            if (filtered.length === 0) {
                list.innerHTML = '<div class="med-dd-empty">No medicines found.</div>';
                return;
            }

            filtered.forEach(m => {
                const price = parseFloat(m.selling_price || 0);
                const item = document.createElement('div');
                item.className = 'med-dd-item' + (hiddenId.value == m.id ? ' active' : '');
                item.innerHTML = `
                    <span class="med-dd-name">${m.name}</span>
                    <span class="med-dd-price">${price > 0 ? '₱' + price.toFixed(2) : 'No price'}</span>`;
                item.addEventListener('click', () => selectMedicine(m.id, m.name, price));
                list.appendChild(item);
            });
        }

        // ── Select a medicine ────────────────────────────────────────
        function selectMedicine(id, name, price) {
            hiddenId.value = id;
            hiddenPrice.value = price;
            display.value = name;
            display.dataset.lastName = name; // Save existing selection name
            selectedLbl.textContent = '₱' + parseFloat(price).toFixed(2) + ' / unit';
            selectedLbl.classList.remove('d-none');
            display.classList.remove('is-invalid');
            if (invalidEl) invalidEl.style.display = '';
            closeDropdown();
            recalcGrandTotal();
        }

        // ── Open / Close ─────────────────────────────────────────────
        function openDropdown() {
            if (isOpen) return;
            isOpen = true;
            combobox.classList.add('open');
            renderList(''); // Always render all first when opening, highlight selection
            // Scroll active item into view
            const active = list.querySelector('.med-dd-item.active');
            if (active) active.scrollIntoView({ block: 'nearest' });
        }

        function closeDropdown() {
            if (!isOpen) return;
            isOpen = false;
            combobox.classList.remove('open');
            // Revert display value to the selected medicine if search is aborted/blurred
            if (!hiddenId.value) {
                display.value = '';
                selectedLbl.textContent = '';
                selectedLbl.classList.add('d-none');
            } else {
                display.value = display.dataset.lastName || '';
            }
            recalcGrandTotal();
        }

        // ── Events on display input ──────────────────────────────────
        display.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!isOpen) openDropdown();
        });

        // Click on input wrapper / chevron toggles dropdown
        combobox.querySelector('.med-input-wrapper').addEventListener('click', (e) => {
            e.stopPropagation();
            isOpen ? closeDropdown() : openDropdown();
        });

        // Typing in the display input filters and clears the selection
        display.addEventListener('input', () => {
            if (display.value.trim() === '') {
                hiddenId.value = '';
                hiddenPrice.value = '0';
                display.dataset.lastName = '';
                selectedLbl.textContent = '';
                selectedLbl.classList.add('d-none');
                recalcGrandTotal();
            }
            if (!isOpen) openDropdown();
            renderList(display.value);
        });

        // Clicking inside the dropdown body must not close it
        dropdown.addEventListener('click', (e) => e.stopPropagation());

        // Close on outside click
        document.addEventListener('click', () => closeDropdown());

        // ── Public API (mirrors medicine.js combobox) ────────────────
        combobox._reset = function () {
            hiddenId.value = '';
            hiddenPrice.value = '0';
            display.value = '';
            display.dataset.lastName = '';
            selectedLbl.textContent = '';
            selectedLbl.classList.add('d-none');
            display.classList.remove('is-invalid');
            if (invalidEl) invalidEl.style.display = '';
            recalcGrandTotal();
            closeDropdown();
        };

        combobox._validate = function () {
            if (!hiddenId.value) {
                display.classList.add('is-invalid');
                if (invalidEl) invalidEl.style.display = 'block';
                return false;
            }
            display.classList.remove('is-invalid');
            if (invalidEl) invalidEl.style.display = '';
            return true;
        };

        return combobox;
    }

    // ── Grand total recalculation ────────────────────────────────────
    function recalcGrandTotal() {
        let grand = 0;
        document.querySelectorAll('#med-items-tbody tr').forEach(tr => {
            const price = parseFloat(tr.querySelector('.med-row-hidden-price')?.value || 0);
            const qty = parseInt(tr.querySelector('.med-row-qty')?.value) || 0;
            const line = qty * price;
            const lineEl = tr.querySelector('.med-row-line-total-display');
            if (lineEl) {
                lineEl.textContent = '₱' + line.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                lineEl.classList.toggle('line-total-zero', line === 0);
            }
            grand += line;
        });
        const gt = document.getElementById('new_grand_total_display');
        if (gt) gt.textContent = '₱' + grand.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const gtHidden = document.getElementById('new_grand_total');
        if (gtHidden) gtHidden.value = grand.toFixed(2);
    }

    // ── Build one medicine row ───────────────────────────────────────
    function buildMedRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="med-picker-cell">
                <input type="hidden" class="med-row-hidden-id"    value="">
                <input type="hidden" class="med-row-hidden-price" value="0">
                <div class="med-combobox">
                    <div class="med-input-wrapper">
                        <input type="text"
                               class="form-control med-display-input"
                               placeholder="Select medicine..."
                               autocomplete="off">
                        <i class="bi bi-chevron-down med-chevron"></i>
                    </div>
                    <div class="med-dropdown">
                        <div class="med-dropdown-list"></div>
                    </div>
                </div>
                <div class="med-selected-label d-none"></div>
                <div class="med-invalid">Please select a medicine.</div>
            </td>
            <td class="med-qty-cell">
                <input type="number" class="form-control form-control-sm med-row-qty" min="1" value="1" required>
                <div class="invalid-feedback">Min 1.</div>
            </td>
            <td class="med-linetotal-cell">
                <span class="med-row-line-total-display line-total-zero">₱0.00</span>
            </td>
            <td class="med-del-cell text-center">
                <button type="button" class="btn btn-sm btn-danger remove-med-row-btn" title="Remove medicine">
                    <i class="bi bi-trash"></i>
                </button>
            </td>`;

        // Wire qty change
        tr.querySelector('.med-row-qty').addEventListener('input', recalcGrandTotal);

        // Wire combobox
        wireMedCombobox(tr);

        // Wire remove button
        tr.querySelector('.remove-med-row-btn').addEventListener('click', () => {
            const tbody = document.getElementById('med-items-tbody');
            if (tbody && tbody.querySelectorAll('tr').length <= 1) {
                showToast('A purchase must have at least one medicine.', 'warning');
                return;
            }
            pendingRemoveMedRow = tr;
            removeMedRowModal?.show();
        });

        return tr;
    }

    /** Reset medicine items table to a single blank row */
    function resetMedItems() {
        const tbody = document.getElementById('med-items-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        tbody.appendChild(buildMedRow());
        recalcGrandTotal();
    }

    // "Add Medicine" button
    document.getElementById('add-med-row-btn')?.addEventListener('click', () => {
        const tbody = document.getElementById('med-items-tbody');
        if (tbody) {
            tbody.appendChild(buildMedRow());
            recalcGrandTotal();
        }
    });

    // Confirm remove medicine row
    confirmRemoveMedRowBtn?.addEventListener('click', () => {
        if (!pendingRemoveMedRow) return;
        pendingRemoveMedRow.remove();
        recalcGrandTotal();
        removeMedRowModal?.hide();
        pendingRemoveMedRow = null;
    });

    removeMedRowModalEl?.addEventListener('hidden.bs.modal', () => {
        pendingRemoveMedRow = null;
    });

    // ── Note rows ────────────────────────────────────────────────────
    function buildNoteRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select class="form-select form-select-sm">
                    <option value="dosage">Dosage</option>
                    <option value="instructions">Instructions</option>
                    <option value="warnings">Warnings</option>
                    <option value="other">Other</option>
                </select>
            </td>
            <td><textarea class="notes-table-textarea" placeholder="Enter note details here..."></textarea></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-note-btn">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </td>`;
        tr.querySelector('.remove-note-btn').addEventListener('click', () => {
            pendingRemoveNoteRow = tr;
            removeNoteModal?.show();
        });
        return tr;
    }

    function resetNotesTable() {
        const tbody = document.getElementById('notes-table-body');
        if (!tbody) return;
        tbody.innerHTML = '';
        tbody.appendChild(buildNoteRow());
    }

    document.getElementById('add-note-row-btn')?.addEventListener('click', () => {
        const tbody = document.getElementById('notes-table-body');
        if (tbody) tbody.appendChild(buildNoteRow());
    });

    confirmRemoveNoteBtn?.addEventListener('click', () => {
        if (!pendingRemoveNoteRow) return;
        const tbody = document.getElementById('notes-table-body');
        pendingRemoveNoteRow.remove();
        if (tbody && !tbody.querySelector('tr')) tbody.appendChild(buildNoteRow());
        removeNoteModal?.hide();
        pendingRemoveNoteRow = null;
    });

    removeNoteModalEl?.addEventListener('hidden.bs.modal', () => {
        pendingRemoveNoteRow = null;
    });

    // ── Pagination ───────────────────────────────────────────────────
    function renderPagination(totalPages) {
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
                if (!disabled) { purchasePage = page; renderPurchaseRows(); }
            });
            ul.appendChild(li);
        };
        add('&laquo;', purchasePage - 1, purchasePage === 1);
        for (let i = Math.max(1, purchasePage - 2); i <= Math.min(totalPages, purchasePage + 2); i++) {
            add(i, i, false, i === purchasePage);
        }
        add('&raquo;', purchasePage + 1, purchasePage === totalPages);
        pagination.appendChild(ul);
    }

    function renderPurchaseRows() {
        tableBody.innerHTML = '';
        const totalPages = Math.max(1, Math.ceil(purchaseRows.length / perPage));
        if (purchasePage > totalPages) purchasePage = totalPages;
        const start = (purchasePage - 1) * perPage;
        const rows = purchaseRows.slice(start, start + perPage);
        if (pageInfo) pageInfo.textContent = purchaseRows.length
            ? `Showing ${start + 1}-${Math.min(start + perPage, purchaseRows.length)} of ${purchaseRows.length}`
            : 'No purchases';
        if (rows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-5">No purchases found.</td></tr>';
            renderPagination(0);
            return;
        }
        rows.forEach(row => tableBody.appendChild(row));
        renderPagination(totalPages);
    }

    // ── Load purchases ───────────────────────────────────────────
    let purchasesAbortController = null;

    function loadPurchases(query = '', medicineId = '', status = '') {
        // Cancel any request that's still in flight so responses can't pile up
        if (purchasesAbortController) purchasesAbortController.abort();
        purchasesAbortController = new AbortController();

        tableLoading.classList.add('show');
        noDataRow?.classList.add('d-none');
        tableBody.innerHTML = '';

        let url = `../Admin/api/purchases.php?per_page=9999`;
        if (query) url += `&search=${encodeURIComponent(query)}`;
        if (medicineId) url += `&medicine_id=${medicineId}`;
        if (status) url += `&status=${status}`;

        fetch(url, { signal: purchasesAbortController.signal })
            .then(r => r.json())
            .then(res => {
                const purchases = res.data || [];
                if (purchases.length === 0) {
                    purchaseRows = [];
                    renderPurchaseRows();
                    tableLoading.classList.remove('show');
                    return;
                }
                purchaseRows = [];
                purchases.forEach(p => {
                    const statusBadge = p.status === 'pending' ? 'status-pending'
                        : p.status === 'filled' ? 'status-filled'
                            : 'status-cancelled';
                    const notesPreview = p.notes
                        ? `<span title="${p.notes.replace(/"/g, '&quot;')}">${p.notes.substring(0, 30)}${p.notes.length > 30 ? '...' : ''}</span>`
                        : '-';

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><span class="badge bg-info">${p.purchase_number}</span></td>
                        <td>${p.medicine_name}</td>
                        <td><span class="badge bg-secondary">${p.quantity}</span></td>
                        <td>${new Date(p.purchase_date).toLocaleDateString()}</td>
                        <td><span class="status-badge ${statusBadge}">${p.status.charAt(0).toUpperCase() + p.status.slice(1)}</span></td>
                        <td class="text-success fw-bold">₱${parseFloat(p.total_cost).toFixed(2)}</td>
                        <td>${notesPreview}</td>
                        <td class="action-cell"></td>`;

                    const cell = row.querySelector('.action-cell');

                    if (p.status === 'pending') {
                        const ringUpBtn = document.createElement('button');
                        ringUpBtn.className = 'btn btn-sm btn-success action-btn me-1';
                        ringUpBtn.title = 'Ring up this purchase on the Sales page';
                        ringUpBtn.innerHTML = '<i class="bi bi-cart-check"></i> Ring Up';
                        ringUpBtn.addEventListener('click', () => handleRingUp(p.id));
                        cell.appendChild(ringUpBtn);
                    }

                    const receiptBtn = document.createElement('button');
                    receiptBtn.className = 'btn btn-sm btn-info action-btn me-1';
                    receiptBtn.title = 'View Receipt';
                    receiptBtn.innerHTML = '<i class="bi bi-receipt"></i> Receipt';
                    receiptBtn.addEventListener('click', () => printPurchase(p.id));
                    cell.appendChild(receiptBtn);

                    if (p.status === 'pending') {
                        // Editing (including its status field) only works while a purchase is
                        // still pending — once it's filled/cancelled, purchases.php rejects any
                        // update, so there's no point offering the button here.
                        const editBtn = document.createElement('button');
                        editBtn.className = 'btn btn-sm btn-warning action-btn me-1';
                        editBtn.title = 'Edit';
                        editBtn.innerHTML = '<i class="bi bi-pencil"></i> Edit';
                        editBtn.addEventListener('click', () => editPurchase(p.id));
                        cell.appendChild(editBtn);
                    }

                    const delBtn = document.createElement('button');
                    delBtn.className = 'btn btn-sm btn-danger action-btn';
                    delBtn.title = 'Delete';
                    delBtn.innerHTML = '<i class="bi bi-trash"></i> Delete';
                    delBtn.addEventListener('click', () => deletePurchase(p.id));
                    cell.appendChild(delBtn);

                    purchaseRows.push(row);
                });
                purchasePage = 1;
                renderPurchaseRows();
                tableLoading.classList.remove('show');
            })
            .catch(err => {
                if (err.name === 'AbortError') return; // superseded by a newer request, ignore
                showToast('Failed to load purchases', 'danger');
                tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-5">Failed to load purchases.</td></tr>';
                tableLoading.classList.remove('show');
            });
    }

    // ── Ring up a purchase on the Sales page ───────────────────────
    // A purchase is only ever finalized through Sales checkout now — this just sends the
    // cashier there with the purchase pre-selected, instead of finalizing it here.
    // Adjust the path below ("sales.php") if your Sales page lives somewhere else relative
    // to this one — it mirrors how printPurchase() below already links to this same folder.
    function handleRingUp(purchaseId) {
        window.location.href = `sales.php?purchase_id=${encodeURIComponent(purchaseId)}`;
    }

    // ── Receipt / Print ──────────────────────────────────────────────
    async function printPurchase(id) {
        try {
            const res = await fetch(`../Admin/api/purchases.php?id=${id}`);
            const p = await res.json();
            if (p.error) { showToast(p.error, 'danger'); return; }

            document.getElementById('rx-print-date').textContent = 'Printed: ' + new Date().toLocaleString();
            document.getElementById('rx-number').textContent = '📋 ' + p.purchase_number;
            document.getElementById('rx-footer-number').textContent = 'Purchase No: ' + p.purchase_number;
            document.getElementById('rx-medicine').textContent = p.medicine_name;
            document.getElementById('rx-quantity').textContent = p.quantity + ' unit(s)';
            document.getElementById('rx-date').textContent = new Date(p.purchase_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('rx-total-cost').textContent = '₱' + parseFloat(p.total_cost).toFixed(2);

            const statusEl = document.getElementById('rx-status');
            statusEl.className = p.status === 'filled' ? 'rx-status-filled'
                : p.status === 'pending' ? 'rx-status-pending'
                    : 'rx-status-cancelled';
            statusEl.textContent = p.status.charAt(0).toUpperCase() + p.status.slice(1);

            document.getElementById('rx-pharmacist').textContent = p.pharmacist_name || p.filled_by_name || 'Pharmacist on Duty';

            const notesWrap = document.getElementById('rx-notes-wrap');
            const notesEl = document.getElementById('rx-notes');
            if (p.notes && p.notes.trim()) {
                notesEl.textContent = p.notes.trim();
                notesWrap.classList.remove('d-none');
            } else {
                notesWrap.classList.add('d-none');
            }
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        } catch (err) {
            console.error(err);
            showToast('Failed to load purchase for printing', 'danger');
        }
    }

    document.getElementById('btn-print-receipt')?.addEventListener('click', () => window.print());

    document.getElementById('btn-download-pdf')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-download-pdf');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
        try {
            const receipt = document.getElementById('receipt-printable');
            const canvas = await html2canvas(receipt, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jspdf;
            const pdfWidth = 80;
            const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
            const pdf = new jsPDF({ unit: 'mm', format: [pdfWidth, pdfHeight] });
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            const rxNum = document.getElementById('rx-number').textContent.replace(/[^A-Z0-9-]/gi, '').trim();
            pdf.save(`Purchase_${rxNum || 'Receipt'}.pdf`);
        } catch (err) {
            console.error(err);
            showToast('Failed to generate PDF', 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    });

    // ── Edit Purchase ────────────────────────────────────────────
    async function editPurchase(id) {
        try {
            const res = await fetch(`../Admin/api/purchases.php?id=${id}`);
            const data = await res.json();
            if (data.error) { showToast(data.error, 'danger'); return; }
            const p = data;
            document.getElementById('edit_id').value = p.id;
            document.getElementById('edit_purchase_number').value = p.purchase_number;
            document.getElementById('edit_medicine_id').value = p.medicine_id;
            document.getElementById('edit_quantity').value = p.quantity;
            document.getElementById('edit_purchase_date').value = p.purchase_date;

            // This endpoint only ever accepts 'pending' or 'cancelled' — 'filled' is now only
            // reachable through Sales checkout (Ring Up), so don't even offer it as a choice here.
            // It's stripped every time the modal opens rather than once, since the <select>'s
            // markup lives in the page HTML and may still list it.
            const statusSelect = document.getElementById('edit_status');
            if (statusSelect) {
                Array.from(statusSelect.options)
                    .filter(opt => opt.value === 'filled')
                    .forEach(opt => opt.remove());
            }
            document.getElementById('edit_status').value = p.status === 'filled' ? 'pending' : p.status;
            document.getElementById('edit_total_cost').value = parseFloat(p.total_cost).toFixed(2);
            document.getElementById('edit_notes').value = p.notes || '';
            calculateTotalCost('edit_medicine_id', 'edit_quantity', 'edit_total_cost');
            new bootstrap.Modal(document.getElementById('editPurchaseModal')).show();
        } catch (err) {
            console.error(err);
            showToast('Failed to load purchase details', 'danger');
        }
    }

    document.getElementById('editPurchaseForm')?.addEventListener('submit', (e) => {
        e.preventDefault();
        if (!e.target.checkValidity()) { e.target.classList.add('was-validated'); return; }
        const id = document.getElementById('edit_id').value;
        const data = {
            id: +id,
            medicine_id: +document.getElementById('edit_medicine_id').value,
            quantity: +document.getElementById('edit_quantity').value,
            purchase_date: document.getElementById('edit_purchase_date').value,
            status: document.getElementById('edit_status').value,
            total_cost: parseFloat(document.getElementById('edit_total_cost').value),
            notes: document.getElementById('edit_notes').value.trim() || null
        };
        fetch('../Admin/api/purchases.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) showToast(res.error, 'danger');
                else {
                    showToast('Purchase updated successfully!', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editPurchaseModal')).hide();
                    loadPurchases();
                }
            })
            .catch(() => showToast('Failed to update purchase', 'danger'));
    });

    // ── Delete Purchase ──────────────────────────────────────────
    function deletePurchase(id) {
        pendingDeletePurchaseId = id;
        deletePurchaseModal?.show();
    }

    confirmDeletePurchaseBtn?.addEventListener('click', () => {
        if (!pendingDeletePurchaseId) return;
        const orig = confirmDeletePurchaseBtn.innerHTML;
        confirmDeletePurchaseBtn.disabled = true;
        confirmDeletePurchaseBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
        fetch('../Admin/api/purchases.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: pendingDeletePurchaseId })
        })
            .then(r => r.json())
            .then(res => {
                if (res.error) showToast(res.error, 'danger');
                else {
                    deletePurchaseModal?.hide();
                    showToast('Purchase deleted successfully', 'success');
                    loadPurchases();
                }
            })
            .catch(() => showToast('Failed to delete purchase', 'danger'))
            .finally(() => {
                confirmDeletePurchaseBtn.disabled = false;
                confirmDeletePurchaseBtn.innerHTML = orig;
                pendingDeletePurchaseId = null;
            });
    });

    deletePurchaseModalEl?.addEventListener('hidden.bs.modal', () => {
        pendingDeletePurchaseId = null;
    });

    // ════════════════════════════════════════════════════════════════
    //  NEW PURCHASE FORM — multi-medicine submit
    // ════════════════════════════════════════════════════════════════
    document.getElementById('newPurchaseForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;

        if (!form.checkValidity()) { form.classList.add('was-validated'); return; }

        // Validate every medicine row using the combobox _validate API
        const medRows = Array.from(document.querySelectorAll('#med-items-tbody tr'));
        let medValid = true;
        medRows.forEach(tr => {
            const combobox = tr.querySelector('.med-combobox');
            const qty = tr.querySelector('.med-row-qty');
            if (!combobox._validate()) medValid = false;
            if (!qty.value || parseInt(qty.value) < 1) { qty.classList.add('is-invalid'); medValid = false; }
            else qty.classList.remove('is-invalid');
        });
        if (!medValid) {
            showToast('Please fill in all medicine rows correctly.', 'warning');
            return;
        }

        // Check for duplicate medicines
        const selectedIds = medRows.map(tr => tr.querySelector('.med-row-hidden-id').value);
        const uniqueIds = new Set(selectedIds);
        if (uniqueIds.size !== selectedIds.length) {
            showToast('Duplicate medicines detected. Please merge or remove duplicate rows.', 'warning');
            return;
        }

        // Build shared notes string (null-safe)
        const notes = Array.from(document.querySelectorAll('#notes-table-body tr')).map(row => {
            const sel = row.querySelector('select');
            const ta = row.querySelector('textarea');
            if (!sel || !ta) return '';
            const type = sel.selectedOptions[0]?.text || '';
            const text = ta.value.trim();
            return text ? `${type}: ${text}` : '';
        }).filter(Boolean).join('\n');

        const purchaseDate = document.getElementById('new_purchase_date').value;

        const submitBtn = document.querySelector('#newPurchaseModal button[type="submit"]');
        const origLabel = submitBtn ? submitBtn.innerHTML : "";
        if (submitBtn) submitBtn.disabled = true;
        if (submitBtn) submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating...';

        const results = [];
        let hasError = false;

        for (const tr of medRows) {
            const hiddenId = tr.querySelector('.med-row-hidden-id');
            const hiddenPrice = tr.querySelector('.med-row-hidden-price');
            const qty = tr.querySelector('.med-row-qty');
            const medName = tr.querySelector('.med-display-input')?.value || 'Unknown';
            const lineTotal = parseFloat(hiddenPrice.value || 0) * (parseInt(qty.value) || 0);
            const payload = {
                medicine_id: +hiddenId.value,
                quantity: +qty.value,
                purchase_date: purchaseDate,
                total_cost: lineTotal,
                notes: notes || null
            };
            try {
                const res = await fetch('../Admin/api/purchases.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.error) {
                    showToast(`Error for ${medName}: ${data.error}`, 'danger');
                    hasError = true;
                } else {
                    results.push(data.purchase_number);
                }
            } catch {
                showToast(`Network error saving ${medName}`, 'danger');
                hasError = true;
            }
        }

        if (submitBtn) submitBtn.disabled = false;
        if (submitBtn) submitBtn.innerHTML = origLabel;

        if (results.length > 0) {
            const nums = results.join(', ');
            showToast(
                results.length === 1
                    ? `Purchase #${nums} created!`
                    : `${results.length} purchases created: ${nums}`,
                'success'
            );
        }

        if (!hasError) {
            bootstrap.Modal.getInstance(document.getElementById('newPurchaseModal'))?.hide();
            form.reset();
            form.classList.remove('was-validated');
            resetMedItems();
            resetNotesTable();
        }

        loadPurchases();
    });

    // Reset rows when New Purchase modal opens
    document.getElementById('newPurchaseModal')?.addEventListener('show.bs.modal', () => {
        resetMedItems();
        resetNotesTable();
    });

    // ── Debounced filters ────────────────────────────────────────────
    const debounce = (fn, delay) => {
        let t;
        return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
    };

    const throttle = (fn, interval) => {
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
    };

    // ── SSE real-time updates ────────────────────────────────────────
    // Throttled to at most once every 3s: if api/realtime.php ever fires
    // 'update' in a tight loop, this stops it from flooding the page with
    // overlapping full-table reloads and freezing the tab.
    const throttledLoadPurchases = throttle(() => loadPurchases(), 3000);
    if (typeof EventSource !== 'undefined') {
        const source = new EventSource('../Admin/api/realtime.php');
        source.addEventListener('update', throttledLoadPurchases);
        source.onerror = () => source.close();
    }

    searchInput?.addEventListener('input',
        debounce(() => loadPurchases(searchInput.value, medicineFilter.value, statusFilter.value), 300));
    medicineFilter?.addEventListener('change',
        () => loadPurchases(searchInput.value, medicineFilter.value, statusFilter.value));
    statusFilter?.addEventListener('change',
        () => loadPurchases(searchInput.value, medicineFilter.value, statusFilter.value));

    // ── Init ─────────────────────────────────────────────────────────
    resetNotesTable();
    loadMedicines().then(() => {
        attachCalculators();
        loadPurchases();
    });
});