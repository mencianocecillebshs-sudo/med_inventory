<?php
// Lightweight debug endpoint: reports session and DB connection status
header('Content-Type: application/json; charset=utf-8');
session_start();

$out = ['session' => $_SESSION ?? null, 'db' => null, 'errors' => []];

$dbPaths = [
    __DIR__ . '/../../Config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../config/db.php'
];

foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        // include inside isolated scope to avoid variable clobbering
        try {
            require_once $path;
        } catch (Throwable $t) {
            $out['errors'][] = "Include failed for $path: " . $t->getMessage();
            continue;
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $out['db'] = [
                'path' => $path,
                'connected' => !$conn->connect_error,
                'connect_error' => $conn->connect_error ?? null,
                'server_info' => $conn->server_info ?? null,
            ];
            break;
        }
    } else {
        $out['errors'][] = "Missing path: $path";
    }
}

if (!$out['db']) {
    $out['errors'][] = 'No DB connection available after checking paths.';
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
