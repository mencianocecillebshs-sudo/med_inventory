<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; overflow: hidden; }
        .main-content { margin-left: 250px; padding: 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 768px) { .main-content { margin-left: 0; padding: 1rem; } }
        .card { border-radius: 15px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); background: #fff; border: none; }
        .table { border-radius: 12px; overflow: hidden; margin-bottom: 0; }
        .table th { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; font-weight: 600; padding: 1rem; border: none; }
        .table td { vertical-align: middle; color: #2d3748; padding: 1rem; border-color: #e2e8f0; }
        .table tbody tr { transition: all 0.2s ease; }
        .table tbody tr:hover { background: #f7fafc; transform: translateX(2px); }
        .role-badge { padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; border: 1px solid #cbd5e1; color: #334155; background: transparent; text-transform: none; letter-spacing: 0; }
        .action-btn { padding: 0.5rem 1rem; font-size: 0.9rem; border-radius: 8px; transition: all 0.3s ease; margin: 0 0.2rem; font-weight: 500; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); }
        .btn-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(135deg, #0f3f28 0%, #1b5e3f 100%); }
        .btn-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border: none; color: #78350f; }
        .btn-warning:hover { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
        .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; }
        .btn-danger:hover { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }
        .modal-content { border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
        .modal-header { border-bottom: none; padding: 1.25rem 1.5rem; border-radius: 16px 16px 0 0; }
        .modal-header.bg-primary { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); }
        .modal-header.bg-warning { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); }
        .form-control, .form-select { border-radius: 10px; border: 2px solid #e2e8f0; transition: all 0.3s ease; padding: 0.6rem 0.85rem; }
        .form-control:focus, .form-select:focus { border-color: #1b5e3f; box-shadow: 0 0 0 0.2rem rgba(27, 94, 63, 0.15); transform: translateY(-1px); }
        .form-label { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }
        .invalid-feedback { font-size: 0.85rem; color: #dc2626; font-weight: 500; }
        #toggle-sidebar-mobile { position: fixed; top: 1rem; left: 1rem; z-index: 1100; background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: #fff; border-radius: 50%; width: 45px; height: 45px; border: none; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); }
        h2 { color: #1e293b; font-weight: 700; }
        .card-body { padding: 0; }
        .page-header { background: linear-gradient(135deg, #1b5e3f 0%, #0f3f28 100%); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; box-shadow: 0 8px 24px rgba(27, 94, 63, 0.2); }
        .page-header h2 { color: white; margin: 0; }
        .form-text { font-size: 0.85rem; color: #64748b; }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <button class="btn d-lg-none" id="toggle-sidebar-mobile"><i class="bi bi-list"></i></button>
    <div class="main-content admin-table-page">
        <div class="page-header">
            <h2><i class="bi bi-people me-2"></i> Users Management</h2>
        </div>
        <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-primary action-btn" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-circle me-1"></i> Add User</button>
            <input type="text" class="form-control" id="user-search" placeholder="Search users..." style="max-width: 300px;">
        </div>
        <div class="card admin-table-card">
            <div class="card-body p-0">
                <div class="table-responsive admin-table-scroll">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="user-table"></tbody>
                    </table>
                </div>
                <div class="admin-table-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small id="users-page-info" class="text-muted"></small>
                    <nav id="users-pagination" aria-label="Users pages"></nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-user-form" novalidate>
                        <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback">Username is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                            <div class="invalid-feedback">Full Name is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="invalid-feedback">Password is required.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <div class="invalid-feedback">Passwords must match.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="pharmacist">Pharmacist</option>
                                <option value="staff">Staff</option>
                                <option value="supplier">Supplier</option>
                            </select>
                            <div class="invalid-feedback">Role is required.</div>
                        </div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary action-btn me-2"><i class="bi bi-save me-1"></i> Save User</button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-user-form" novalidate>
                        <input type="hidden" id="edit_id" name="edit_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                            <div class="invalid-feedback">Username is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                            <div class="invalid-feedback">Full Name is required.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <div class="form-text">Leave blank to keep current password.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="pharmacist">Pharmacist</option>
                                <option value="staff">Staff</option>
                                <option value="supplier">Supplier</option>
                            </select>
                            <div class="invalid-feedback">Role is required.</div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary action-btn me-2"><i class="bi bi-save me-1"></i> Update User</button>
                            <button type="button" class="btn btn-secondary action-btn" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteUserModalLabel"><i class="bi bi-trash me-2"></i>Delete User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to delete "<strong id="delete-user-name"></strong>"?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-user-btn">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/users.js"></script>
</body>
</html>
