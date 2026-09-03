document.addEventListener('DOMContentLoaded', function() {
    const recentNotifications = document.getElementById('recent-notifications');
    const oldNotifications = document.getElementById('old-notifications');
    const notificationCount = document.getElementById('notification-count');
    const recentCount = document.getElementById('recent-count');
    const oldCount = document.getElementById('old-count');
    const searchInput = document.getElementById('search');
    const fromDateInput = document.getElementById('from-date');
    const toDateInput = document.getElementById('to-date');
    const markAllRecentBtn = document.getElementById('mark-all-recent');
    const markAllOldBtn = document.getElementById('mark-all-old');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const notificationsPaginationEl = document.getElementById('notifications-pagination');
    const notificationsPaginationInfoEl = document.getElementById('notifications-pagination-info');
    const U = window.SupUtils;

    let sharedPage = 1;
    const notificationPages = {
        0: { total_pages: 1, total_items: 0, current_page: 1 },
        1: { total_pages: 1, total_items: 0, current_page: 1 }
    };

    // Sidebar
    const sidebar = document.querySelector('.sidebar');
    const toggleButton = document.getElementById('toggle-sidebar-mobile');
    if (sidebar && localStorage.getItem('sidebar') === 'open') sidebar.classList.add('active');
    if (toggleButton && sidebar) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            localStorage.setItem('sidebar', sidebar.classList.contains('active') ? 'open' : 'closed');
        });
    }

    // Theme
    if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
    }

    // Toast
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container') || createToastContainer();
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${type} border-0 shadow-lg`;
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        container.appendChild(toastEl);
        new bootstrap.Toast(toastEl).show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    function createToastContainer() {
        const cont = document.createElement('div');
        cont.id = 'toast-container';
        cont.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(cont);
        return cont;
    }

    // Get notification icon based on type
    function getNotificationIcon(type) {
        switch(type.toLowerCase()) {
            case 'warning':
                return '<i class="bi bi-exclamation-triangle-fill"></i>';
            case 'danger':
            case 'error':
                return '<i class="bi bi-x-circle-fill"></i>';
            case 'success':
                return '<i class="bi bi-check-circle-fill"></i>';
            default:
                return '<i class="bi bi-info-circle-fill"></i>';
        }
    }

    function getIconClass(type) {
        switch(type.toLowerCase()) {
            case 'warning':
                return 'icon-warning';
            case 'danger':
            case 'error':
                return 'icon-danger';
            default:
                return 'icon-info';
        }
    }

    function renderSharedPagination() {
        if (!U || !notificationsPaginationEl) return;

        const totalPages = Math.max(
            notificationPages[0].total_pages || 1,
            notificationPages[1].total_pages || 1,
            1
        );
        const totalItems = (notificationPages[0].total_items || 0) + (notificationPages[1].total_items || 0);

        const meta = U.normalizePagination({
            current_page: sharedPage,
            total_pages: totalPages,
            total_items: totalItems,
            items_per_page: U.getRecordsPerPage()
        }, sharedPage);

        if (notificationsPaginationInfoEl) {
            notificationsPaginationInfoEl.textContent = U.buildPaginationInfoText(meta);
        }

        U.renderPagination(notificationsPaginationEl, {
            current_page: sharedPage,
            total_pages: totalPages,
            total_items: totalItems,
            items_per_page: U.getRecordsPerPage()
        }, sharedPage, (newPage) => {
            sharedPage = newPage;
            reloadAllNotifications();
        });
    }

    function loadNotifications(readStatus, container, page) {
        const search = searchInput.value;
        const fromDate = fromDateInput.value;
        const toDate = toDateInput.value;
        const limit = U ? U.getRecordsPerPage() : (window.RECORDS_PER_PAGE || 25);
        let url = `api/sup_notifications.php?read=${readStatus}&search=${encodeURIComponent(search)}&page=${page}&limit=${limit}`;
        if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
        if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;

        fetch(url)
            .then(response => response.json())
            .then(data => {
                container.innerHTML = '';
                let count = 0;
                const total = data.total ?? data.notifications.length;
                const pagination = data.pagination || {
                    total_items: total,
                    total_pages: 1,
                    current_page: page,
                    items_per_page: limit
                };

                notificationPages[readStatus] = {
                    total_pages: pagination.total_pages || 1,
                    total_items: pagination.total_items || total,
                    current_page: page
                };

                // Update count badges
                if (readStatus === 0) {
                    recentCount.textContent = total;
                } else {
                    oldCount.textContent = total;
                }

                if (data.notifications.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>No ${readStatus ? 'old' : 'recent'} notifications</p>
                        </div>
                    `;
                } else {
                    data.notifications.forEach(notification => {
                        const item = document.createElement('div');
                        item.className = `list-group-item d-flex align-items-start ${notification.read ? 'read' : 'unread'}`;
                        item.innerHTML = `
                            <div class="notification-icon ${getIconClass(notification.type)}">
                                ${getNotificationIcon(notification.type)}
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notification.type.toUpperCase()}</div>
                                <div class="notification-message">${notification.message}</div>
                                <div class="notification-time">
                                    <i class="bi bi-clock me-1"></i>${U ? U.formatDateTime(notification.created_at) : new Date(notification.created_at).toLocaleString()}
                                </div>
                            </div>
                            <div class="d-flex gap-2 ms-3">
                                <button class="btn btn-sm btn-outline-primary mark-read-btn" data-id="${notification.id}" title="Mark as ${notification.read ? 'Unread' : 'Read'}">
                                    <i class="bi bi-${notification.read ? 'arrow-counterclockwise' : 'check-circle'}"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${notification.id}" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        `;
                        container.appendChild(item);
                        if (!notification.read) count++;
                    });
                }

                if (!readStatus) notificationCount.textContent = count;
                renderSharedPagination();
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                        <p class="text-danger">Error loading notifications</p>
                    </div>
                `;
                if (readStatus === 0) {
                    recentCount.textContent = '0';
                } else {
                    oldCount.textContent = '0';
                }
                renderSharedPagination();
                showToast('Failed to load notifications', 'danger');
            });
    }

    function reloadAllNotifications() {
        loadNotifications(0, recentNotifications, sharedPage);
        loadNotifications(1, oldNotifications, sharedPage);
    }

    function reloadFromFirstPage() {
        sharedPage = 1;
        reloadAllNotifications();
    }

    function handleMarkRead(e) {
        if (e.target.closest('.mark-read-btn')) {
            const btn = e.target.closest('.mark-read-btn');
            const id = btn.dataset.id;
            const item = btn.closest('.list-group-item');
            const isCurrentlyRead = item.classList.contains('read');
            const newReadStatus = isCurrentlyRead ? 0 : 1;
            
            fetch(`api/sup_notifications.php?id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ read: newReadStatus })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload both sections to show the transfer
                        reloadAllNotifications();
                        localStorage.setItem('notificationUpdated', Date.now());
                        showToast(`Notification marked as ${newReadStatus ? 'read' : 'unread'}`, 'success');
                    } else {
                        showToast('Error marking notification', 'danger');
                    }
                })
                .catch(() => showToast('Error marking notification', 'danger'));
        }
    }

    function handleDelete(e) {
        if (e.target.closest('.delete-btn')) {
            const btn = e.target.closest('.delete-btn');
            const id = btn.dataset.id;
            
            if (confirm('Are you sure you want to delete this notification?')) {
                fetch(`api/sup_notifications.php?id=${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            reloadAllNotifications();
                            localStorage.setItem('notificationUpdated', Date.now());
                            showToast('Notification deleted', 'success');
                        } else {
                            showToast('Error deleting notification', 'danger');
                        }
                    })
                    .catch(() => showToast('Error deleting notification', 'danger'));
            }
        }
    }

    function handleMarkAll(readStatus) {
        const action = readStatus ? 'unread' : 'read';
        fetch(`api/sup_notifications.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ read: readStatus ? 0 : 1, mark_all: true })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    reloadAllNotifications();
                    localStorage.setItem('notificationUpdated', Date.now());
                    showToast(`All notifications marked as ${action}`, 'success');
                } else {
                    showToast('Error marking all notifications', 'danger');
                }
            })
            .catch(() => showToast('Error marking all notifications', 'danger'));
    }

    // Event listeners
    recentNotifications.addEventListener('click', (e) => {
        handleMarkRead(e);
        handleDelete(e);
    });

    oldNotifications.addEventListener('click', (e) => {
        handleMarkRead(e);
        handleDelete(e);
    });

    markAllRecentBtn.addEventListener('click', () => handleMarkAll(0));
    markAllOldBtn.addEventListener('click', () => handleMarkAll(1));

    clearFiltersBtn.addEventListener('click', () => {
        searchInput.value = '';
        fromDateInput.value = '';
        toDateInput.value = '';
        reloadFromFirstPage();
        showToast('Filters cleared', 'info');
    });

    // Debounce search input
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            reloadFromFirstPage();
        }, 300);
    });

    fromDateInput.addEventListener('change', reloadFromFirstPage);

    toDateInput.addEventListener('change', reloadFromFirstPage);

    // Initial load
    reloadAllNotifications();

    // Real-time updates (check every 30 seconds)
    setInterval(reloadAllNotifications, 30000);
});