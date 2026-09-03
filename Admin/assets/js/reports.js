document.addEventListener('DOMContentLoaded', () => {
    const reportType = document.getElementById('report-type');
    const medicineSearchInput = document.getElementById('medicine-search');
    const medicineFilterId = document.getElementById('medicine-filter-id');
    const medicineSearchClear = document.getElementById('medicine-search-clear');
    const tableHeader = document.getElementById('report-table-header');
    const tableBody = document.getElementById('report-table-body');
    const filterIndicator = document.getElementById('filter-indicator');
    const filterText = document.getElementById('filter-text');
    const reportPagination = document.getElementById('report-pagination');
    const reportPageInfo = document.getElementById('report-page-info');
    const printReportBtn = document.getElementById('print-report-btn');
    const printPreviewBtn = document.getElementById('print-preview-btn');
    const downloadPdfBtn = document.getElementById('download-pdf-btn');
    const downloadExcelBtn = document.getElementById('download-excel-btn');
    const downloadCsvBtn = document.getElementById('download-csv-btn');

    let reportRows = [];
    let reportPage = 1;
    const reportPerPage = window.RECORDS_PER_PAGE || 10;

    // True while the user is actively typing a free-text search. While
    // true, the main table shows every matching row at once, bypassing
    // pagination entirely.
    let isClientFiltering = false;

    // Holds the most recently built {headers, rows} table for the
    // currently-loaded report, and separately for whatever is shown in
    // the preview modal. Print and download actions read from these
    // instead of re-deriving from the DOM, so "Print this" inside the
    // preview always prints exactly what's on screen there.
    let currentReportTable = null;
    let currentPreviewTable = null;
    let currentPreviewLabel = '';

    const escapeHtml = text => {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    };

    function showToast(message, type = 'success') {
        // NOTE: #toast-container must carry "position-fixed bottom-0 end-0 p-3"
        // (set in reports.php). That keeps every toast taken out of the normal
        // page flow, so showing/removing one never shifts the table or any
        // other content on the page. If for some reason the container is
        // missing from the DOM, recreate it with the same fixed positioning
        // rather than letting toasts fall back into the document flow.
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1080';
            document.body.appendChild(container);
        }
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${type} border-0 shadow-lg`;
        toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        container.appendChild(toastEl);
        new bootstrap.Toast(toastEl).show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    function reportUrl() {
        const type = reportType.value;
        const medId = medicineFilterId.value;
        let url = `api/reports.php?type=${encodeURIComponent(type)}`;
        if (medId) url += `&medicine_id=${encodeURIComponent(medId)}`;
        return url;
    }

    async function fetchReportItems() {
        const res = await fetch(reportUrl(), { credentials: 'same-origin' });
        if (!res.ok) throw new Error('Failed to load report');
        const data = await res.json();
        return data.data || data.medicines || [];
    }

    function tableFor(type, items) {
        if (type === 'inventory') {
            return {
                headers: ['Item Name', 'Barcode', 'Stock Level', 'Category', 'Description', 'Date Acquired', 'Expiry Date'],
                rows: items.map(item => [
                    item['Item Name'] || item['Medicine Name'] || item.name || '',
                    item.barcode || 'N/A',
                    `${item['Remaining Stock'] || item.quantity || 0} units`,
                    item['Type'] || item.type || item.item_type || item.category || 'N/A',
                    item.description || 'No description',
                    item['Date Acquired'] || item.created_at ? new Date(item['Date Acquired'] || item.created_at).toLocaleDateString() : 'N/A',
                    item['Expiry Date'] || item.expiry_date || 'N/A'
                ])
            };
        }
        if (type === 'transactions') {
            return {
                headers: ['Item Name', 'Total Dispensed (15 Days)', 'Date Span Start', 'Date Span End', 'Yearly Total'],
                rows: items.map(item => [
                    item['Item Name'] || item['Medicine Name'] || 'Unknown',
                    `${item['Total Dispensed (15 Days)'] || 0} units`,
                    item['Date Span Start'] || '-',
                    item['Date Span End'] || '-',
                    `${item['Yearly Total'] || 0} units`
                ])
            };
        }
        return {
            headers: ['Invoice #', 'Purchase', 'Items', 'Amount', 'Payment Method', 'Cashier', 'Date'],
            rows: items.map(item => [
                item.invoice_number || '',
                item.purchase_number || 'Walk-in',
                item.item_count || 0,
                `PHP ${parseFloat(item.total_amount || 0).toFixed(2)}`,
                (item.payment_method || '').toUpperCase().replace(/_/g, ' '),
                item.cashier_name || 'Unknown',
                item.created_at ? new Date(item.created_at).toLocaleString() : 'N/A'
            ])
        };
    }

    function renderPagination(totalPages) {
        reportPagination.innerHTML = '';
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
                    reportPage = page;
                    renderReportRows();
                }
            });
            ul.appendChild(li);
        };
        add('&laquo;', reportPage - 1, reportPage === 1);
        for (let i = Math.max(1, reportPage - 2); i <= Math.min(totalPages, reportPage + 2); i++) add(i, i, false, i === reportPage);
        add('&raquo;', reportPage + 1, reportPage === totalPages);
        reportPagination.appendChild(ul);
    }

    function renderReportRows() {
        const totalPages = Math.max(1, Math.ceil(reportRows.length / reportPerPage));
        if (reportPage > totalPages) reportPage = totalPages;
        const start = (reportPage - 1) * reportPerPage;
        tableBody.innerHTML = reportRows.slice(start, start + reportPerPage).join('');
        reportPageInfo.textContent = reportRows.length ? `Showing ${start + 1}-${Math.min(start + reportPerPage, reportRows.length)} of ${reportRows.length}` : 'No records';
        renderPagination(totalPages);
    }

    /* ------------------------------------------------------------------ *
     *  Client-side free-text filter that BYPASSES pagination.
     * ------------------------------------------------------------------ */
    function applyClientTextFilter(query) {
        if (!currentReportTable) return;
        const q = query.trim().toLowerCase();

        if (!q) {
            isClientFiltering = false;
            reportRows = currentReportTable.rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`);
            reportPage = 1;
            renderReportRows();
            return;
        }

        isClientFiltering = true;
        const headers = currentReportTable.headers;
        const matches = currentReportTable.rows.filter(row => row.some(cell => String(cell ?? '').toLowerCase().includes(q)));

        if (matches.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="${headers.length}" class="text-center text-muted py-5">No matching records found.</td></tr>`;
            reportPageInfo.textContent = 'No matching records';
        } else {
            tableBody.innerHTML = matches.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`).join('');
            reportPageInfo.textContent = `Showing all ${matches.length} matching record(s) — paging bypassed while searching`;
        }
        // Pagination doesn't apply while a free-text filter is active.
        reportPagination.innerHTML = '';
    }

    async function loadReport() {
        const type = reportType.value;
        const medName = medicineSearchInput.value.trim();
        filterIndicator.classList.toggle('d-none', !medicineFilterId.value);
        if (medicineFilterId.value) filterText.textContent = `Showing results filtered by: ${medName}`;
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5">Loading report...</td></tr>';
        try {
            const items = await fetchReportItems();
            const table = tableFor(type, items);
            currentReportTable = table;
            tableHeader.innerHTML = `<tr>${table.headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr>`;
            reportRows = table.rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`);
            reportPage = 1;
            isClientFiltering = false;

            if (reportRows.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="${table.headers.length}" class="text-center text-muted py-5">No records found.</td></tr>`;
                reportPageInfo.textContent = 'No records';
                renderPagination(0);
            } else {
                renderReportRows();
            }

            // If the user was already mid-search when the report reloaded
            // (e.g. switched report type while typing), re-apply the filter.
            const currentQuery = medicineSearchInput.value.trim();
            if (currentQuery && !medicineFilterId.value) {
                applyClientTextFilter(currentQuery);
            }
        } catch (err) {
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-5">Failed to load report data.</td></tr>';
            showToast(err.message, 'danger');
        }
    }

    window.previewReport = async () => {
        try {
            const type = reportType.value;
            const items = await fetchReportItems();
            const table = tableFor(type, items);
            currentPreviewTable = table;
            currentPreviewLabel = `${type.toUpperCase()} REPORT`;
            document.getElementById('preview-title').textContent = `${currentPreviewLabel} Preview`;
            document.getElementById('preview-table-header').innerHTML = `<tr>${table.headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr>`;
            document.getElementById('preview-table-body').innerHTML = table.rows.length
                ? table.rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`).join('')
                : `<tr><td colspan="${table.headers.length}" class="text-center text-muted py-4">No records found.</td></tr>`;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('reportPreviewModal')).show();
        } catch (err) {
            showToast('Failed to load preview: ' + err.message, 'danger');
        }
    };

    /* ------------------------------------------------------------------ *
     *  Print — only the report table is printed, never the rest of the page.
     * ------------------------------------------------------------------ */
    function buildPrintArea(table, title) {
        const printTitle = document.getElementById('print-area-title');
        const printHeader = document.getElementById('print-area-header');
        const printBody = document.getElementById('print-area-body');
        printTitle.textContent = title;
        printHeader.innerHTML = `<tr>${table.headers.map(h => `<th>${escapeHtml(h)}</th>`).join('')}</tr>`;
        printBody.innerHTML = table.rows.length
            ? table.rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(String(cell))}</td>`).join('')}</tr>`).join('')
            : `<tr><td colspan="${table.headers.length}" style="text-align:center;color:#888;">No records found.</td></tr>`;
    }

    function getActivePrintableTable() {
        // While the user is free-text filtering, print exactly what's
        // currently visible on screen instead of the unfiltered dataset.
        if (isClientFiltering && currentReportTable) {
            const q = medicineSearchInput.value.trim().toLowerCase();
            const filteredRows = currentReportTable.rows.filter(row => row.some(cell => String(cell ?? '').toLowerCase().includes(q)));
            return { headers: currentReportTable.headers, rows: filteredRows };
        }
        return currentReportTable;
    }

    window.printReport = (fromPreview = false) => {
        const type = reportType.value;
        if (fromPreview) {
            if (!currentPreviewTable) {
                showToast('Open a preview first.', 'warning');
                return;
            }
            buildPrintArea(currentPreviewTable, `${currentPreviewLabel} (Generated ${new Date().toLocaleString()})`);
        } else {
            const table = getActivePrintableTable();
            if (!table) {
                showToast('No report loaded to print.', 'warning');
                return;
            }
            buildPrintArea(table, `${type.toUpperCase()} REPORT (Generated ${new Date().toLocaleString()})`);
        }
        window.print();
    };

    /* ------------------------------------------------------------------ *
     *  Downloads (PDF / Excel / CSV)
     *
     *  Each export now:
     *   1. Carries a proper header block — system name, report type,
     *      active filter (if any), and a generated timestamp — so the
     *      file is self-identifying once it leaves the app.
     *   2. Lets the user pick the destination folder via the native
     *      "Save As" dialog (File System Access API) where supported
     *      (Chrome/Edge desktop). Everywhere else (Firefox, Safari,
     *      mobile) it transparently falls back to a normal browser
     *      download — there's no way to force a folder picker there,
     *      browsers don't expose that to web pages.
     * ------------------------------------------------------------------ */

    const SYSTEM_NAME = 'Medicine Inventory System';

    function getActiveFilterLabel() {
        // Server-side medicine filter (selected from a real match).
        if (medicineFilterId.value && medicineSearchInput.value.trim()) {
            return medicineSearchInput.value.trim();
        }
        // Client-side free-text filter currently applied to the table.
        if (isClientFiltering && medicineSearchInput.value.trim()) {
            return `Text search — "${medicineSearchInput.value.trim()}"`;
        }
        return 'None (all records)';
    }

    function getReportMeta(typeLabel) {
        return {
            system: SYSTEM_NAME,
            reportTitle: `${typeLabel.toUpperCase()} REPORT`,
            filter: getActiveFilterLabel(),
            generated: new Date().toLocaleString()
        };
    }

    function getActiveDownloadTable() {
        const type = reportType.value;
        const table = getActivePrintableTable();
        if (!table) return null;
        return { type, headers: table.headers, rows: table.rows };
    }

    /**
     * Saves a Blob to disk. Tries the native "Save As" folder picker first
     * (showSaveFilePicker — Chrome/Edge desktop, requires HTTPS or localhost);
     * falls back to a standard anchor-tag download everywhere else, or if
     * the user's browser supports the picker but throws for any reason
     * other than the user cancelling.
     *
     * Returns false only when the user actively cancelled the save dialog,
     * so callers can skip the "downloaded successfully" toast in that case.
     */
    async function saveFile(blob, suggestedName, pickerOptions) {
        if (window.showSaveFilePicker) {
            try {
                const handle = await window.showSaveFilePicker({
                    suggestedName,
                    types: pickerOptions ? [pickerOptions] : undefined
                });
                const writable = await handle.createWritable();
                await writable.write(blob);
                await writable.close();
                return true;
            } catch (err) {
                if (err && err.name === 'AbortError') {
                    // User closed/cancelled the folder picker — not an error.
                    return false;
                }
                // Any other failure (e.g. permission issue): fall through to
                // the standard download below so the export still succeeds.
            }
        }

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = suggestedName;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        return true;
    }

    /* ---------- PDF ---------- */

    // Wraps long cell text onto multiple lines within a column instead of
    // truncating/overlapping into the next column, and grows the row height
    // to fit whichever cell needed the most lines.
    function drawPdfTableManually(doc, headers, rows, startY) {
        const marginLeft = 14;
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        const usableWidth = pageWidth - marginLeft * 2;
        const colWidth = usableWidth / headers.length;
        const lineHeight = 4.5;
        const cellPadding = 2;
        let y = startY;

        function drawHeaderRow(yPos) {
            doc.setFillColor(27, 94, 63);
            doc.rect(marginLeft, yPos - 5.5, usableWidth, 7.5, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(8.5);
            doc.setFont(undefined, 'bold');
            headers.forEach((h, i) => {
                doc.text(String(h), marginLeft + i * colWidth + cellPadding, yPos);
            });
            doc.setTextColor(20, 20, 20);
            doc.setFont(undefined, 'normal');
        }

        drawHeaderRow(y);
        y += 6;

        doc.setFontSize(8);
        rows.forEach((row, rowIndex) => {
            // Wrap each cell to its column width, track tallest cell in the row.
            const wrapped = row.map(cell =>
                doc.splitTextToSize(String(cell ?? ''), colWidth - cellPadding * 2)
            );
            const rowLines = Math.max(1, ...wrapped.map(lines => lines.length));
            const rowHeight = rowLines * lineHeight + 2;

            // New page if this row won't fit.
            if (y + rowHeight > pageHeight - 12) {
                doc.addPage();
                y = 20;
                drawHeaderRow(y);
                y += 6;
            }

            // Light zebra striping so overlapping rows are easy to tell apart visually.
            if (rowIndex % 2 === 1) {
                doc.setFillColor(247, 250, 252);
                doc.rect(marginLeft, y - 4, usableWidth, rowHeight, 'F');
            }

            wrapped.forEach((lines, colIndex) => {
                lines.forEach((line, lineIndex) => {
                    doc.text(line, marginLeft + colIndex * colWidth + cellPadding, y + lineIndex * lineHeight);
                });
            });

            y += rowHeight;
        });
    }

    async function generatePDF() {
        if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
            showToast('PDF library failed to load. Check your internet connection and try again.', 'danger');
            return;
        }
        const data = getActiveDownloadTable();
        if (!data || data.rows.length === 0) {
            showToast('No report data to export.', 'warning');
            return;
        }
        try {
            const meta = getReportMeta(data.type);
            const doc = new window.jspdf.jsPDF();

            // Header block: who/what/when this came from, always present
            // regardless of whether autoTable is available.
            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.text(meta.system, 14, 16);
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            doc.text(meta.reportTitle, 14, 23);
            doc.setFontSize(9);
            doc.setTextColor(90, 90, 90);
            doc.text(`Filter applied: ${meta.filter}`, 14, 29);
            doc.text(`Generated: ${meta.generated}`, 14, 34);
            doc.setTextColor(20, 20, 20);
            doc.setLineWidth(0.3);
            doc.line(14, 37, doc.internal.pageSize.getWidth() - 14, 37);

            const tableStartY = 43;

            if (typeof doc.autoTable === 'function') {
                doc.autoTable({
                    head: [data.headers],
                    body: data.rows,
                    startY: tableStartY,
                    theme: 'grid',
                    styles: { fontSize: 8, cellPadding: 3, overflow: 'linebreak' },
                    headStyles: { fillColor: [27, 94, 63], textColor: 255 },
                    columnStyles: { /* let autoTable auto-size + wrap; prevents overlap */ }
                });
            } else {
                // autoTable plugin didn't attach — fall back to the manual
                // wrapped-table drawer above so columns never overlap.
                drawPdfTableManually(doc, data.headers, data.rows, tableStartY);
            }

            const saved = await saveFile(
                doc.output('blob'),
                `${data.type}_report_${new Date().toISOString().split('T')[0]}.pdf`,
                { description: 'PDF Document', accept: { 'application/pdf': ['.pdf'] } }
            );
            if (saved) showToast('PDF downloaded successfully', 'success');
        } catch (err) {
            showToast('Failed to generate PDF: ' + err.message, 'danger');
        }
    }

    /* ---------- Excel ---------- */

    async function generateExcel() {
        if (!window.XLSX || typeof window.XLSX.utils?.book_new !== 'function') {
            showToast('Excel library failed to load. Check your internet connection and try again.', 'danger');
            return;
        }
        const data = getActiveDownloadTable();
        if (!data || data.rows.length === 0) {
            showToast('No report data to export.', 'warning');
            return;
        }
        try {
            const meta = getReportMeta(data.type);

            // Metadata block on top of the sheet so the file is
            // self-identifying, followed by a blank spacer row, then the
            // real header row + data.
            const sheetData = [
                [meta.system],
                [meta.reportTitle],
                [`Filter applied: ${meta.filter}`],
                [`Generated: ${meta.generated}`],
                [],
                data.headers,
                ...data.rows
            ];

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(sheetData);

            // Merge the metadata rows across all columns so they read as a
            // banner instead of being squeezed into column A only.
            const colCount = data.headers.length;
            ws['!merges'] = [
                { s: { r: 0, c: 0 }, e: { r: 0, c: colCount - 1 } },
                { s: { r: 1, c: 0 }, e: { r: 1, c: colCount - 1 } },
                { s: { r: 2, c: 0 }, e: { r: 2, c: colCount - 1 } },
                { s: { r: 3, c: 0 }, e: { r: 3, c: colCount - 1 } }
            ];

            // Auto-size columns based on header + data content (metadata
            // rows excluded since they're merged/banner text).
            ws['!cols'] = data.headers.map((header, i) => ({
                wch: Math.min(Math.max(String(header).length, ...data.rows.map(row => String(row[i] ?? '').length)) + 2, 50)
            }));

            XLSX.utils.book_append_sheet(wb, ws, data.type.charAt(0).toUpperCase() + data.type.slice(1));

            const wbArray = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
            const blob = new Blob([wbArray], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });

            const saved = await saveFile(
                blob,
                `${data.type}_report_${new Date().toISOString().split('T')[0]}.xlsx`,
                { description: 'Excel Workbook', accept: { 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'] } }
            );
            if (saved) showToast('Excel downloaded successfully', 'success');
        } catch (err) {
            showToast('Failed to generate Excel: ' + err.message, 'danger');
        }
    }

    /* ---------- CSV ---------- */

    async function generateCSV() {
        const data = getActiveDownloadTable();
        if (!data || data.rows.length === 0) {
            showToast('No report data to export.', 'warning');
            return;
        }
        try {
            const meta = getReportMeta(data.type);
            const csvEscape = val => `"${String(val ?? '').replace(/"/g, '""')}"`;

            const lines = [
                csvEscape(meta.system),
                csvEscape(meta.reportTitle),
                csvEscape(`Filter applied: ${meta.filter}`),
                csvEscape(`Generated: ${meta.generated}`),
                '',
                data.headers.map(csvEscape).join(','),
                ...data.rows.map(row => row.map(csvEscape).join(','))
            ];

            const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });

            const saved = await saveFile(
                blob,
                `${data.type}_report_${new Date().toISOString().split('T')[0]}.csv`,
                { description: 'CSV File', accept: { 'text/csv': ['.csv'] } }
            );
            if (saved) showToast('CSV downloaded successfully', 'success');
        } catch (err) {
            showToast('Failed to generate CSV: ' + err.message, 'danger');
        }
    }

    // Keep window.* references for backward compatibility.
    window.generatePDF = generatePDF;
    window.generateExcel = generateExcel;
    window.generateCSV = generateCSV;

    if (printReportBtn) printReportBtn.addEventListener('click', () => window.printReport(false));
    if (printPreviewBtn) printPreviewBtn.addEventListener('click', () => window.printReport(true));

    if (downloadPdfBtn) downloadPdfBtn.addEventListener('click', e => { e.preventDefault(); generatePDF(); });
    if (downloadExcelBtn) downloadExcelBtn.addEventListener('click', e => { e.preventDefault(); generateExcel(); });
    if (downloadCsvBtn) downloadCsvBtn.addEventListener('click', e => { e.preventDefault(); generateCSV(); });

    /* ------------------------------------------------------------------ *
     *  Medicine search bar (replaces the old <select id="medicine-filter">)
     * ------------------------------------------------------------------ */
    medicineSearchInput.addEventListener('input', () => {
        const val = medicineSearchInput.value;
        medicineSearchClear.style.display = val ? 'block' : 'none';

        if (medicineFilterId.value) {
            medicineFilterId.value = '';
            filterIndicator.classList.add('d-none');
        }

        applyClientTextFilter(val);
    });

    medicineSearchClear.addEventListener('click', () => {
        medicineSearchInput.value = '';
        medicineSearchClear.style.display = 'none';
        medicineFilterId.value = '';
        filterIndicator.classList.add('d-none');
        applyClientTextFilter('');
        medicineSearchInput.focus();
    });

    reportType.addEventListener('change', loadReport);

    loadReport();
});
