document.addEventListener('DOMContentLoaded', () => {
    const settingsForm = document.getElementById('settings-form');
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('toggle-sidebar');
    const confirmSaveBtn = document.getElementById('confirm-save-btn');
    const resetDefaultsBtn = document.getElementById('reset-defaults');
    const cancelChangesBtn = document.getElementById('cancel-changes');
    let pendingSaveData = null;
    let originalFormData = {};
    let formChanged = false;

    // Sidebar toggle
    if (sidebar && toggleButton) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // Auto-reorder toggle handler
    const autoReorderToggle = document.getElementById('auto_reorder_enabled');
    const autoReorderStatus = document.getElementById('auto-reorder-status');

    if (autoReorderToggle && autoReorderStatus) {
        autoReorderToggle.addEventListener('change', () => {
            autoReorderStatus.textContent = autoReorderToggle.checked ? 'Enabled' : 'Disabled';
            autoReorderStatus.className = autoReorderToggle.checked 
                ? 'ms-3 text-success fw-bold' 
                : 'ms-3 text-muted';
            formChanged = true;
            
            // Show info message
            if (autoReorderToggle.checked) {
                showToast('Auto-reorder system will generate reorder suggestions automatically', 'info');
            }
        });
    }

    // Toast notification system
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        const iconMap = {
            success: 'check-circle-fill',
            danger: 'x-circle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type} border-0 shadow-lg`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${iconMap[type]} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    // Validation
    function validateThresholds() {
        const lowStock = parseInt(document.getElementById('low_stock_threshold').value);
        const criticalStock = parseInt(document.getElementById('critical_stock_threshold').value);
        
        if (isNaN(lowStock) || isNaN(criticalStock)) {
            showToast('Please enter valid numbers for thresholds', 'warning');
            return false;
        }
        
        if (criticalStock >= lowStock) {
            showToast('Critical stock threshold must be less than low stock threshold', 'warning');
            return false;
        }
        return true;
    }

    // Real-time threshold validation
    document.getElementById('low_stock_threshold').addEventListener('input', validateThresholds);
    document.getElementById('critical_stock_threshold').addEventListener('input', validateThresholds);

    // Capture form data
    function captureFormData() {
        return {
            low_stock_threshold: document.getElementById('low_stock_threshold').value,
            critical_stock_threshold: document.getElementById('critical_stock_threshold').value,
            expiry_alert_days: document.getElementById('expiry_alert_days').value,
            auto_reorder_enabled: document.getElementById('auto_reorder_enabled').checked ? '1' : '0',
            notification_frequency: document.getElementById('notification_frequency').value,
            notification_method: document.getElementById('notification_method').value,
            notif_low_stock: document.getElementById('notif_low_stock').checked ? '1' : '0',
            notif_expiry: document.getElementById('notif_expiry').checked ? '1' : '0',
            notif_prescription: document.getElementById('notif_prescription').checked ? '1' : '0',
            currency_symbol: document.getElementById('currency_symbol').value,
            date_format: document.getElementById('date_format').value,
            records_per_page: document.getElementById('records_per_page').value,
            default_language: document.getElementById('default_language').value
        };
    }

    // Check whether form is dirty by comparing current values to original
    function isFormDirty() {
        try {
            const current = captureFormData();
            return JSON.stringify(current) !== JSON.stringify(originalFormData || {});
        } catch (err) {
            console.warn('isFormDirty check failed:', err);
            return false;
        }
    }

    // Load settings
    function loadSettings() {
        console.log('Loading settings...');
        
        fetch('api/sup_settings.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        })
            .then(response => {
                console.log('Load response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(result => {
                console.log('Loaded settings:', result);
                
                if (result.error || !result.success) {
                    showToast(result.error || 'Failed to load settings', 'danger');
                    return;
                }
                
                const data = result.data;
                
                // Populate form fields with proper null checks
                document.getElementById('low_stock_threshold').value = data.low_stock_threshold || '10';
                document.getElementById('critical_stock_threshold').value = data.critical_stock_threshold || '5';
                document.getElementById('expiry_alert_days').value = data.expiry_alert_days || '30';
                document.getElementById('auto_reorder_enabled').checked = data.auto_reorder_enabled == '1';
                document.getElementById('notification_frequency').value = data.notification_frequency || 'daily';
                document.getElementById('notification_method').value = data.notification_method || 'system';
                document.getElementById('notif_low_stock').checked = data.notif_low_stock != '0';
                document.getElementById('notif_expiry').checked = data.notif_expiry != '0';
                document.getElementById('notif_prescription').checked = data.notif_prescription != '0';
                document.getElementById('currency_symbol').value = data.currency_symbol || '₱';
                document.getElementById('date_format').value = data.date_format || 'Y-m-d';
                document.getElementById('records_per_page').value = data.records_per_page || '25';
                document.getElementById('default_language').value = data.default_language || 'en';
                
                // Update toggle status
                if (autoReorderToggle) {
                    autoReorderToggle.dispatchEvent(new Event('change'));
                }
                
                // Update stats
                updateStats(data);
                
                // Capture original data
                originalFormData = captureFormData();
                formChanged = false;
                
                // Check alerts automatically
                checkForAlerts();
                
                console.log('Settings loaded successfully');
            })
            .catch(error => {
                console.error('Error loading settings:', error);
                showToast('Failed to load settings: ' + error.message, 'danger');
            });
    }

    // Check for alerts based on current settings
    function checkForAlerts() {
        fetch('api/sup_check_alerts.php', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.alerts_created > 0) {
                console.log(`Created ${data.alerts_created} alerts based on current settings`);
            }
        })
        .catch(error => {
            console.error('Alert check error:', error);
        });
    }

    // Update statistics cards
    function updateStats(data) {
        const securityEl = document.getElementById('security-level');
        if (securityEl) {
            // Determine security level based on settings
            let securityLevel = 'Medium';
            let securityClass = 'text-warning';
            
            if (data.auto_reorder_enabled == '1' && 
                data.notif_low_stock == '1' && 
                data.notif_expiry == '1') {
                securityLevel = 'High';
                securityClass = 'text-success';
            } else if (data.notif_low_stock == '0' && data.notif_expiry == '0') {
                securityLevel = 'Low';
                securityClass = 'text-danger';
            }
            
            securityEl.textContent = securityLevel;
            securityEl.className = `mb-0 ${securityClass} fw-bold`;
        }
        
        const lastUpdateEl = document.getElementById('last-update');
        if (lastUpdateEl && data.last_update) {
            const updateDate = new Date(data.last_update);
            const now = new Date();
            const diffMs = now - updateDate;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 60) {
                lastUpdateEl.textContent = diffMins === 0 ? 'Just now' : `${diffMins} min ago`;
            } else if (diffHours < 24) {
                lastUpdateEl.textContent = `${diffHours}h ago`;
            } else {
                lastUpdateEl.textContent = `${diffDays}d ago`;
            }
        } else if (lastUpdateEl) {
            lastUpdateEl.textContent = 'Never';
        }
        
        const notifCountEl = document.getElementById('notif-count');
        if (notifCountEl) {
            notifCountEl.textContent = data.pending_notifications || '0';
        }
    }

    // Save settings
    settingsForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        if (!settingsForm.checkValidity()) {
            e.stopPropagation();
            settingsForm.classList.add('was-validated');
            showToast('Please fill all required fields correctly', 'warning');
            return;
        }
        
        if (!validateThresholds()) {
            return;
        }
        
        pendingSaveData = captureFormData();
        console.log('Preparing to save:', pendingSaveData);
        new bootstrap.Modal(document.getElementById('confirmSaveModal')).show();
    });

    // Confirm save
    confirmSaveBtn.addEventListener('click', () => {
        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmSaveModal'));
        
        confirmSaveBtn.disabled = true;
        confirmSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        
        console.log('Saving settings:', pendingSaveData);
        
        fetch('api/sup_settings.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(pendingSaveData)
        })
        .then(response => {
            console.log('Save response status:', response.status);
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.error || `HTTP error! status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Save response:', data);
            modal.hide();
            
            if (data.error || !data.success) {
                showToast(data.error || 'Failed to save settings', 'danger');
            } else {
                showToast('✅ ' + (data.message || 'Settings saved successfully!'), 'success');
                settingsForm.classList.remove('was-validated');
                formChanged = false;

                if (pendingSaveData.records_per_page) {
                    window.RECORDS_PER_PAGE = parseInt(pendingSaveData.records_per_page, 10) || 25;
                }
                if (pendingSaveData.currency_symbol) {
                    window.CURRENCY_SYMBOL = pendingSaveData.currency_symbol;
                }
                if (pendingSaveData.date_format) {
                    window.SYSTEM_DATE_FORMAT = pendingSaveData.date_format;
                }
                if (pendingSaveData.low_stock_threshold) {
                    window.LOW_STOCK_THRESHOLD = parseInt(pendingSaveData.low_stock_threshold, 10) || 10;
                }
                if (pendingSaveData.critical_stock_threshold) {
                    window.CRITICAL_STOCK_THRESHOLD = parseInt(pendingSaveData.critical_stock_threshold, 10) || 5;
                }
                if (pendingSaveData.expiry_alert_days) {
                    window.EXPIRY_ALERT_DAYS = parseInt(pendingSaveData.expiry_alert_days, 10) || 30;
                }
                
                // Show what changed
                const changedSettings = [];
                for (let key in pendingSaveData) {
                    if (originalFormData[key] !== pendingSaveData[key]) {
                        changedSettings.push(key.replace(/_/g, ' '));
                    }
                }
                
                if (changedSettings.length > 0) {
                    console.log('Changed settings:', changedSettings);
                    setTimeout(() => {
                        showToast(`Updated: ${changedSettings.join(', ')}`, 'info');
                    }, 1000);
                }
                
                // Reload settings after a short delay to confirm from database
                setTimeout(() => {
                    loadSettings();
                }, 1500);
            }
        })
        .catch(error => {
            modal.hide();
            console.error('Error saving settings:', error);
            showToast('❌ Failed to save settings: ' + error.message, 'danger');
        })
        .finally(() => {
            confirmSaveBtn.disabled = false;
            confirmSaveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm & Save';
        });
    });

    // Reset defaults
    if (resetDefaultsBtn) {
        resetDefaultsBtn.addEventListener('click', () => {
            if (confirm('⚠️ Are you sure you want to reset all settings to default values?\n\nThis will:\n- Set low stock threshold to 10\n- Set critical stock threshold to 5\n- Set expiry alert to 30 days\n- Enable all notifications\n- Disable auto-reorder\n- Reset all preferences')) {
                const defaultSettings = {
                    low_stock_threshold: '10',
                    critical_stock_threshold: '5',
                    expiry_alert_days: '30',
                    auto_reorder_enabled: '0',
                    notification_frequency: 'daily',
                    notification_method: 'system',
                    notif_low_stock: '1',
                    notif_expiry: '1',
                    notif_prescription: '1',
                    currency_symbol: '₱',
                    date_format: 'Y-m-d',
                    records_per_page: '25',
                    default_language: 'en'
                };
                
                console.log('Resetting to defaults:', defaultSettings);
                
                fetch('api/sup_settings.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(defaultSettings)
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Reset response:', data);
                    if (data.error || !data.success) {
                        showToast(data.error || 'Failed to reset settings', 'danger');
                    } else {
                        showToast('✅ Settings reset to defaults successfully', 'success');
                        setTimeout(() => loadSettings(), 500);
                    }
                })
                .catch(error => {
                    console.error('Error resetting settings:', error);
                    showToast('Failed to reset settings: ' + error.message, 'danger');
                });
            }
        });
    }

    // Cancel changes
    if (cancelChangesBtn) {
        cancelChangesBtn.addEventListener('click', () => {
            if (isFormDirty() && confirm('🔄 Discard all unsaved changes?')) {
                if (settingsForm) settingsForm.classList.remove('was-validated');
                loadSettings();
                showToast('Changes discarded', 'info');
            } else if (!isFormDirty()) {
                showToast('No changes to discard', 'info');
            }
        });
    }

    // Form change detection (guarded)
    if (settingsForm) {
        settingsForm.addEventListener('change', () => { 
            formChanged = true;
            console.log('Form changed');
        });
        settingsForm.addEventListener('input', () => { 
            formChanged = true; 
        });
    }

    // Warn before leaving — use robust dirty check
    window.addEventListener('beforeunload', (e) => {
        if (isFormDirty()) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            return e.returnValue;
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            if (settingsForm) settingsForm.dispatchEvent(new Event('submit'));
        }
        if (e.key === 'Escape') {
            const modalEl = document.getElementById('confirmSaveModal');
            const modal = modalEl ? bootstrap.Modal.getInstance(modalEl) : null;
            if (modal) modal.hide();
        }
    });

    // Auto-refresh notification count every 30 seconds
    setInterval(() => {
        fetch('api/sup_settings.php', {
            method: 'GET',
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.data) {
                const notifCountEl = document.getElementById('notif-count');
                if (notifCountEl) {
                    notifCountEl.textContent = result.data.pending_notifications || '0';
                }
            }
        })
        .catch(error => console.error('Notification count refresh error:', error));
    }, 30000);

    // Initialize
    console.log('Initializing settings page...');
    loadSettings();
});