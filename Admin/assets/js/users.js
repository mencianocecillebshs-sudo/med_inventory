document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.getElementById('user-table');
    const addForm = document.getElementById('add-user-form');
    const editForm = document.getElementById('edit-user-form');
    const toggleButton = document.getElementById('toggle-sidebar-mobile');
    const pagination = document.getElementById('users-pagination');
    const pageInfo = document.getElementById('users-page-info');
    const searchInput = document.getElementById('user-search');
    let usersCache = [];
    let usersPage = 1;
    const perPage = window.RECORDS_PER_PAGE || 10;

    // Sidebar toggle for mobile
    if (toggleButton) {
        toggleButton.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
                const mainContent = document.querySelector('.main-content');
                if (sidebar.classList.contains('active')) {
                    mainContent.style.marginLeft = '250px';
                } else {
                    mainContent.style.marginLeft = '0';
                }
            }
        });
    }

    // Function to get role badge HTML
    function getRoleBadge(role) {
        const label = (role || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        return `<span class="role-badge">${escapeHtml(label)}</span>`;
    }

    // Function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
        return date.toLocaleDateString('en-US', options);
    }

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
                if (!disabled) {
                    usersPage = page;
                    renderUsers();
                }
            });
            ul.appendChild(li);
        };
        add('&laquo;', usersPage - 1, usersPage === 1);
        for (let i = Math.max(1, usersPage - 2); i <= Math.min(totalPages, usersPage + 2); i++) add(i, i, false, i === usersPage);
        add('&raquo;', usersPage + 1, usersPage === totalPages);
        pagination.appendChild(ul);
    }

    function getFilteredUsers() {
        const term = (searchInput?.value || '').trim().toLowerCase();
        if (!term) return usersCache;
        return usersCache.filter(u =>
            (u.username || '').toLowerCase().includes(term) ||
            (u.name || '').toLowerCase().includes(term) ||
            (u.role || '').toLowerCase().includes(term)
        );
    }

    function renderUsers() {
        tableBody.innerHTML = '';
        const filtered = getFilteredUsers();
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (usersPage > totalPages) usersPage = totalPages;
        const start = (usersPage - 1) * perPage;
        const users = filtered.slice(start, start + perPage);
        if (pageInfo) pageInfo.textContent = filtered.length ? `Showing ${start + 1}-${Math.min(start + perPage, filtered.length)} of ${filtered.length}` : 'No users';
        if (users.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No users found</td></tr>';
            renderPagination(0);
            return;
        }
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${escapeHtml(user.username)}</strong></td>
                <td>${escapeHtml(user.name || user.username)}</td>
                <td>${getRoleBadge(user.role)}</td>
                <td>${formatDate(user.created_at)}</td>
                <td>
                    <button class="btn btn-sm btn-warning action-btn edit-btn" data-id="${user.id}">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-danger action-btn delete-btn" data-id="${user.id}">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
        renderPagination(totalPages);
    }

    // Load users function
    function loadUsers() {
        fetch('api/users.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                usersCache = Array.isArray(data) ? data : [];
                usersPage = 1;
                renderUsers();
            })
            .catch(error => {
                console.error('Error loading users:', error);
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error loading users. Please try again.</td></tr>';
            });
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    // Add user form submission
    addForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Remove previous validation
        addForm.classList.remove('was-validated');
        
        // Validate form
        if (!addForm.checkValidity()) {
            e.stopPropagation();
            addForm.classList.add('was-validated');
            return;
        }

        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            showNotification('Passwords do not match', 'danger');
            document.getElementById('confirm_password').setCustomValidity('Passwords do not match');
            document.getElementById('confirm_password').classList.add('is-invalid');
            return;
        } else {
            document.getElementById('confirm_password').setCustomValidity('');
            document.getElementById('confirm_password').classList.remove('is-invalid');
        }

        const formData = new FormData(addForm);
        const submitBtn = addForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        // Convert FormData to JSON
        const jsonData = {};
        formData.forEach((value, key) => {
            if (key !== 'confirm_password') { // Don't send confirm_password to server
                jsonData[key] = value;
            }
        });

        fetch('api/users.php', { 
            method: 'POST', 
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                    modal.hide();
                    addForm.reset();
                    addForm.classList.remove('was-validated');
                    loadUsers();
                    
                    showNotification('User added successfully!', 'success');
                } else {
                    showNotification(data.message || 'Error adding user', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error adding user. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    });

    // Edit user form submission
    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Remove previous validation
        editForm.classList.remove('was-validated');
        
        // Validate form
        if (!editForm.checkValidity()) {
            e.stopPropagation();
            editForm.classList.add('was-validated');
            return;
        }

        const formData = new FormData(editForm);
        const userId = formData.get('edit_id');
        const submitBtn = editForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';

        // Convert FormData to JSON
        const jsonData = {};
        formData.forEach((value, key) => {
            if (key !== 'edit_id') {
                jsonData[key] = value;
            }
        });

        fetch(`api/users.php?id=${userId}`, { 
            method: 'PUT', 
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(jsonData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                    modal.hide();
                    editForm.reset();
                    editForm.classList.remove('was-validated');
                    loadUsers();
                    
                    showNotification('User updated successfully!', 'success');
                } else {
                    showNotification(data.message || 'Error updating user', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating user. Please try again.', 'danger');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    });

    // Table event delegation for edit and delete buttons
    tableBody.addEventListener('click', function(e) {
        const target = e.target.closest('button');
        if (!target) return;

        if (target.classList.contains('edit-btn')) {
            const id = target.dataset.id;
            fetch(`api/users.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success === false) {
                        showNotification(data.message || 'Error fetching user data', 'danger');
                        return;
                    }
                    
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_username').value = data.username;
                    document.getElementById('edit_name').value = data.name || data.username;
                    document.getElementById('edit_role').value = data.role;
                    document.getElementById('edit_password').value = '';
                    
                    editForm.classList.remove('was-validated');
                    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error fetching user data. Please try again.', 'danger');
                });
        } else if (target.classList.contains('delete-btn')) {
            const id = target.dataset.id;
            const user = usersCache.find(u => String(u.id) === String(id));
            pendingDeleteUserBtn = target;
            document.getElementById('delete-user-name').textContent = user ? (user.name || user.username) : 'this user';
            deleteUserModal?.show();
        }
    });

    const deleteUserModalEl = document.getElementById('deleteUserModal');
    const deleteUserModal = deleteUserModalEl ? new bootstrap.Modal(deleteUserModalEl) : null;
    let pendingDeleteUserBtn = null;

    document.getElementById('confirm-delete-user-btn')?.addEventListener('click', () => {
        const target = pendingDeleteUserBtn;
        if (!target) return;
        const id = target.dataset.id;
        target.disabled = true;
        const originalText = target.innerHTML;
        target.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        fetch(`api/users.php?id=${id}`, { method: 'DELETE' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUsers();
                    showNotification('User deleted successfully!', 'success');
                } else {
                    showNotification(data.message || 'Error deleting user', 'danger');
                    target.disabled = false;
                    target.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error deleting user. Please try again.', 'danger');
                target.disabled = false;
                target.innerHTML = originalText;
            })
            .finally(() => {
                deleteUserModal?.hide();
                pendingDeleteUserBtn = null;
            });
    });

    // Password confirmation validation for add form
    document.getElementById('confirm_password').addEventListener('input', function() {
        const password = document.getElementById('password').value;
        const confirmPassword = this.value;
        
        if (password !== confirmPassword) {
            this.setCustomValidity('Passwords do not match');
            this.classList.add('is-invalid');
        } else {
            this.setCustomValidity('');
            this.classList.remove('is-invalid');
        }
    });

    document.getElementById('password').addEventListener('input', function() {
        const confirmPassword = document.getElementById('confirm_password');
        if (confirmPassword.value) {
            if (this.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                confirmPassword.classList.add('is-invalid');
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
            }
        }
    });

    // Reset form validation when modals are hidden
    document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
        addForm.reset();
        addForm.classList.remove('was-validated');
        document.getElementById('confirm_password').setCustomValidity('');
        document.getElementById('confirm_password').classList.remove('is-invalid');
    });

    // Also clear explicitly right before showing — form.reset() alone can be
    // undone by browser autofill re-populating saved username/password values
    // after the modal re-opens, so each field is blanked directly too.
    document.getElementById('addUserModal').addEventListener('show.bs.modal', function() {
        addForm.reset();
        addForm.classList.remove('was-validated');
        ['username', 'name', 'password', 'confirm_password'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('role').value = 'admin';
        document.getElementById('confirm_password').setCustomValidity('');
        document.getElementById('confirm_password').classList.remove('is-invalid');
    });

    document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function() {
        editForm.reset();
        editForm.classList.remove('was-validated');
    });

    // Show notification function
    function showNotification(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }

    // Initial load
    loadUsers();

    searchInput?.addEventListener('input', () => {
        usersPage = 1;
        renderUsers();
    });
});
