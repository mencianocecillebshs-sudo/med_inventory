/**
 * Shared utilities for the Supplier workspace.
 * Reads window.RECORDS_PER_PAGE, window.CURRENCY_SYMBOL, window.SYSTEM_DATE_FORMAT from nav.php.
 */
window.SupUtils = (function () {
    function currencySymbol() {
        const sym = window.CURRENCY_SYMBOL || '\u20b1';
        if (!sym || sym === '?' || /^\?+$/.test(sym)) {
            return '\u20b1';
        }
        return sym;
    }

    function formatCurrency(value) {
        const amount = parseFloat(value);
        if (Number.isNaN(amount)) return currencySymbol() + '0.00';
        return currencySymbol() + amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(value, fallback) {
        if (!value) return fallback !== undefined ? fallback : 'N/A';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);

        const fmt = window.SYSTEM_DATE_FORMAT || 'Y-m-d';
        const pad = (n) => String(n).padStart(2, '0');
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const y = date.getFullYear();
        const m = date.getMonth() + 1;
        const d = date.getDate();
        const mon = months[date.getMonth()];

        switch (fmt) {
            case 'm/d/Y':
                return `${pad(m)}/${pad(d)}/${y}`;
            case 'd/m/Y':
                return `${pad(d)}/${pad(m)}/${y}`;
            case 'd-M-Y':
                return `${pad(d)}-${mon}-${y}`;
            default:
                return `${y}-${pad(m)}-${pad(d)}`;
        }
    }

    function formatDateTime(value, fallback) {
        if (!value) return fallback !== undefined ? fallback : 'N/A';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getRecordsPerPage() {
        const n = parseInt(window.RECORDS_PER_PAGE, 10);
        return Number.isFinite(n) && n > 0 ? n : 25;
    }

    function getLowStockThreshold() {
        const n = parseInt(window.LOW_STOCK_THRESHOLD, 10);
        return Number.isFinite(n) && n > 0 ? n : 10;
    }

    function getCriticalStockThreshold() {
        const n = parseInt(window.CRITICAL_STOCK_THRESHOLD, 10);
        return Number.isFinite(n) && n > 0 ? n : 5;
    }

    function getExpiryAlertDays() {
        const n = parseInt(window.EXPIRY_ALERT_DAYS, 10);
        return Number.isFinite(n) && n > 0 ? n : 30;
    }

    function normalizePagination(paginationData, currentPage) {
        const page = Math.max(1, parseInt(paginationData?.current_page ?? currentPage ?? 1, 10));
        const totalPages = Math.max(1, parseInt(paginationData?.total_pages ?? 1, 10));
        const totalItems = Math.max(0, parseInt(paginationData?.total_items ?? 0, 10));
        const perPage = Math.max(1, parseInt(paginationData?.items_per_page ?? getRecordsPerPage(), 10));
        const start = totalItems === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = totalItems === 0 ? 0 : Math.min(page * perPage, totalItems);
        return { page, totalPages, totalItems, perPage, start, end };
    }

    function buildPaginationInfoText(meta) {
        if (meta.totalItems === 0) {
            return 'Page 1 of 1 · 0 items';
        }
        return `Page ${meta.page} of ${meta.totalPages} · Showing ${meta.start.toLocaleString()}–${meta.end.toLocaleString()} of ${meta.totalItems.toLocaleString()}`;
    }

    function resolvePaginationInfoEl(containerEl) {
        if (!containerEl) return null;
        if (containerEl.dataset.infoTarget) {
            return document.getElementById(containerEl.dataset.infoTarget);
        }
        const byId = document.getElementById(`${containerEl.id}-info`);
        if (byId) return byId;
        const footer = containerEl.closest('.sup-table-footer, .pagination-bar');
        return footer ? footer.querySelector('.sup-pagination-info') : null;
    }

    function renderPagination(containerEl, paginationData, currentPage, onPageChange) {
        if (!containerEl) return;

        const meta = normalizePagination(paginationData, currentPage);
        const infoEl = resolvePaginationInfoEl(containerEl);
        if (infoEl) {
            infoEl.textContent = buildPaginationInfoText(meta);
        }

        const totalPages = Math.max(1, meta.totalPages);
        let html = `<li class="page-item ${meta.page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${meta.page - 1}" aria-label="Previous page">Previous</a></li>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= meta.page - 2 && i <= meta.page + 2)) {
                html += `<li class="page-item ${i === meta.page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}" aria-label="Page ${i}" ${i === meta.page ? 'aria-current="page"' : ''}>${i}</a></li>`;
            } else if (i === meta.page - 3 || i === meta.page + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        if (totalPages === 1) {
            html += `<li class="page-item disabled">
                <a class="page-link" href="#" data-page="1" aria-label="Page 1">Next</a></li>`;
        } else {
            html += `<li class="page-item ${meta.page === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${meta.page + 1}" aria-label="Next page">Next</a></li>`;
        }

        containerEl.innerHTML = html;
        containerEl.querySelectorAll('a.page-link[data-page]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.dataset.page, 10);
                if (page > 0 && page <= totalPages && page !== meta.page) {
                    onPageChange(page);
                }
            });
        });
    }

    function initExpandableTable(tableWrapSelector, toggleBtnSelector) {
        const wrap = document.querySelector(tableWrapSelector);
        const btn = document.querySelector(toggleBtnSelector);
        if (!wrap || !btn) return;

        const setLabel = (expanded) => {
            btn.innerHTML = expanded
                ? '<i class="bi bi-arrows-angle-contract me-1"></i> Show Less'
                : '<i class="bi bi-arrows-angle-expand me-1"></i> Show All Columns';
            btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        };

        setLabel(false);
        btn.addEventListener('click', () => {
            wrap.classList.toggle('expanded');
            setLabel(wrap.classList.contains('expanded'));
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    return {
        currencySymbol,
        formatCurrency,
        formatDate,
        formatDateTime,
        getRecordsPerPage,
        getLowStockThreshold,
        getCriticalStockThreshold,
        getExpiryAlertDays,
        normalizePagination,
        buildPaginationInfoText,
        renderPagination,
        initExpandableTable,
        escapeHtml
    };
})();
