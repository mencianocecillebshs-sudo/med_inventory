document.addEventListener('DOMContentLoaded', function() {
    const recentNotifications = document.getElementById('recent-notifications');
    const oldNotifications = document.getElementById('old-notifications');
    const notificationCount = document.getElementById('notification-count');
    const recentCount = document.getElementById('recent-count');
    const oldCount = document.getElementById('old-count');
    const notificationsPageInfo = document.getElementById('notifications-page-info');
    const searchInput = document.getElementById('search');
    const fromDateInput = document.getElementById('from-date');
    const toDateInput = document.getElementById('to-date');
    const markAllRecentBtn = document.getElementById('mark-all-recent');
    const markAllOldBtn = document.getElementById('mark-all-old');
    const clearFiltersBtn = document.getElementById('clear-filters');

    let recentPage = 1;
    let oldPage = 1;
    let currentPage = 1;
    let recentTotalPages = 1;
    let oldTotalPages = 1;
    const perPage = 10;

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
    function createToastContainer() {
        const cont = document.createElement('div');
        cont.id = 'toast-container';
        cont.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(cont);
        return cont;
    }

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

    function renderPagination() {
        const pagination = document.getElementById('notifications-pagination');
        if (!pagination) return;

        const totalPages = Math.max(recentTotalPages, oldTotalPages);
        pagination.innerHTML = '';
        if (notificationsPageInfo) {
            notificationsPageInfo.textContent = totalPages > 1 ? `Page ${currentPage} of ${totalPages}` : (recentCount.textContent === '0' && oldCount.textContent === '0' ? 'No notifications' : 'Page 1');
        }
        if (totalPages <= 1) return;

        const ul = document.createElement('ul');
        ul.className = 'pagination pagination-sm mb-0';
        ul.style.gap = '0.25rem';
        const add = (label, page, disabled = false, active = false) => {
            const li = document.createElement('li');
            li.className = `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" style="border-radius: 8px; padding: 0.35rem 0.65rem; font-weight: 600; color: #0f3f28; background: ${active ? '#0f3f28' : '#ffffff'}; border: 1px solid #cbd5e1;">${label}</a>`;
            li.addEventListener('click', e => {
                e.preventDefault();
                if (!disabled) {
                    currentPage = page;
                    loadNotifications(0, currentPage);
                    loadNotifications(1, currentPage);
                }
            });
            ul.appendChild(li);
        };
        add('&laquo;', currentPage - 1, currentPage === 1);
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            add(i, i, false, i === currentPage);
        }
        add('&raquo;', currentPage + 1, currentPage === totalPages);
        pagination.appendChild(ul);
    }

    function loadNotifications(readStatus, page = 1) {
        const container = readStatus === 0 ? recentNotifications : oldNotifications;
        const countBadge = readStatus === 0 ? recentCount : oldCount;
        
        const search = searchInput.value;
        const fromDate = fromDateInput.value;
        const toDate = toDateInput.value;
        
        let url = `api/notifications.php?read=${readStatus}&search=${encodeURIComponent(search)}&page=${page}&limit=${perPage}`;
        if (fromDate) url += `&from_date=${encodeURIComponent(fromDate)}`;
        if (toDate) url += `&to_date=${encodeURIComponent(toDate)}`;

        currentPage = page;
        if (readStatus === 0) {
            recentPage = page;
        } else {
            oldPage = page;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                container.innerHTML = '';
                const notifications = data.data || [];
                const total = parseInt(data.total) || 0;
                const totalPages = parseInt(data.total_pages) || 1;
                
                countBadge.textContent = total;
                if (readStatus === 0) {
                    notificationCount.textContent = total;
                    recentTotalPages = totalPages;
                } else {
                    oldTotalPages = totalPages;
                }

                if (notifications.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <i class="bi bi-inbox"></i>
                            <p>No ${readStatus ? 'old' : 'recent'} notifications</p>
                        </div>
                    `;
                    renderPagination();
                } else {
                    notifications.forEach(notification => {
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
                                    <i class="bi bi-clock me-1"></i>${new Date(notification.created_at).toLocaleString()}
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
                    });
                    
                    renderPagination();
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-exclamation-triangle text-danger"></i>
                        <p class="text-danger">Error loading notifications</p>
                    </div>
                `;
                countBadge.textContent = '0';
                if (readStatus === 0) {
                    notificationCount.textContent = '0';
                    recentTotalPages = 1;
                } else {
                    oldTotalPages = 1;
                }
                renderPagination();
                showToast('Failed to load notifications', 'danger');
            });
    }

    function handleMarkRead(e) {
        if (e.target.closest('.mark-read-btn')) {
            const btn = e.target.closest('.mark-read-btn');
            const id = btn.dataset.id;
            const item = btn.closest('.list-group-item');
            const isCurrentlyRead = item.classList.contains('read');
            const newReadStatus = isCurrentlyRead ? 0 : 1;
            
            fetch(`api/notifications.php?id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ read: newReadStatus })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications(0, currentPage);
                        loadNotifications(1, currentPage);
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
                fetch(`api/notifications.php?id=${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadNotifications(0, currentPage);
                            loadNotifications(1, currentPage);
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
        fetch(`api/notifications.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ read: readStatus ? 0 : 1, mark_all: true })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(0, 1);
                    loadNotifications(1, 1);
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

    if (markAllRecentBtn) markAllRecentBtn.addEventListener('click', () => handleMarkAll(0));
    if (markAllOldBtn) markAllOldBtn.addEventListener('click', () => handleMarkAll(1));

    const dismissAllBadgeBtn = document.getElementById('dismiss-all-badge');
    if (dismissAllBadgeBtn) {
        dismissAllBadgeBtn.addEventListener('click', () => handleMarkAll(0));
    }

    if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', () => {
        searchInput.value = '';
        fromDateInput.value = '';
        toDateInput.value = '';
        loadNotifications(0, 1);
        loadNotifications(1, 1);
        showToast('Filters cleared', 'info');
    });

    // Debounce search input
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadNotifications(0, currentPage);
            loadNotifications(1, currentPage);
        }, 300);
    });

    fromDateInput.addEventListener('change', () => {
        loadNotifications(0, 1);
        loadNotifications(1, 1);
    });

    toDateInput.addEventListener('change', () => {
        loadNotifications(0, 1);
        loadNotifications(1, 1);
    });

    // Initial load
    loadNotifications(0, 1);
    loadNotifications(1, 1);

    // Real-time updates (check every 30 seconds)
    setInterval(() => {
        loadNotifications(0, currentPage);
        loadNotifications(1, currentPage);
    }, 30000);
});
