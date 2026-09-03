<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/includes/supplier_auth.php';
requireSupplierPage($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Medicine Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .stats-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .stats-card.system { border-left-color: #007bff; }
        .stats-card.security { border-left-color: #28a745; }
        .stats-card.notifications { border-left-color: #ffc107; }
        .stats-card.backup { border-left-color: #dc3545; }
        
        .settings-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s;
        }
        .settings-section:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .settings-section h5 {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .settings-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 0.75rem 2rem;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #65408b 100%);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #28a745;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>

    <div class="main-content">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="bi bi-gear-fill"></i> System Settings</h2>
                <p class="text-muted mb-0">Configure system preferences and behaviors</p>
            </div>
            <button class="btn btn-outline-secondary" id="reset-defaults">
                <i class="bi bi-arrow-counterclockwise"></i> Reset to Defaults
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stats-card system">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">System Status</h6>
                                <h5 class="mb-0 text-success">Active</h5>
                            </div>
                            <i class="bi bi-activity fs-1 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card security">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Security Level</h6>
                                <h5 class="mb-0" id="security-level">Medium</h5>
                            </div>
                            <i class="bi bi-shield-check fs-1 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card notifications">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Last Update</h6>
                                <h5 class="mb-0" id="last-update">Never</h5>
                            </div>
                            <i class="bi bi-clock-history fs-1 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card backup">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Notifications</h6>
                                <h5 class="mb-0" id="notif-count">0</h5>
                            </div>
                            <i class="bi bi-bell-fill fs-1 text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Form -->
        <form id="settings-form" novalidate>
            
            <!-- Inventory Settings -->
            <div class="settings-section">
                <div class="d-flex align-items-center mb-3">
                    <div class="settings-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h5 class="mb-0">Inventory Management</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="low_stock_threshold" class="form-label">
                            Low Stock Threshold *
                            <i class="bi bi-info-circle text-muted" title="Alert when stock falls below this level"></i>
                        </label>
                        <input type="number" class="form-control" id="low_stock_threshold" required min="1" max="1000" value="10">
                        <div class="invalid-feedback">Threshold must be between 1 and 1000.</div>
                        <div class="form-text">Set minimum stock level for low stock alerts</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="critical_stock_threshold" class="form-label">
                            Critical Stock Threshold *
                            <i class="bi bi-info-circle text-muted" title="Urgent alert when stock is critically low"></i>
                        </label>
                        <input type="number" class="form-control" id="critical_stock_threshold" required min="1" max="100" value="5">
                        <div class="invalid-feedback">Critical threshold must be between 1 and 100.</div>
                        <div class="form-text">Set critical stock level for urgent alerts</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="expiry_alert_days" class="form-label">
                            Expiry Alert Days *
                        </label>
                        <input type="number" class="form-control" id="expiry_alert_days" required min="1" max="365" value="30">
                        <div class="invalid-feedback">Must be between 1 and 365 days.</div>
                        <div class="form-text">Alert X days before medicine expires</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="auto_reorder_enabled" class="form-label">
                            Enable Auto-Reorder System
                        </label>
                        <div class="d-flex align-items-center">
                            <label class="toggle-switch">
                                <input type="checkbox" id="auto_reorder_enabled">
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="ms-3 text-muted" id="auto-reorder-status">Disabled</span>
                        </div>
                        <div class="form-text">Automatically generate reorder suggestions</div>
                    </div>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="settings-section">
                <div class="d-flex align-items-center mb-3">
                    <div class="settings-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-bell"></i>
                    </div>
                    <h5 class="mb-0">Notification Preferences</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="notification_frequency" class="form-label">Notification Frequency *</label>
                        <select class="form-select" id="notification_frequency" required>
                            <option value="">Select Frequency</option>
                            <option value="realtime">Real-time</option>
                            <option value="hourly">Hourly</option>
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                        </select>
                        <div class="invalid-feedback">Please select a notification frequency.</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="notification_method" class="form-label">Notification Method *</label>
                        <select class="form-select" id="notification_method" required>
                            <option value="">Select Method</option>
                            <option value="system" selected>System Only</option>
                            <option value="email">Email</option>
                            <option value="both">System & Email</option>
                        </select>
                        <div class="invalid-feedback">Please select a notification method.</div>
                    </div>
                    
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Notification Types</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notif_low_stock" checked>
                                    <label class="form-check-label" for="notif_low_stock">
                                        Low Stock Alerts
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notif_expiry" checked>
                                    <label class="form-check-label" for="notif_expiry">
                                        Expiry Alerts
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notif_prescription" checked>
                                    <label class="form-check-label" for="notif_prescription">
                                        Prescription Updates
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Preferences -->
            <div class="settings-section">
                <div class="d-flex align-items-center mb-3">
                    <div class="settings-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-sliders"></i>
                    </div>
                    <h5 class="mb-0">System Preferences</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="currency_symbol" class="form-label">Currency Symbol *</label>
                        <input type="text" class="form-control" id="currency_symbol" required maxlength="10" value="₱">
                        <div class="invalid-feedback">Currency symbol is required.</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="date_format" class="form-label">Date Format *</label>
                        <select class="form-select" id="date_format" required>
                            <option value="">Select Format</option>
                            <option value="Y-m-d" selected>YYYY-MM-DD</option>
                            <option value="m/d/Y">MM/DD/YYYY</option>
                            <option value="d/m/Y">DD/MM/YYYY</option>
                            <option value="d-M-Y">DD-Mon-YYYY</option>
                        </select>
                        <div class="invalid-feedback">Please select a date format.</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="records_per_page" class="form-label">Records Per Page *</label>
                        <select class="form-select" id="records_per_page" required>
                            <option value="">Select</option>
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <div class="invalid-feedback">Please select records per page.</div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="default_language" class="form-label">Default Language *</label>
                        <select class="form-select" id="default_language" required>
                            <option value="">Select Language</option>
                            <option value="en" selected>English</option>
                            <option value="es">Spanish</option>
                            <option value="fr">French</option>
                            <option value="tl">Tagalog</option>
                        </select>
                        <div class="invalid-feedback">Please select a language.</div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" id="cancel-changes">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Confirm Save Modal -->
    <div class="modal fade" id="confirmSaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Save</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to save these settings?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle"></i> Changes will affect system-wide behavior and all users.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-save-btn">
                        <i class="bi bi-check-circle"></i> Confirm & Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sup_settings.js"></script>
</body>
</html>
