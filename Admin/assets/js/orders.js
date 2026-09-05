document.addEventListener("DOMContentLoaded", () => {
    const tbody          = document.getElementById("orders-table");
    const filterStatus   = document.getElementById("filter-status");
    const filterSearch   = document.getElementById("filter-search");
    const filterExpected = document.getElementById("filter-expected");
    const pagination     = document.getElementById("orders-pagination");
    const pageInfo       = document.getElementById("orders-page-info");
    const orderSupplier  = document.getElementById("order-supplier");
    const preferredSupplierCallout = document.getElementById("preferred-supplier-callout");
    const medicineContainer  = document.getElementById("medicine-container");
    const addMedicineRowBtn  = document.getElementById("add-medicine-row-btn");
    const totalCost      = document.getElementById("total-cost");
    const addOrderModalElement = document.getElementById("addOrderModal");
    const addOrderForm   = document.getElementById("add-order-form");
    const initialStatus = new URLSearchParams(window.location.search).get("status");

    if (filterStatus && initialStatus) {
        filterStatus.value = initialStatus;
    }

    // ── Fetch helper ────────────────────────────────────────────────
    // The API always returns a JSON body — { success, message, ... } —
    // even on 4xx/5xx responses. Previously several call sites did
    // `if (!r.ok) throw new Error(...)` BEFORE reading that body, which
    // discarded the real, user-facing error message and replaced it with
    // a generic "HTTP 500: Internal Server Error". This helper always
    // parses the JSON first, so the actual message from the server makes
    // it to the UI regardless of status code. A rejection from this
    // helper now means "the response wasn't valid JSON" (a true network
    // or server-crash issue), not "the server returned a 4xx/5xx".
    function fetchJSON(url, options) {
        return fetch(url, options).then(r =>
            r.json()
                .catch(() => {
                    throw new Error("HTTP " + r.status + ": " + r.statusText);
                })
                .then(data => {
                    if (!data || typeof data.success === "undefined") {
                        throw new Error("HTTP " + r.status + ": " + r.statusText);
                    }
                    return data;
                })
        );
    }

    // Remove-medicine-row modal
    const removeMedicineRowModalEl  = document.getElementById("removeMedicineRowModal");
    const removeMedicineRowModal    = removeMedicineRowModalEl
        ? bootstrap.Modal.getOrCreateInstance(removeMedicineRowModalEl) : null;
    const confirmRemoveMedicineBtn  = document.getElementById("confirm-remove-medicine-btn");
    let pendingRemoveMedicineRow    = null;
    const confirmReceivedModalEl = document.getElementById("confirmReceivedModal");
    const confirmReceivedModal = confirmReceivedModalEl
        ? bootstrap.Modal.getOrCreateInstance(confirmReceivedModalEl) : null;
    const confirmReceivedBtn = document.getElementById("confirm-received-order-btn");
    const receivedOrderIdLabel = document.getElementById("received-order-id");
    let pendingReceivedOrderId = null;
    const orderDetailsModalEl = document.getElementById("orderDetailsModal");
    const orderDetailsModal = orderDetailsModalEl
        ? bootstrap.Modal.getOrCreateInstance(orderDetailsModalEl) : null;
    const detailsOrderId = document.getElementById("details-order-id");
    const detailsSummary = document.getElementById("order-details-summary");
    const detailsItems = document.getElementById("order-details-items");
    const detailsTotal = document.getElementById("order-details-total");

    confirmRemoveMedicineBtn?.addEventListener("click", () => {
        if (!pendingRemoveMedicineRow) return;
        pendingRemoveMedicineRow.remove();
        pendingRemoveMedicineRow = null;
        updateGrandTotal();
        refreshAllSelects();
        removeMedicineRowModal?.hide();
    });

    removeMedicineRowModalEl?.addEventListener("hidden.bs.modal", () => {
        pendingRemoveMedicineRow = null;
    });

    let addOrderModal;
    let availableMedicines = [];
    let allOrders          = [];
    let currentPage        = 1;
    const perPage          = window.RECORDS_PER_PAGE || 10;

    const paymentLabels = {
        cash:          "Cash",
        gcash:         "GCash",
        bank_transfer: "Bank Transfer",
        check:         "Check/PDC"
    };

    const paymentReferenceLabels = {
        gcash:         "GCash Number",
        bank_transfer: "Bank Account Number"
    };

    const statusLabels = {
        pending: "Pending",
        ordered: "Pending",
        accepted: "Accepted",
        declined: "Declined",
        shipped: "Shipped",
        out_for_delivery: "Out for Delivery",
        delivered: "Delivered",
        fulfilled: "Fulfilled",
        completed: "Fulfilled",
        cancelled: "Cancelled"
    };

    function esc(value) {
        return String(value ?? "").replace(/[&<>"']/g, char => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        })[char]);
    }

    function statusBadge(status) {
        const normalized = status === "ordered" ? "pending" : status === "completed" ? "fulfilled" : status;
        const label = statusLabels[status] || status || "Pending";
        return '<span class="status-badge status-' + esc(normalized || "pending") + '">' + esc(label) + "</span>";
    }

    function formatDateTime(value) {
        if (!value) return "N/A";
        const normalized = String(value).replace(" ", "T");
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return esc(value);
        return date.toLocaleString([], {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    function formatDate(value) {
        if (!value) return "N/A";
        const date = new Date(String(value).replace(" ", "T"));
        if (Number.isNaN(date.getTime())) return esc(value);
        return date.toLocaleDateString();
    }

    function deliveryBadge(status) {
        const map = {
            accepted:         { label: "Accepted",         cls: "status-accepted" },
            shipped:          { label: "Shipped",          cls: "status-shipped" },
            out_for_delivery: { label: "Out for Delivery", cls: "status-out_for_delivery" },
            delivered:        { label: "Delivered",        cls: "status-delivered" }
        };
        const d = map[status];
        if (!d) return '<span class="text-muted small">—</span>';
        return '<span class="status-badge ' + d.cls + '">' + d.label + "</span>";
    }

    function showToast(message, type) {
        type = type || "success";
        const container = document.getElementById("toast-container") || createToastContainer();
        const toast = document.createElement("div");
        const icon = type === "success" ? "check-circle-fill"
            : type === "danger" ? "x-circle-fill"
            : type === "warning" ? "exclamation-triangle-fill"
            : "info-circle-fill";
        const title = type === "success" ? "Success"
            : type === "danger" ? "Something went wrong"
            : type === "warning" ? "Check this"
            : "Notice";
        toast.className = "system-toast toast-" + type + " fade show";
        toast.setAttribute("role", "alert");
        toast.innerHTML =
            '<div class="system-toast-icon"><i class="bi bi-' + icon + '"></i></div>' +
            '<div><div class="system-toast-title">' + title + '</div>' +
            '<div class="system-toast-message">' + esc(message) + '</div></div>' +
            '<button type="button" class="btn-close" aria-label="Close"></button>';
        toast.querySelector(".btn-close").addEventListener("click", () => toast.remove());
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 180);
        }, 5000);
    }

    function createToastContainer() {
        const container = document.createElement("div");
        container.id = "toast-container";
        document.body.appendChild(container);
        return container;
    }

    if (addOrderModalElement) {
        addOrderModal = new bootstrap.Modal(addOrderModalElement);
        addOrderModalElement.addEventListener("hidden.bs.modal", () => {
            addOrderForm.reset();
            medicineContainer.innerHTML = "";
            totalCost.value    = "0.00";
            availableMedicines = [];
            orderSupplier.value    = "";
            orderSupplier.disabled = false;
            preferredSupplierCallout?.classList.remove("show");
        });
    }

    const dbCheckCell = document.querySelector("#orders-table tr td");
    if (dbCheckCell && dbCheckCell.textContent.includes("Database")) {
        const newOrderBtn = document.querySelector("[data-bs-target=\"#addOrderModal\"]");
        if (newOrderBtn) newOrderBtn.disabled = true;
        showToast("Database connection required for orders. Check server.", "warning");
        tbody.innerHTML = '<tr><td colspan="12" class="text-center text-warning py-4">DB issue - cannot load.</td></tr>';
        return;
    }

    function renderPagination(totalPages) {
        if (!pagination) return;
        pagination.innerHTML = "";
        if (totalPages <= 1) return;
        const ul = document.createElement("ul");
        ul.className = "pagination pagination-sm mb-0";
        const addItem = (label, page, disabled, active) => {
            disabled = disabled || false; active = active || false;
            const li = document.createElement("li");
            li.className = "page-item" + (disabled ? " disabled" : "") + (active ? " active" : "");
            li.innerHTML = '<a class="page-link" href="#">' + label + "</a>";
            li.addEventListener("click", e => {
                e.preventDefault();
                if (!disabled) { currentPage = page; renderOrders(); }
            });
            ul.appendChild(li);
        };
        addItem("&laquo;", currentPage - 1, currentPage === 1, false);
        for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
            addItem(i, i, false, i === currentPage);
        }
        addItem("&raquo;", currentPage + 1, currentPage === totalPages, false);
        pagination.appendChild(ul);
    }

    function money(value) {
        return "\u20b1" + Number(value || 0).toFixed(2);
    }

    function itemTypeLabel(value) {
        return value === "non-medicine" ? "Other Product" : "Medicine";
    }

    function getOrderItems(order) {
        return Array.isArray(order.items) && order.items.length
            ? order.items
            : [{
                medicine_name: order.medicine_name,
                item_type: order.item_type,
                quantity: order.quantity,
                unit_price: order.unit_price,
                subtotal: order.display_total ?? order.subtotal ?? order.total_amount
            }];
    }

    function normalizeOrders(rows) {
        const grouped = new Map();
        rows.forEach(row => {
            const orderId = String(row.order_id || row.id || "");
            if (!orderId) return;
            if (!grouped.has(orderId)) {
                grouped.set(orderId, {
                    ...row,
                    items: [],
                    item_count: 0,
                    total_quantity: 0,
                    display_total: Number(row.total_amount || 0)
                });
            }
            const order = grouped.get(orderId);
            const quantity = Number(row.quantity || 0);
            const subtotal = Number(row.display_total ?? row.subtotal ?? 0);
            order.items.push({
                medicine_id: row.medicine_id,
                medicine_name: row.medicine_name || "N/A",
                item_type: row.item_type || "medicine",
                quantity,
                unit_price: Number(row.unit_price || 0),
                subtotal
            });
            order.item_count = order.items.length;
            order.total_quantity += quantity;
            if (!Number(row.total_amount || 0)) {
                order.display_total = Number(order.display_total || 0) + subtotal;
            }
        });
        return Array.from(grouped.values());
    }

    function getOrderSearchText(order) {
        const itemText = getOrderItems(order)
            .map(item => [item.medicine_name, itemTypeLabel(item.item_type)].join(" "))
            .join(" ");
        return [
            order.order_id || order.id || "",
            order.supplier_name || "",
            itemText
        ].join(" ").toLowerCase();
    }

    function renderOrderSummary(order) {
        const items = getOrderItems(order);
        const visible = items.map(item =>
            '<span class="order-summary-pill" title="' + esc(item.medicine_name || "N/A") + '">' +
                '<span class="order-summary-name">' + esc(item.medicine_name || "N/A") + '</span>' +
                '<span class="order-summary-meta">x' + esc(item.quantity || 0) + '</span>' +
            '</span>'
        ).join("");
        return '<div class="order-summary-inline">' + visible + '</div>';
    }

    function getFilteredOrders() {
        const status   = filterStatus.value || "";
        const search   = ((filterSearch ? filterSearch.value : "") || "").trim().toLowerCase();
        const expected = (filterExpected ? filterExpected.value : "") || "";
        return allOrders.filter(order => {
            const text = getOrderSearchText(order);
            return (!status   || order.status === status || order.delivery_status === status)
                && (!search   || text.includes(search))
                && (!expected || (order.expected_delivery && order.expected_delivery <= expected));
        });
    }

    function renderOrders() {
        const filtered   = getFilteredOrders();
        const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;
        const start    = (currentPage - 1) * perPage;
        const pageRows = filtered.slice(start, start + perPage);
        tbody.innerHTML = "";
        document.getElementById("orders-count").textContent = filtered.length + " orders";
        if (pageInfo) pageInfo.textContent = filtered.length
            ? "Showing " + (start + 1) + "-" + Math.min(start + perPage, filtered.length) + " of " + filtered.length
            : "No orders";

        if (pageRows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">No orders found.</td></tr>';
            renderPagination(0);
            return;
        }

        pageRows.forEach(order => {
            const row    = document.createElement("tr");
            const amount = Number(order.display_total ?? order.total_amount ?? order.subtotal ?? 0).toFixed(2);
            const orderId = order.order_id || order.id;
            const payLabel = paymentLabels[order.payment_method] || order.payment_method || "—";
            const delivBadge = deliveryBadge(order.delivery_status);
            const receivedAt = order.fulfilled_date || order.delivered_at || null;

            const activeStatuses = ["accepted", "shipped", "out_for_delivery"];
            let actionBtn = "";
            const isActive = activeStatuses.includes(order.status) || activeStatuses.includes(order.delivery_status);
            const isAlreadyDone = order.status === "fulfilled";
            const isPending = order.status === "pending" || order.status === "ordered";
            if (order.delivery_status === "delivered" && order.status !== "fulfilled") {
                actionBtn = '<button type="button" class="btn-mark-delivered js-confirm-received" data-order-id="' + esc(orderId) + '"><i class="bi bi-check2-circle me-1"></i>Confirm Received</button>';
            } else if (isActive && !isAlreadyDone) {
                actionBtn = '<button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-truck me-1"></i>In Transit</button>';
            } else if (isPending) {
                actionBtn = '<button class="btn btn-sm btn-outline-warning" disabled><i class="bi bi-hourglass-split me-1"></i>Supplier Review</button>';
            } else if (order.status === "declined" || order.status === "cancelled") {
                actionBtn = '<button class="btn btn-sm btn-outline-danger" disabled><i class="bi bi-x-circle me-1"></i>' + (order.status === "cancelled" ? "Cancelled" : "Declined") + "</button>";
            } else if (isAlreadyDone) {
                actionBtn = '<button class="btn btn-sm btn-outline-success" disabled><i class="bi bi-check2-all me-1"></i>Completed</button>';
            }

            // Admin can still edit or cancel the order as long as the supplier hasn't
            // responded yet (order still pending).
            if (isPending) {
                actionBtn += ' <button type="button" class="btn btn-sm btn-outline-primary js-edit-order" data-order-id="' + esc(orderId) + '" title="Edit order"><i class="bi bi-pencil"></i></button>' +
                    ' <button type="button" class="btn btn-sm btn-outline-danger js-cancel-order" data-order-id="' + esc(orderId) + '" title="Cancel order"><i class="bi bi-x-lg"></i></button>';
            }
            actionBtn += ' <button type="button" class="btn btn-sm btn-outline-primary view-order-details" data-order-id="' + esc(orderId) + '" title="View details"><i class="bi bi-list-ul"></i></button>';

            row.innerHTML =
                "<td>" + esc(orderId) + "</td>" +
                '<td><span class="order-supplier-name">' + esc(order.supplier_name || "N/A") + "</span></td>" +
                "<td>" + renderOrderSummary(order) + "</td>" +
                "<td>" + esc(order.total_quantity || order.quantity || "N/A") + "</td>" +
                "<td>\u20b1" + amount + "</td>" +
                '<td><small class="fw-semibold">' + esc(payLabel) + "</small></td>" +
                "<td>" + statusBadge(order.status) + "</td>" +
                "<td>" + delivBadge + "</td>" +
                "<td>" + formatDate(order.order_date) + "</td>" +
                "<td>" + esc(order.expected_delivery || "N/A") + "</td>" +
                "<td>" + (receivedAt ? '<span class="fw-semibold">' + formatDateTime(receivedAt) + "</span>" : '<span class="text-muted">Pending</span>') + "</td>" +
                "<td class=\"actions-cell\">" + actionBtn + "</td>";
            tbody.appendChild(row);
        });
        renderPagination(totalPages);
    }

    function updatePreferredSupplierCallout() {
        if (!preferredSupplierCallout || !orderSupplier) return;
        const selected = orderSupplier.options[orderSupplier.selectedIndex];
        preferredSupplierCallout.classList.toggle("show", !!selected && selected.dataset.preferred === "1");
    }

    function openOrderDetails(orderId) {
        const order = allOrders.find(item => String(item.order_id || item.id) === String(orderId));
        if (!order) {
            showToast("Order details could not be found.", "warning");
            return;
        }
        const items = getOrderItems(order);
        if (detailsOrderId) detailsOrderId.textContent = orderId;
        if (detailsSummary) {
            detailsSummary.innerHTML =
                '<div class="order-detail-chip"><span>Supplier</span><strong>' + esc(order.supplier_name || "N/A") + '</strong></div>' +
                '<div class="order-detail-chip"><span>Payment</span><strong>' + esc(paymentLabels[order.payment_method] || order.payment_method || "N/A") + '</strong></div>' +
                '<div class="order-detail-chip"><span>Order Status</span><strong>' + esc(statusLabels[order.status] || order.status || "Pending") + '</strong></div>' +
                '<div class="order-detail-chip"><span>Expected</span><strong>' + esc(order.expected_delivery || "N/A") + '</strong></div>';
        }
        if (detailsItems) {
            detailsItems.innerHTML = items.map(item =>
                '<tr>' +
                    '<td>' + esc(item.medicine_name || "N/A") + '</td>' +
                    '<td>' + esc(itemTypeLabel(item.item_type)) + '</td>' +
                    '<td>' + esc(item.quantity || 0) + '</td>' +
                    '<td>' + money(item.unit_price) + '</td>' +
                    '<td>' + money(item.subtotal) + '</td>' +
                '</tr>'
            ).join("");
        }
        if (detailsTotal) detailsTotal.textContent = money(order.display_total ?? order.total_amount);
        orderDetailsModal?.show();
    }

    function submitReceivedOrder(orderId) {
        const formData = new FormData();
        formData.append("action", "mark_delivered");
        formData.append("order_id", orderId);
        if (confirmReceivedBtn) {
            confirmReceivedBtn.disabled = true;
            confirmReceivedBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming...';
        }
        fetchJSON("../Admin/api/orders.php", { method: "POST", body: formData })
            .then(data => {
                if (data.success) {
                    showToast(data.message || "Order #" + orderId + " marked as delivered!", "success");
                    confirmReceivedModal?.hide();
                    loadOrders();
                } else {
                    showToast(data.message || "Failed to mark delivered", "danger");
                }
            })
            .catch(err => showToast("Error: " + err.message, "danger"))
            .finally(() => {
                if (confirmReceivedBtn) {
                    confirmReceivedBtn.disabled = false;
                    confirmReceivedBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Confirm Received';
                }
                pendingReceivedOrderId = null;
            });
    }

    function openReceivedConfirm(orderId) {
        pendingReceivedOrderId = orderId;
        if (receivedOrderIdLabel) receivedOrderIdLabel.textContent = orderId;
        confirmReceivedModal?.show();
    }

    window.markDelivered = function(orderId) {
        openReceivedConfirm(orderId);
    };

    tbody.addEventListener("click", (event) => {
        const receivedBtn = event.target.closest(".js-confirm-received");
        if (receivedBtn) {
            openReceivedConfirm(receivedBtn.dataset.orderId);
            return;
        }
        const editBtn = event.target.closest(".js-edit-order");
        if (editBtn) {
            window.openEditOrder(editBtn.dataset.orderId);
            return;
        }
        const cancelBtn = event.target.closest(".js-cancel-order");
        if (cancelBtn) {
            window.openCancelOrder(cancelBtn.dataset.orderId);
            return;
        }
        const detailsBtn = event.target.closest(".view-order-details");
        if (detailsBtn) {
            openOrderDetails(detailsBtn.dataset.orderId);
        }
    });

    confirmReceivedBtn?.addEventListener("click", () => {
        if (!pendingReceivedOrderId) return;
        submitReceivedOrder(pendingReceivedOrderId);
    });

    confirmReceivedModalEl?.addEventListener("hidden.bs.modal", () => {
        if (confirmReceivedBtn && !confirmReceivedBtn.disabled) {
            pendingReceivedOrderId = null;
        }
    });

    function loadOrders() {
        const statusFilter = filterStatus.value || "";
        fetchJSON("../Admin/api/orders.php?action=list&status=" + statusFilter)
            .then(data => {
                tbody.innerHTML = "";
                if (data.success && Array.isArray(data.data)) {
                    allOrders = normalizeOrders(data.data);
                    renderOrders();
                } else {
                    showToast("Failed to load orders: " + (data.message || "Unknown error"), "danger");
                    tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">Error loading orders.</td></tr>';
                }
            })
            .catch(err => {
                showToast("Network error loading orders: " + err.message, "danger");
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">Network error.</td></tr>';
            });
    }

    fetchJSON("../Admin/api/orders.php?action=suppliers")
        .then(data => {
            if (data.success) {
                orderSupplier.innerHTML = '<option value="">Select Supplier</option>';
                if (data.data && data.data.length > 0) {
                    const preferredGroup = document.createElement("optgroup");
                    preferredGroup.label = "Preferred Suppliers";
                    const otherGroup = document.createElement("optgroup");
                    otherGroup.label = "Other Suppliers";
                    data.data.forEach(sup => {
                        const option = document.createElement("option");
                        option.value       = sup.id;
                        const isPreferred = String(sup.is_preferred) === "1";
                        option.textContent = (isPreferred ? "[PREFERRED] " : "") + (sup.company_name || sup.name);
                        option.dataset.preferred = isPreferred ? "1" : "0";
                        (isPreferred ? preferredGroup : otherGroup).appendChild(option);
                    });
                    if (preferredGroup.children.length) orderSupplier.appendChild(preferredGroup);
                    if (otherGroup.children.length) orderSupplier.appendChild(otherGroup);
                    orderSupplier.disabled = false;
                } else {
                    showToast("No suppliers available. Add some in Suppliers section first.", "warning");
                    orderSupplier.disabled = true;
                }
            } else {
                showToast("Failed to load suppliers: " + (data.message || "Unknown error"), "danger");
                orderSupplier.disabled = true;
            }
        })
        .catch(err => {
            showToast("Network error loading suppliers: " + err.message, "danger");
            orderSupplier.disabled = true;
        });

    orderSupplier.addEventListener("change", (e) => {
        const supplierId = e.target.value;
        medicineContainer.innerHTML = "";
        availableMedicines = [];
        updatePreferredSupplierCallout();
        if (supplierId) {
            loadMedicinesForSupplier(supplierId);
        } else {
            showToast("Select a supplier to load inventory items.", "info");
        }
    });

    function loadMedicinesForSupplier(supplierId) {
        medicineContainer.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split me-2"></i>Loading inventory items with available stock...</div>';
        fetchJSON("../Admin/api/orders.php?action=get_medicines&supplier_id=" + supplierId)
            .then(data => {
                availableMedicines = data.data || [];
                medicineContainer.innerHTML = "";
                if (data.success && availableMedicines.length > 0) {
                    addMedicineRow();
                    showToast("\u2705 " + availableMedicines.length + " inventory item(s) with stock loaded", "success");
                } else {
                    medicineContainer.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>No inventory items available for this supplier.</strong><br>This supplier needs to add items to their inventory first.</div>';
                    showToast(data.message || "No inventory items with stock found for this supplier", "warning");
                }
            })
            .catch(err => {
                showToast("Error loading inventory items: " + err.message, "danger");
                medicineContainer.innerHTML = '<div class="alert alert-danger">Error loading inventory items.</div>';
            });
    }

    function refreshAllSelects() {
        document.querySelectorAll(".medicine-row .medicine-select").forEach(s => refreshSelectOptions(s));
    }

    function getUsedMedicineIds(excludeSelect) {
        excludeSelect = excludeSelect || null;
        const used = [];
        document.querySelectorAll(".medicine-row .medicine-select").forEach(s => {
            if (s !== excludeSelect && s.value) used.push(s.value);
        });
        return used;
    }

    function refreshSelectOptions(targetSelect) {
        const currentVal = targetSelect.value;
        const used = getUsedMedicineIds(targetSelect);
        Array.from(targetSelect.options).forEach(opt => {
            if (opt.value === "") return;
            opt.disabled   = used.includes(opt.value);
            opt.style.color = used.includes(opt.value) ? "#aaa" : "";
        });
        targetSelect.value = currentVal;
    }

    function addMedicineRow(itemTypeFilter) {
        const pool = itemTypeFilter
            ? availableMedicines.filter(m => (m.item_type || "medicine") === itemTypeFilter)
            : availableMedicines;

        if (availableMedicines.length === 0) {
            showToast("No inventory items with stock available to add.", "warning");
            return;
        }
        if (itemTypeFilter && pool.length === 0) {
            showToast("This supplier has no non-medicine products with stock available.", "warning");
            return;
        }

        const row = document.createElement("div");
        row.className = "medicine-row row g-3 mb-2";
        if (itemTypeFilter) row.dataset.itemTypeFilter = itemTypeFilter;
        row.innerHTML = '<div class="col-12 d-flex justify-content-end"><button type="button" class="remove-row btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i> Remove</button></div>' +
            '<div class="col-md-4"><label class="form-label">' + (itemTypeFilter === "non-medicine" ? "Product" : "Medicine") + ' <span class="text-danger">*</span></label><select class="form-select medicine-select" required><option value="">Select ' + (itemTypeFilter === "non-medicine" ? "Product" : "Medicine") + ' (with stock)</option></select><div class="invalid-feedback">Please make a selection.</div><small class="text-success stock-info"></small></div>' +
            '<div class="col-md-3"><label class="form-label">Quantity <span class="text-danger">*</span></label><input type="number" class="form-control quantity" min="1" required><div class="invalid-feedback">Enter valid quantity (within stock).</div></div>' +
            '<div class="col-md-3"><label class="form-label">Supplier Selling Price <span class="text-danger">*</span></label><div class="input-group"><span class="input-group-text">\u20b1</span><input type="number" step="0.01" class="form-control unit-price" min="0.01" required></div><div class="invalid-feedback">Enter valid price.</div><small class="text-muted">Automatic markup price</small></div>' +
            '<div class="col-md-2"><label class="form-label">Row Total</label><div class="input-group"><span class="input-group-text">\u20b1</span><input type="number" step="0.01" class="form-control row-total" readonly value="0.00"></div></div>';
        medicineContainer.appendChild(row);

        const select     = row.querySelector(".medicine-select");
        const stockInfo  = row.querySelector(".stock-info");
        const qtyInput   = row.querySelector(".quantity");
        const priceInput = row.querySelector(".unit-price");

        rebuildSelectOptions(select, pool, itemTypeFilter);

        select.addEventListener("change", () => {
            refreshAllSelects();
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                const stock  = parseInt(selectedOption.dataset.stock)  || 0;
                const minQty = parseInt(selectedOption.dataset.minQty) || 1;
                const price  = parseFloat(selectedOption.dataset.price) || 0;
                stockInfo.innerHTML   = "\u2705 Available: " + stock + " units (Min order: " + minQty + ")";
                qtyInput.disabled     = false;
                qtyInput.value        = minQty;
                qtyInput.min          = minQty;
                qtyInput.max          = stock;
                priceInput.value      = price.toFixed(2);
                calculateRowTotal(row);
                updateGrandTotal();
            } else {
                stockInfo.innerHTML  = "";
                qtyInput.disabled    = false;
                qtyInput.value       = "";
                priceInput.value     = "";
                row.querySelector(".row-total").value = "0.00";
            }
        });

        qtyInput.addEventListener("input", () => {
            if (parseInt(qtyInput.value) > parseInt(qtyInput.max)) {
                qtyInput.value = qtyInput.max;
                showToast("Quantity adjusted to available stock", "warning");
            }
            calculateRowTotal(row);
        });

        priceInput.addEventListener("input", () => calculateRowTotal(row));

        row.querySelector(".remove-row").addEventListener("click", () => {
            pendingRemoveMedicineRow = row;
            removeMedicineRowModal?.show();
        });
    }

    function calculateRowTotal(row) {
        const qty   = parseInt(row.querySelector(".quantity").value)    || 0;
        const price = parseFloat(row.querySelector(".unit-price").value) || 0;
        row.querySelector(".row-total").value = (qty * price).toFixed(2);
        updateGrandTotal();
    }

    function updateGrandTotal() {
        let grand = 0;
        document.querySelectorAll(".medicine-row .row-total").forEach(input => {
            grand += parseFloat(input.value) || 0;
        });
        totalCost.value = grand.toFixed(2);
    }

    addMedicineRowBtn.addEventListener("click", () => addMedicineRow());

    const addProductRowBtn = document.getElementById("add-product-row-btn");
    addProductRowBtn?.addEventListener("click", () => addMedicineRow("non-medicine"));

    // ── Payment method: show a reference field for anything other than cash ──
    function wirePaymentMethod(selectEl, wrapperEl, labelEl, inputEl) {
        if (!selectEl || !wrapperEl) return;
        const update = () => {
            const method = selectEl.value;
            if (method === "cash") {
                wrapperEl.classList.add("d-none");
                if (inputEl) { inputEl.required = false; inputEl.value = ""; }
            } else {
                wrapperEl.classList.remove("d-none");
                if (labelEl) {
                    const label = paymentReferenceLabels[method] || "Reference Number";
                    labelEl.innerHTML = label + ' <span class="text-danger">*</span>';
                }
                if (inputEl) inputEl.required = true;
            }
        };
        selectEl.addEventListener("change", update);
        update();
    }
    wirePaymentMethod(
        document.getElementById("payment-method"),
        document.getElementById("payment-reference-wrapper"),
        document.getElementById("payment-reference-label"),
        document.getElementById("payment-reference")
    );
    wirePaymentMethod(
        document.getElementById("edit-payment-method"),
        document.getElementById("edit-payment-reference-wrapper"),
        document.getElementById("edit-payment-reference-label"),
        document.getElementById("edit-payment-reference")
    );

    // ── Medicine search: rebuilds each row's option list to only the matches ──
    // (rebuilding the DOM rather than toggling `hidden` is what makes the filter
    // visibly and reliably take effect across browsers) — and any row added
    // while a search is active also gets built pre-filtered.
    function currentMedicineSearchTerm() {
        return "";
    }

    function rebuildSelectOptions(select, pool, itemTypeFilter) {
        const currentVal = select.value;
        const term = currentMedicineSearchTerm();
        const matches = term
            ? pool.filter(m => m.name.toLowerCase().includes(term) || (m.category || "").toLowerCase().includes(term))
            : pool;

        select.innerHTML = "";
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = matches.length
            ? "Select " + (itemTypeFilter === "non-medicine" ? "Product" : "Medicine") + " (with stock)"
            : "No matches found";
        select.appendChild(placeholder);

        matches.forEach(med => {
            const option = document.createElement("option");
            option.value          = med.id;
            option.textContent    = med.name + " (" + (med.category || "N/A") + ")";
            option.dataset.stock  = med.supplier_stock || 0;
            option.dataset.price  = med.supplier_price || 0;
            option.dataset.minQty = med.min_order_quantity || 1;
            select.appendChild(option);
        });

        // Keep the previous selection if it's still among the matches; otherwise
        // clear the row so a filtered-out medicine can't stay silently selected.
        if (currentVal && matches.some(m => String(m.id) === String(currentVal))) {
            select.value = currentVal;
        } else if (currentVal) {
            select.value = "";
            select.dispatchEvent(new Event("change"));
        }
        refreshSelectOptions(select);
    }

    addOrderForm.addEventListener("submit", (e) => {
        e.preventDefault();
        const rows     = document.querySelectorAll(".medicine-row");
        const medicines = [];
        let valid = true;

        rows.forEach(row => {
            const select = row.querySelector(".medicine-select");
            const qty    = row.querySelector(".quantity");
            const price  = row.querySelector(".unit-price");
            const total  = row.querySelector(".row-total");

            if (select.value && qty.value && price.value && parseFloat(total.value) > 0) {
                medicines.push({
                    medicine_id: select.value,
                    quantity:    parseInt(qty.value),
                    unit_price:  parseFloat(price.value),
                    subtotal:    parseFloat(total.value)
                });
                row.classList.remove("was-validated");
            } else {
                valid = false;
                row.classList.add("was-validated");
            }
        });

        if (!valid || medicines.length === 0) {
            showToast("Please fill all fields for at least one item.", "warning");
            return;
        }

        const paymentMethodEl = document.getElementById("payment-method");
        const paymentReferenceEl = document.getElementById("payment-reference");

        if (paymentMethodEl && paymentMethodEl.value !== "cash" && !(paymentReferenceEl?.value || "").trim()) {
            showToast("Please provide the payment reference/account number.", "warning");
            paymentReferenceEl?.focus();
            return;
        }

        const formData = new FormData();
        formData.append("action",            "create");
        formData.append("supplier_id",       orderSupplier.value);
        formData.append("expected_delivery", document.getElementById("expected-delivery").value);
        formData.append("notes",             document.getElementById("notes").value);
        formData.append("payment_method",    paymentMethodEl ? paymentMethodEl.value : "cash");
        formData.append("payment_reference", paymentReferenceEl ? paymentReferenceEl.value : "");
        formData.append("medicines",         JSON.stringify(medicines));

        const submitBtn = addOrderForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        fetchJSON("../Admin/api/orders.php", { method: "POST", body: formData })
            .then(data => {
                if (data.success) {
                    showToast(data.message, "success");
                    addOrderModal.hide();
                    loadOrders();
                    addOrderForm.reset();
                    medicineContainer.innerHTML = "";
                    totalCost.value = "0.00";
                } else {
                    // This now shows the *real* reason (e.g. "Requested quantity
                    // exceeds supplier stock") instead of a generic network error.
                    showToast(data.message || "Failed to place order", "danger");
                }
            })
            .catch(err => {
                showToast("Network error placing order: " + err.message, "danger");
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
            });
    });

    // ── Edit Order ──────────────────────────────────────────────────
    const editOrderModalEl = document.getElementById("editOrderModal");
    const editOrderModal = editOrderModalEl ? bootstrap.Modal.getOrCreateInstance(editOrderModalEl) : null;
    const editOrderForm = document.getElementById("edit-order-form");
    const editMedicineContainer = document.getElementById("edit-medicine-container");
    const editTotalCost = document.getElementById("edit-total-cost");
    let editSupplierMedicines = [];

    function calculateEditRowTotal(row) {
        const qty = parseInt(row.querySelector(".quantity").value) || 0;
        const price = parseFloat(row.dataset.unitPrice) || 0;
        row.querySelector(".row-total").value = (qty * price).toFixed(2);
        updateEditGrandTotal();
    }

    function updateEditGrandTotal() {
        let grand = 0;
        editMedicineContainer.querySelectorAll(".row-total").forEach(input => { grand += parseFloat(input.value) || 0; });
        editTotalCost.value = grand.toFixed(2);
    }

    function addEditRow(item) {
        const stockInfo = editSupplierMedicines.find(m => String(m.id) === String(item.medicine_id));
        const maxStock = stockInfo ? parseInt(stockInfo.supplier_stock) : parseInt(item.quantity);
        const unitPrice = stockInfo ? parseFloat(stockInfo.supplier_price) : parseFloat(item.unit_price);

        const row = document.createElement("div");
        row.className = "medicine-row row g-3 mb-2 align-items-end";
        row.dataset.medicineId = item.medicine_id;
        row.dataset.unitPrice = unitPrice;
        row.innerHTML =
            '<div class="col-md-5"><label class="form-label mb-0">' + esc(item.medicine_name) + '</label>' +
            '<div class="small text-muted">₱' + unitPrice.toFixed(2) + ' / unit &middot; ' + maxStock + ' in stock</div></div>' +
            '<div class="col-md-3"><label class="form-label">Quantity</label><input type="number" class="form-control quantity" min="1" max="' + maxStock + '" value="' + item.quantity + '"></div>' +
            '<div class="col-md-3"><label class="form-label">Row Total</label><div class="input-group"><span class="input-group-text">₱</span><input type="number" class="form-control row-total" readonly></div></div>' +
            '<div class="col-md-1"><button type="button" class="btn btn-sm btn-danger remove-edit-row"><i class="bi bi-trash"></i></button></div>';
        editMedicineContainer.appendChild(row);

        const qtyInput = row.querySelector(".quantity");
        qtyInput.addEventListener("input", () => {
            if (parseInt(qtyInput.value) > maxStock) { qtyInput.value = maxStock; showToast("Quantity adjusted to available stock", "warning"); }
            calculateEditRowTotal(row);
        });
        row.querySelector(".remove-edit-row").addEventListener("click", () => {
            if (editMedicineContainer.querySelectorAll(".medicine-row").length <= 1) {
                showToast("An order needs at least one item — cancel the order instead if you want to remove it entirely.", "warning");
                return;
            }
            row.remove();
            updateEditGrandTotal();
        });
        calculateEditRowTotal(row);
    }

    window.openEditOrder = function(orderId) {
        const formData = new FormData();
        formData.append("action", "get_order");
        formData.append("order_id", orderId);
        fetchJSON("../Admin/api/orders.php", { method: "POST", body: formData })
            .then(data => {
                if (!data.success) { showToast(data.message || "Failed to load order", "danger"); return; }
                const ord = data.data;
                document.getElementById("edit-order-id").textContent = orderId;
                document.getElementById("edit-order-id-input").value = orderId;
                document.getElementById("edit-expected-delivery").value = ord.expected_delivery || "";
                document.getElementById("edit-notes").value = ord.notes || "";
                const editPaymentMethod = document.getElementById("edit-payment-method");
                editPaymentMethod.value = ["cash", "gcash", "bank_transfer"].includes(ord.payment_method) ? ord.payment_method : "cash";
                document.getElementById("edit-payment-reference").value = ord.payment_reference || "";
                editPaymentMethod.dispatchEvent(new Event("change"));

                // Load this supplier's current stock/pricing so quantity limits & totals stay accurate.
                fetchJSON("../Admin/api/orders.php?action=get_medicines&supplier_id=" + ord.supplier_id)
                    .then(medData => {
                        editSupplierMedicines = medData.data || [];
                        editMedicineContainer.innerHTML = "";
                        (ord.items || []).forEach(item => addEditRow(item));
                        updateEditGrandTotal();
                        editOrderModal?.show();
                    })
                    .catch(err => showToast("Error loading supplier stock: " + err.message, "danger"));
            })
            .catch(err => showToast("Error loading order: " + err.message, "danger"));
    };

    editOrderForm?.addEventListener("submit", (e) => {
        e.preventDefault();
        const orderId = document.getElementById("edit-order-id-input").value;
        const editPaymentMethod = document.getElementById("edit-payment-method");
        const editPaymentReference = document.getElementById("edit-payment-reference");

        if (editPaymentMethod.value !== "cash" && !(editPaymentReference.value || "").trim()) {
            showToast("Please provide the payment reference/account number.", "warning");
            editPaymentReference.focus();
            return;
        }

        const medicines = [];
        editMedicineContainer.querySelectorAll(".medicine-row").forEach(row => {
            medicines.push({
                medicine_id: row.dataset.medicineId,
                quantity: parseInt(row.querySelector(".quantity").value) || 0,
                unit_price: parseFloat(row.dataset.unitPrice) || 0,
                subtotal: parseFloat(row.querySelector(".row-total").value) || 0
            });
        });
        if (medicines.length === 0) {
            showToast("An order needs at least one item.", "warning");
            return;
        }

        const formData = new FormData();
        formData.append("action", "edit_order");
        formData.append("order_id", orderId);
        formData.append("expected_delivery", document.getElementById("edit-expected-delivery").value);
        formData.append("notes", document.getElementById("edit-notes").value);
        formData.append("payment_method", editPaymentMethod.value);
        formData.append("payment_reference", editPaymentReference.value);
        formData.append("medicines", JSON.stringify(medicines));

        fetchJSON("../Admin/api/orders.php", { method: "POST", body: formData })
            .then(data => {
                if (data.success) {
                    showToast(data.message || "Order updated", "success");
                    editOrderModal?.hide();
                    loadOrders();
                } else {
                    showToast(data.message || "Failed to update order", "danger");
                }
            })
            .catch(err => showToast("Network error: " + err.message, "danger"));
    });

    // ── Cancel Order ────────────────────────────────────────────────
    const cancelOrderModalEl = document.getElementById("cancelOrderModal");
    const cancelOrderModal = cancelOrderModalEl ? bootstrap.Modal.getOrCreateInstance(cancelOrderModalEl) : null;
    let pendingCancelOrderId = null;

    window.openCancelOrder = function(orderId) {
        pendingCancelOrderId = orderId;
        document.getElementById("cancel-order-id").textContent = orderId;
        cancelOrderModal?.show();
    };

    document.getElementById("confirm-cancel-order-btn")?.addEventListener("click", () => {
        if (!pendingCancelOrderId) return;
        const formData = new FormData();
        formData.append("action", "cancel_order");
        formData.append("order_id", pendingCancelOrderId);
        fetchJSON("../Admin/api/orders.php", { method: "POST", body: formData })
            .then(data => {
                if (data.success) {
                    showToast(data.message || "Order cancelled", "success");
                    loadOrders();
                } else {
                    showToast(data.message || "Failed to cancel order", "danger");
                }
                cancelOrderModal?.hide();
                pendingCancelOrderId = null;
            })
            .catch(err => {
                showToast("Network error: " + err.message, "danger");
                cancelOrderModal?.hide();
            });
    });

    loadOrders();
    filterStatus.addEventListener("change", () => { currentPage = 1; renderOrders(); });
    if (filterExpected) filterExpected.addEventListener("change", () => { currentPage = 1; renderOrders(); });
    let filterTimer;
    if (filterSearch) filterSearch.addEventListener("input", () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => { currentPage = 1; renderOrders(); }, 250);
    });
});