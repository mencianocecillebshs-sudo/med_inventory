// assets/js/user_activity.js
document.addEventListener('DOMContentLoaded', function () {
    loadActivities();
    loadStats();
    setupEventListeners();
    
    // Auto-refresh active users count every 30 seconds
    setInterval(() => {
        loadStats();
    }, 30000);
});

let activityRows = [];
let activitySourceRows = [];
let activityPage = 1;
const activityPerPage = window.RECORDS_PER_PAGE || 10;

// Toast notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;

    const icons = {
        success: 'check-circle-fill',
        error: 'x-circle-fill',
        warning: 'exclamation-triangle-fill',
        info: 'info-circle-fill'
    };

    toast.innerHTML = `
        <i class="bi bi-${icons[type]}"></i>
        <span>${message}</span>
        <button class="toast-close">×</button>
    `;

    container.appendChild(toast);

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => removeToast(toast));

    setTimeout(() => removeToast(toast), 5000);
}

function removeToast(toast) {
    toast.style.animation = 'slideOut 0.3s ease-out';
    setTimeout(() => toast.remove(), 300);
}

// Load activities
function loadActivities(filterType = null) {
    let url = 'api/user_activity.php?action=fetch';
    if (filterType) {
        url += `&filter_type=${filterType}`;
    }
    
    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayActivities(data.activities);
                updateTotalCount(data.total);
            } else {
                showToast(data.message || 'Failed to load activities', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error', 'error');
        });
}

// Display activities
function displayActivities(activities) {
    activitySourceRows = activities || [];
    activityRows = activitySourceRows;
    activityPage = 1;
    renderActivityPage();
}

function renderActivityPagination(totalPages) {
    const pagination = document.getElementById('activity-pagination');
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
                activityPage = page;
                renderActivityPage();
            }
        });
        ul.appendChild(li);
    };
    add('&laquo;', activityPage - 1, activityPage === 1);
    for (let i = Math.max(1, activityPage - 2); i <= Math.min(totalPages, activityPage + 2); i++) add(i, i, false, i === activityPage);
    add('&raquo;', activityPage + 1, activityPage === totalPages);
    pagination.appendChild(ul);
}

function renderActivityPage() {
    const tbody = document.getElementById('activities-table');
    tbody.innerHTML = '';

    if (!activityRows || activityRows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-5">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="mt-3 text-muted">No activity records found</p>
                </td>
            </tr>
        `;
        renderActivityPagination(0);
        const info = document.getElementById('activity-page-info');
        if (info) info.textContent = 'No activities';
        return;
    }

    const totalPages = Math.max(1, Math.ceil(activityRows.length / activityPerPage));
    if (activityPage > totalPages) activityPage = totalPages;
    const start = (activityPage - 1) * activityPerPage;
    const pageRows = activityRows.slice(start, start + activityPerPage);
    const info = document.getElementById('activity-page-info');
    if (info) info.textContent = `Showing ${start + 1}-${Math.min(start + activityPerPage, activityRows.length)} of ${activityRows.length}`;

    pageRows.forEach(activity => {
        const tr = document.createElement('tr');

        const actionBadge = getActionBadge(activity.action_type);
        const roleBadge = getRoleBadge(activity.role);

        tr.innerHTML = `
            <td>${activity.id}</td>
            <td>
                <div class="fw-bold">${escapeHtml(activity.username || 'N/A')}</div>
                <small class="text-muted">${escapeHtml(activity.name || 'Unknown')}</small>
            </td>
            <td>${roleBadge}</td>
            <td>${actionBadge}</td>
            <td><code>${escapeHtml(activity.ip_address || 'N/A')}</code></td>
            <td><small class="text-break">${truncate(activity.user_agent, 35)}</small></td>
            <td>${formatDuration(activity.session_duration)}</td>
            <td>
                <div>${formatDate(activity.created_at)}</div>
                <small class="text-muted">${formatTime(activity.created_at)}</small>
            </td>
            <td>
                <button class="btn btn-sm btn-warning action-btn" onclick="viewActivityDetails(${activity.id})" title="View Details">
                    <i class="bi bi-eye"></i> View
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    renderActivityPagination(totalPages);
}

// Action badge
function getActionBadge(action) {
    const map = {
        'login': { text: 'Login', class: 'status-login' },
        'logout': { text: 'Logout', class: 'status-logout' },
        'failed_login': { text: 'Failed Login', class: 'status-failed' },
        'password_change': { text: 'Password Change', class: 'status-pwdchange' }
    };
    const def = { text: formatAction(action), class: 'status-pending' };
    const badge = map[action] || def;
    return `<span class="status-badge ${badge.class}">${badge.text}</span>`;
}

// Role badge
function getRoleBadge(role) {
    const map = {
        'admin': 'status-delivered',
        'pharmacist': 'status-ordered',
        'inventory_manager': 'status-pending',
        'staff': 'status-cancelled'
    };
    const cls = map[role] || 'status-pending';
    return `<span class="status-badge ${cls}">${formatRole(role)}</span>`;
}

// Load stats
function loadStats() {
    fetch('api/user_activity.php?action=stats')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.stats) {
                displayStats(data.stats);
            } else {
                console.warn('Failed to load stats');
            }
        })
        .catch(err => {
            console.error('Stats fetch error:', err);
        });
}

function displayStats(stats) {
    document.getElementById('total-activities').textContent = stats.total || 0;
    document.getElementById('today-logins').textContent = stats.today_logins || 0;
    document.getElementById('active-users').textContent = stats.active_users || 0;
}

function updateTotalCount(total) {
    const el = document.getElementById('total-count');
    if (el) el.textContent = `Total: ${total}`;
}

// Setup listeners
function setupEventListeners() {
    const els = {
        reset: document.getElementById('reset-filter-btn'),
        export: document.getElementById('export-btn'),
        refresh: document.getElementById('refresh-btn'),
        search: document.getElementById('search-input'),
        filterAll: document.getElementById('filter-all-btn'),
        filterLogin: document.getElementById('filter-login-btn'),
        filterLogout: document.getElementById('filter-logout-btn'),
        filterFailed: document.getElementById('filter-failed-btn'),
        filterPwd: document.getElementById('filter-pwd-btn')
    };

    els.reset?.addEventListener('click', resetFilters);
    els.export?.addEventListener('click', exportActivities);
    els.refresh?.addEventListener('click', () => {
        loadActivities();
        loadStats();
        showToast('Data refreshed', 'success');
    });

    els.search?.addEventListener('input', debounce(searchActivities, 300));

    // Quick filter buttons
    els.filterAll?.addEventListener('click', () => {
        setActiveFilterButton('filter-all-btn');
        loadActivities(null);
        showToast('Showing all activities', 'info');
    });

    els.filterLogin?.addEventListener('click', () => {
        setActiveFilterButton('filter-login-btn');
        loadActivities('login');
        showToast('Filtered: Login activities', 'success');
    });

    els.filterLogout?.addEventListener('click', () => {
        setActiveFilterButton('filter-logout-btn');
        loadActivities('logout');
        showToast('Filtered: Logout activities', 'info');
    });

    els.filterFailed?.addEventListener('click', () => {
        setActiveFilterButton('filter-failed-btn');
        loadActivities('failed_login');
        showToast('Filtered: Failed login attempts', 'error');
    });

    els.filterPwd?.addEventListener('click', () => {
        setActiveFilterButton('filter-pwd-btn');
        loadActivities('password_change');
        showToast('Filtered: Password changes', 'warning');
    });
}

// Set active filter button styling
function setActiveFilterButton(buttonId) {
    const buttons = [
        'filter-all-btn',
        'filter-login-btn', 
        'filter-logout-btn',
        'filter-failed-btn',
        'filter-pwd-btn'
    ];

    buttons.forEach(id => {
        const btn = document.getElementById(id);
        if (btn) {
            if (id === buttonId) {
                // Make it solid
                btn.classList.remove('btn-outline-primary', 'btn-outline-success', 'btn-outline-info', 'btn-outline-danger', 'btn-outline-warning');
                if (id === 'filter-all-btn') btn.classList.add('btn-primary');
                else if (id === 'filter-login-btn') btn.classList.add('btn-success');
                else if (id === 'filter-logout-btn') btn.classList.add('btn-info');
                else if (id === 'filter-failed-btn') btn.classList.add('btn-danger');
                else if (id === 'filter-pwd-btn') btn.classList.add('btn-warning');
            } else {
                // Make it outline
                btn.classList.remove('btn-primary', 'btn-success', 'btn-info', 'btn-danger', 'btn-warning');
                if (id === 'filter-all-btn') btn.classList.add('btn-outline-primary');
                else if (id === 'filter-login-btn') btn.classList.add('btn-outline-success');
                else if (id === 'filter-logout-btn') btn.classList.add('btn-outline-info');
                else if (id === 'filter-failed-btn') btn.classList.add('btn-outline-danger');
                else if (id === 'filter-pwd-btn') btn.classList.add('btn-outline-warning');
            }
        }
    });
}

// === FILTER VALIDATION ===
function applyFiltersWithValidation() {
    const errorEl = document.getElementById('filter-error');
    errorEl.style.display = 'none';
    errorEl.textContent = '';

    const userId = document.getElementById('filter-user').value.trim();
    const fromDate = document.getElementById('filter-date-from').value;
    const toDate = document.getElementById('filter-date-to').value;

    // Validate User ID
    if (userId && (isNaN(userId) || parseInt(userId) <= 0)) {
        showError('User ID must be a positive number');
        return;
    }

    // Validate Dates
    if (fromDate && toDate && fromDate > toDate) {
        showError('"From" date cannot be after "To" date');
        return;
    }

    applyFilters();
}

function showError(msg) {
    const errorEl = document.getElementById('filter-error');
    errorEl.textContent = msg;
    errorEl.style.display = 'block';
    showToast(msg, 'error');
}

// Apply filters (after validation)
function applyFilters() {
    const filters = {
        user_id: document.getElementById('filter-user').value,
        action_type: document.getElementById('filter-action').value,
        role: document.getElementById('filter-role').value,
        date_from: document.getElementById('filter-date-from').value,
        date_to: document.getElementById('filter-date-to').value
    };

    const formData = new FormData();
    formData.append('action', 'filter');
    Object.entries(filters).forEach(([k, v]) => v && formData.append(k, v));

    fetch('api/user_activity.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayActivities(data.activities);
                showToast(`Found ${data.count} records`, 'success');
                bootstrap.Modal.getInstance(document.getElementById('filterModal'))?.hide();
                
                // Reset quick filter buttons
                setActiveFilterButton('filter-all-btn');
            } else {
                showToast(data.message || 'Filter failed', 'error');
            }
        })
        .catch(() => showToast('Filter error', 'error'));
}

function resetFilters() {
    ['filter-user', 'filter-action', 'filter-role', 'filter-date-from', 'filter-date-to'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const filterError = document.getElementById('filter-error');
    if (filterError) filterError.style.display = 'none';
    
    // Reset to "All" filter
    setActiveFilterButton('filter-all-btn');
    loadActivities();
    showToast('Filters reset', 'info');
}

// Export CSV
function exportActivities() {
    const url = `api/user_activity.php?action=export&format=csv`;
    window.location.href = url;
    showToast('Export started...', 'success');
}

// Search
function searchActivities() {
    const term = document.getElementById('search-input').value.toLowerCase();
    if (!term) {
        activityRows = activitySourceRows;
        activityPage = 1;
        renderActivityPage();
        return;
    }
    activityRows = activitySourceRows.filter(activity => JSON.stringify(activity).toLowerCase().includes(term));
    activityPage = 1;
    renderActivityPage();
}

// View details
function viewActivityDetails(id) {
    fetch('api/user_activity.php?action=fetch')
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            const activity = data.activities.find(a => a.id === id);
            if (activity) showActivityModal(activity);
        });
}

function showActivityModal(activity) {
    const modal = new bootstrap.Modal(document.getElementById('activityDetailModal'));
    const set = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value ?? 'N/A';
    };

    set('detail-username', activity.username);
    set('detail-name', activity.name);
    set('detail-role', formatRole(activity.role));
    set('detail-action', formatAction(activity.action_type));
    set('detail-ip', activity.ip_address);
    set('detail-user-agent', activity.user_agent);
    set('detail-duration', formatDuration(activity.session_duration));
    set('detail-datetime', `${formatDate(activity.created_at)} ${formatTime(activity.created_at)}`);

    modal.show();
}

// Helpers
function formatAction(action) {
    if (!action) return 'N/A';
    return action.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatRole(role) {
    if (!role) return 'N/A';
    return role.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(dateStr) {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(seconds) {
    if (!seconds || seconds <= 0) return 'N/A';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return h > 0 ? `${h}h ${m}m` : m > 0 ? `${m}m ${s}s` : `${s}s`;
}

function truncate(str, len) {
    if (!str) return 'N/A';
    return str.length > len ? str.substring(0, len) + '...' : str;
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}
