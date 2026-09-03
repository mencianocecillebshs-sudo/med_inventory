<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "event: error\ndata: Unauthorized\n\n";
    exit();
}
// Release the session lock now. We don't need to read or write $_SESSION again,
// and PHP holds an exclusive lock on the session file for as long as this script
// runs. Since this script loops indefinitely, keeping the lock would block every
// other request on this session (page loads, other AJAX calls, etc.) until the
// browser closes this connection.
session_write_close();

require_once '../config/db.php';

$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;

// Check for manual trigger (e.g., when a notification is marked read/unread)
if (isset($_GET['trigger']) && $_GET['trigger'] === 'notification') {
    // Send an immediate update event
    echo "event: update\ndata: {}\n\n";
    ob_flush();
    flush();
    exit;
}

// Purchases and Sales pages both listen for this same "update" event and just refetch
// their own list when it fires — they don't read the payload — so all we need is to
// notice that *something* changed in either table, not what. Previously this endpoint
// only ever watched `notifications`, so a purchase or sale made elsewhere never pushed
// a live refresh to the other page.
function watchedChangeSignature(mysqli $conn): string {
    $row = $conn->query(
        "SELECT
            (SELECT COALESCE(MAX(id), 0) FROM purchases) AS p_id,
            (SELECT COALESCE(MAX(id), 0) FROM sales_invoices) AS i_id"
    )->fetch_assoc();
    return $row['p_id'] . '-' . $row['i_id'];
}

// Seed with the current signature so connecting doesn't immediately fire a spurious
// refresh — only genuine changes after this point should trigger one.
$lastChangeSignature = watchedChangeSignature($conn);

while (true) {
    // ---- Notifications (existing behavior — preserved for anything reading the
    // {type, message} payload, e.g. a notification bell) ----
    $result = $conn->query("SELECT MAX(id) as max_id FROM notifications");
    $row = $result->fetch_assoc();
    $currentId = (int)($row['max_id'] ?? 0);

    if ($currentId > $lastEventId) {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE id > ? AND `read` = 0"); // Only unread notifications
        $stmt->bind_param("i", $lastEventId);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($notifications as $notification) {
            echo "id: {$notification['id']}\n";
            echo "event: update\ndata: " . json_encode(['type' => $notification['type'], 'message' => $notification['message']]) . "\n\n";
            $lastEventId = (int)$notification['id'];
        }
    }

    // ---- Purchases & Sales (new) ----
    $changeSignature = watchedChangeSignature($conn);
    if ($changeSignature !== $lastChangeSignature) {
        echo "event: update\ndata: {}\n\n";
        $lastChangeSignature = $changeSignature;
    }

    ob_flush();
    flush();
    if (connection_aborted()) break;
    sleep(2); // Poll every 2 seconds
}
?>