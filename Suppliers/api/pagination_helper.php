<?php
/**
 * Shared pagination helpers for Supplier APIs.
 */
function supLoadSettingsHelper() {
    $path = __DIR__ . '/../config/sup_settings_helper.php';
    if (file_exists($path)) {
        require_once $path;
    }
}

function supGetPaginationParams($conn) {
    supLoadSettingsHelper();
    $defaultLimit = function_exists('getRecordsPerPage') ? getRecordsPerPage($conn) : 25;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limitParam = $_GET['limit'] ?? $_GET['per_page'] ?? null;
    $limit = isset($limitParam) ? max(1, min(100, (int)$limitParam)) : max(1, min(100, $defaultLimit));
    $offset = ($page - 1) * $limit;
    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

function supBuildPaginationMeta($page, $limit, $totalItems) {
    $totalItems = (int)$totalItems;
    $totalPages = $totalItems > 0 ? (int)ceil($totalItems / $limit) : 1;
    return [
        'current_page' => $page,
        'total_pages' => max(1, $totalPages),
        'total_items' => $totalItems,
        'items_per_page' => $limit
    ];
}
