// assets/js/sup_reports.js
document.addEventListener('DOMContentLoaded', () => {
    let currentReportType = 'inventory';
    let currentMedicineId = 0;
    let reportData = [];
    let currentPage = 1;
    let suppressLoadToast = false;
    const fmt = (v) => window.SupUtils ? window.SupUtils.formatCurrency(v) : '₱' + parseFloat(v || 0).toFixed(2);
    const fmtDate = (v) => window.SupUtils ? window.SupUtils.formatDate(v) : (v ? new Date(v).toLocaleDateString() : 'N/A');
    const fmtDateTime = (v) => window.SupUtils ? window.SupUtils.formatDateTime(v) : (v ? new Date(v).toLocaleString() : 'N/A');
    const medicineSearchInput = document.getElementById('medicine-search');
    const medicineFilterInput = document.getElementById('medicine-filter');
    const medicineSuggestionsBox = document.getElementById('medicine-search-suggestions');

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

    function setMedicineFilter(value, label) {
        currentMedicineId = value || 0;
        if (medicineFilterInput) {
            medicineFilterInput.value = String(currentMedicineId || '');
        }
        if (medicineSearchInput) {
            medicineSearchInput.value = label || '';
        }
    }

    function updateFilterIndicator() {
        const indicator = document.getElementById('filter-indicator');
        const text = document.getElementById('filter-text');
        const searchStatusText = document.getElementById('search-status-text');
        const type = document.getElementById('report-type') ? document.getElementById('report-type').value : 'inventory';
        const medName = medicineSearchInput && medicineSearchInput.value.trim()
            ? medicineSearchInput.value.trim()
            : 'All Medicines';

        let filterText = type.replace(/\b\w/g, l => l.toUpperCase()).replace(/([A-Z])/g, ' $1').trim();
        if (currentMedicineId > 0 && medName !== 'All Medicines') {
            filterText += ` for ${escapeHtml(medName)}`;
        }

        if (text) {
            text.textContent = filterText;
        }
        if (indicator) {
            indicator.classList.remove('d-none');
        }

        if (searchStatusText) {
            if (medicineSearchInput && medicineSearchInput.value.trim()) {
                searchStatusText.textContent = `Filtered by: ${medicineSearchInput.value.trim()}`;
                searchStatusText.classList.add('text-primary');
            } else {
                searchStatusText.textContent = 'Showing all medicines';
                searchStatusText.classList.remove('text-primary');
            }
        }
    }

    function hideMedicineSuggestions() {
        if (medicineSuggestionsBox) {
            medicineSuggestionsBox.classList.remove('show');
            medicineSuggestionsBox.innerHTML = '';
        }
    }

    function renderMedicineSuggestions(matches) {
        if (!medicineSuggestionsBox) return;
        medicineSuggestionsBox.innerHTML = '';
        if (!matches.length) {
            hideMedicineSuggestions();
            return;
        }

        matches.slice(0, 8).forEach((item) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'medicine-search-suggestion';
            btn.setAttribute('role', 'option');
            btn.innerHTML = `<span>${escapeHtml(item.name || 'Unknown medicine')}</span><small>Stock: ${escapeHtml(item.stock ?? item.quantity ?? 0)}</small>`;
            btn.addEventListener('click', () => {
                medicineSearchInput.value = item.name || '';
                setMedicineFilter(item.id, item.name || '');
                hideMedicineSuggestions();
                currentPage = 1;
                loadReport();
            });
            medicineSuggestionsBox.appendChild(btn);
        });

        medicineSuggestionsBox.classList.add('show');
    }

    function fetchMedicineSuggestions(term) {
        const cleanTerm = term.trim();
        if (!cleanTerm) {
            hideMedicineSuggestions();
            return;
        }

        fetch(`api/sup_medicines.php?limit=10&page=1&search=${encodeURIComponent(cleanTerm)}&stock_filter=`)
            .then(response => response.json())
            .then(data => {
                const matches = Array.isArray(data?.data) ? data.data : [];
                renderMedicineSuggestions(matches);
            })
            .catch(() => {
                hideMedicineSuggestions();
            });
    }

    function doMedicineSearch(options = {}) {
        const { silent = false } = options;
        const term = (medicineSearchInput?.value || '').trim();
        hideMedicineSuggestions();
        if (!term) {
            setMedicineFilter(0, '');
            currentPage = 1;
            suppressLoadToast = silent;
            loadReport();
            return;
        }

        suppressLoadToast = silent;

        fetch(`api/sup_medicines.php?limit=10000&page=1&search=${encodeURIComponent(term)}&stock_filter=`)
            .then(response => response.json())
            .then(data => {
                const matches = Array.isArray(data?.data) ? data.data : [];
                if (!matches.length) {
                    setMedicineFilter(0, term);
                    currentPage = 1;
                    suppressLoadToast = silent;
                    loadReport();
                    return;
                }

                const found = matches.find(item => item.name && item.name.toLowerCase() === term.toLowerCase())
                    || matches.find(item => item.name && item.name.toLowerCase().includes(term.toLowerCase()))
                    || matches[0];

                setMedicineFilter(found.id, found.name || term);
                currentPage = 1;
                loadReport();
            })
            .catch(err => {
                console.error('Medicine search failed:', err);
                if (!silent) {
                    showToast('Unable to search medicine. Please try again.', 'danger');
                }
            });
    }

    const clearSearchBtn = document.getElementById('clear-report-search');

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            if (medicineSearchInput) {
                medicineSearchInput.value = '';
            }
            clearSearchBtn.style.display = 'none';
            setMedicineFilter(0, '');
            hideMedicineSuggestions();
            currentPage = 1;
            suppressLoadToast = true;
            loadReport();
        });
    }

    if (medicineSearchInput) {
        medicineSearchInput.addEventListener('input', (event) => {
            const term = event.target.value.trim();
            if (clearSearchBtn) clearSearchBtn.style.display = term ? 'block' : 'none';
            if (!term) {
                setMedicineFilter(0, '');
                hideMedicineSuggestions();
                currentPage = 1;
                suppressLoadToast = true;
                loadReport();
                return;
            }
            clearTimeout(medicineSearchInput.searchTimer);
            medicineSearchInput.searchTimer = setTimeout(() => doMedicineSearch({ silent: true }), 300);
        });

        medicineSearchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                doMedicineSearch();
            }
        });

        medicineSearchInput.addEventListener('blur', () => {
            window.setTimeout(() => hideMedicineSuggestions(), 150);
        });
    }

    function renderReportPagination(paginationData) {
        const paginationEl = document.getElementById('pagination');
        if (!paginationEl) return;
        const totalPages = Math.max(1, Number(paginationData?.total_pages || 1));
        const current = Math.max(1, Number(paginationData?.current_page || currentPage));
        const totalItems = Number(paginationData?.total_items || 0);
        const perPage = Number(paginationData?.items_per_page || window.SupUtils?.getRecordsPerPage() || window.RECORDS_PER_PAGE || 25);
        const start = totalItems ? ((current - 1) * perPage) + 1 : 0;
        const end = totalItems ? Math.min(current * perPage, totalItems) : 0;
        const info = document.getElementById('pagination-info');
        if (info) info.textContent = totalItems ? `Showing ${start}-${end} of ${totalItems}` : 'No records';
        paginationEl.innerHTML = '';
        if (totalPages <= 1) return;

        const add = (label, page, disabled = false, active = false) => {
            const item = document.createElement('li');
            item.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            item.innerHTML = `<a class="page-link" href="#">${label}</a>`;
            item.addEventListener('click', event => {
                event.preventDefault();
                if (!disabled) {
                    currentPage = page;
                    loadReport();
                }
            });
            paginationEl.appendChild(item);
        };

        add('&laquo;', current - 1, current === 1);
        for (let page = Math.max(1, current - 2); page <= Math.min(totalPages, current + 2); page++) {
            add(page, page, false, page === current);
        }
        add('&raquo;', current + 1, current === totalPages);
    }

    window.loadReport = function() {
        currentReportType = document.getElementById('report-type').value;
        currentMedicineId = parseInt(document.getElementById('medicine-filter').value) || 0;
        const reportBody = document.querySelector('.admin-table-card > .card-body');
        if (reportBody) {
            reportBody.classList.toggle('medicine-report-view', currentReportType === 'inventory');
        }
        const searchTerm = (medicineSearchInput?.value || '').trim();
        const shouldShowLoadToast = !suppressLoadToast;

        const header = document.getElementById('report-table-header');
        const body = document.getElementById('report-table-body');

        header.innerHTML = '';
        body.innerHTML = '';

        const params = new URLSearchParams({
            type: currentReportType,
            medicine_id: currentMedicineId,
            search: searchTerm,
            page: currentPage,
            limit: window.SupUtils ? window.SupUtils.getRecordsPerPage() : (window.RECORDS_PER_PAGE || 25)
        });

        fetch(`api/sup_reports.php?${params}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                reportData = data.data || [];
                const paginationData = data.pagination || { total_items: 0, total_pages: 1, current_page: currentPage };

                if (!data.success) {
                    body.innerHTML = `
                        <tr>
                            <td colspan="10" class="text-center text-danger py-4">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error: ${escapeHtml(data.error || 'Failed to load report')}
                            </td>
                        </tr>
                    `;
                    renderReportPagination(paginationData);
                    return;
                }

                if (reportData.length === 0) {
                    body.innerHTML = `
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p class="mt-3 mb-2"><strong>No data found</strong></p>
                                <small>Try adjusting your filters</small>
                            </td>
                        </tr>
                    `;
                    renderReportPagination(paginationData);
                    return;
                }

                if (currentReportType === 'inventory') {
                    header.innerHTML = `
                        <tr>
                            <th>Medicine Name</th>
                            <th>Barcode</th>
                            <th>Remaining Stock</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Date Acquired</th>
                            <th>Expiry Date</th>
                        </tr>
                    `;
                    body.innerHTML = reportData.map(row => `
                        <tr>
                            <td><strong>${escapeHtml(row['Medicine Name'] || row.name || 'N/A')}</strong></td>
                            <td>${escapeHtml(row.barcode || 'N/A')}</td>
                            <td><strong class="text-primary">${row['Remaining Stock'] || row.quantity || 0}</strong></td>
                            <td>${escapeHtml(row.type || 'N/A')}</td>
                            <td>${escapeHtml(row.description || 'N/A')}</td>
                            <td>${fmtDate(row['Date Acquired'])}</td>
                            <td>${fmtDate(row['Expiry Date'])}</td>
                        </tr>
                    `).join('');
                } else if (currentReportType === 'transactions') {
                    header.innerHTML = `
                        <tr>
                            <th>Order/Invoice</th>
                            <th>Medicine</th>
                            <th>Action</th>
                            <th>Quantity</th>
                            <th>Total Cost</th>
                            <th>Cashier</th>
                            <th>Timestamp</th>
                        </tr>
                    `;
                    body.innerHTML = reportData.map(row => {
                        const date = new Date(row.timestamp || '');
                        const formattedDate = fmtDate(row.timestamp);
                        const formattedTime = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        const orderDisplay = row.order_id ? `<strong>#${row.order_id}</strong>` : 'Walk-in';
                        const totalCost = parseFloat(row.total_cost || 0).toFixed(2);
                        const cashier = escapeHtml(row.cashier_name || 'Unknown');

                        return `
                            <tr>
                                <td>${orderDisplay} / ${escapeHtml(row.invoice_number || 'N/A')}</td>
                                <td><strong>${escapeHtml(row.medicine_name || 'N/A')}</strong></td>
                                <td><span class="status-badge status-remove">SALE</span></td>
                                <td><strong class="text-primary">${row.quantity || 0}</strong></td>
                                <td><strong class="text-success">${fmt(totalCost)}</strong></td>
                                <td>${cashier}</td>
                                <td>
                                    <div><strong>${formattedDate}</strong></div>
                                    <small class="text-muted">${formattedTime}</small>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }

                updateFilterIndicator();
                renderReportPagination(paginationData);
                if (shouldShowLoadToast && paginationData.total_items > 0) {
                    showToast(`Loaded ${reportData.length} record(s) of ${paginationData.total_items}`, 'success');
                }
                suppressLoadToast = false;
            })
            .catch(err => {
                body.innerHTML = `
                    <tr>
                        <td colspan="10" class="text-center text-danger py-4">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Failed to load report: ${escapeHtml(err.message)}
                        </td>
                    </tr>
                `;
                renderReportPagination({ total_items: 0, total_pages: 1, current_page: currentPage });
                console.error('Error:', err);
                if (!suppressLoadToast) {
                    showToast('Failed to load report', 'danger');
                }
                suppressLoadToast = false;
            });
    };

    // Fetch full report data for exports
    async function fetchFullReport() {
        const params = new URLSearchParams({
            type: currentReportType,
            medicine_id: currentMedicineId,
            page: 1,
            limit: 999999
        });
        try {
            const response = await fetch(`api/sup_reports.php?${params}`);
            if (!response.ok) throw new Error('Fetch failed');
            const data = await response.json();
            return data.success ? data.data : [];
        } catch (err) {
            showToast('Failed to fetch full data: ' + err.message, 'danger');
            return [];
        }
    }

    // Preview report (current page)
    window.previewReport = function() {
        if (reportData.length === 0) {
            showToast('No data to preview', 'warning');
            return;
        }
        const modalHeader = document.getElementById('preview-table-header');
        const modalBody = document.getElementById('preview-table-body');
        const title = document.getElementById('preview-title');
        title.textContent = `${currentReportType.replace(/([A-Z])/g, ' $1').toUpperCase()} Preview`;
        modalHeader.innerHTML = document.getElementById('report-table-header').innerHTML;
        modalBody.innerHTML = document.getElementById('report-table-body').innerHTML;
        new bootstrap.Modal(document.getElementById('reportPreviewModal')).show();
    };

    // Print report (current page)
    window.printReport = function() {
        window.print();
    };

    // Generate PDF (full data)
    window.generatePDF = async function() {
        const fullData = await fetchFullReport();
        if (fullData.length === 0) {
            showToast('No data to export', 'warning');
            return;
        }
        if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
            showToast('PDF library failed to load. Check your internet connection and try again.', 'danger');
            return;
        }

        try {
            const doc = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            doc.setFontSize(16);
            doc.text(`${currentReportType.toUpperCase()} Report`, 10, 10);

            let head = [[]];
            let body = [];
            if (currentReportType === 'inventory') {
                head = [['Medicine Name', 'Barcode', 'Remaining Stock', 'Type', 'Description', 'Date Acquired', 'Expiry Date']];
                body = fullData.map(row => [
                    row['Medicine Name'] || row.name || 'N/A',
                    row.barcode || 'N/A',
                    row['Remaining Stock'] || row.quantity || 0,
                    row.type || 'N/A',
                    row.description || 'N/A',
                    row['Date Acquired'] ? fmtDate(row['Date Acquired']) : 'N/A',
                    row['Expiry Date'] ? fmtDate(row['Expiry Date']) : 'N/A'
                ]);
            } else if (currentReportType === 'transactions') {
                head = [['Order/Invoice', 'Medicine', 'Action', 'Quantity', 'Total Cost', 'Cashier', 'Timestamp']];
                body = fullData.map(row => [
                    (row.order_id ? '#' + row.order_id : 'Walk-in') + ' / ' + (row.invoice_number || 'N/A'),
                    row.medicine_name || 'N/A',
                    'SALE',
                    row.quantity || 0,
                    fmt(parseFloat(row.total_cost || 0)),
                    row.cashier_name || 'Unknown',
                    fmtDateTime(row.timestamp)
                ]);
            }

            if (typeof doc.autoTable !== 'function') {
                showToast('PDF table plugin failed to load. Refresh the page and try again.', 'danger');
                return;
            }

            doc.autoTable({
                head: head,
                body: body,
                startY: 20,
                margin: { top: 20, right: 10, bottom: 20, left: 10 },
                styles: { fontSize: 10, cellPadding: 2, overflow: 'linebreak' },
                didDrawPage: data => {
                    doc.setFontSize(10);
                    doc.text(`Page ${doc.internal.getNumberOfPages()}`, data.settings.margin.left, doc.internal.pageSize.height - 10);
                }
            });

            doc.save(`${currentReportType}_report.pdf`);
            showToast('PDF exported successfully', 'success');
        } catch (err) {
            console.error('PDF export failed:', err);
            showToast('PDF export failed. Please refresh the page and try again.', 'danger');
        }
    };

    // Generate Excel (full data)
    window.generateExcel = async function() {
        const fullData = await fetchFullReport();
        if (fullData.length === 0) {
            showToast('No data to export', 'warning');
            return;
        }
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(fullData);
        XLSX.utils.book_append_sheet(wb, ws, currentReportType);
        XLSX.writeFile(wb, `${currentReportType}_report.xlsx`);
        showToast('Excel exported successfully', 'success');
    };

    // Generate CSV (full data)
    window.generateCSV = async function() {
        const fullData = await fetchFullReport();
        if (fullData.length === 0) {
            showToast('No data to export', 'warning');
            return;
        }
        const ws = XLSX.utils.json_to_sheet(fullData);
        const csv = XLSX.utils.sheet_to_csv(ws);
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${currentReportType}_report.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('CSV exported successfully', 'success');
    };

    // Toast helper
    function showToast(message, type = 'success') {
        let container = document.getElementById('sup-report-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'sup-report-toast-container';
            container.style.cssText = `
                position: fixed;
                right: 20px;
                bottom: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        const bgColor = type === 'success' ? '#d4edda' : type === 'danger' ? '#f8d7da' : type === 'warning' ? '#fff3cd' : '#d1ecf1';
        const textColor = type === 'success' ? '#155724' : type === 'danger' ? '#721c24' : type === 'warning' ? '#856404' : '#0c5460';
        toast.style.cssText = `
            pointer-events: auto;
            background: ${bgColor};
            color: ${textColor};
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid ${textColor};
            box-shadow: 0 10px 24px rgba(15,23,42,0.12);
            max-width: min(340px, calc(100vw - 40px));
            width: max-content;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.92rem;
            line-height: 1.4;
        `;
        toast.innerHTML = `<i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'x-circle' : 'exclamation-triangle'} me-2"></i><span>${escapeHtml(message)}</span>`;

        while (container.childElementCount > 2) {
            container.removeChild(container.firstElementChild);
        }

        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(8px)';
            toast.style.transition = 'all 0.25s ease';
            setTimeout(() => toast.remove(), 250);
        }, 3000);
    }

    // Event listeners
    document.getElementById('report-type').addEventListener('change', () => { currentPage = 1; loadReport(); });

    // Initial load
    setMedicineFilter(0, '');
    loadReport();
});