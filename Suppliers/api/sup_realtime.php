<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

session_start();
if (!isset($_SESSION['user_id'])) {
    echo "event: error\ndata: Unauthorized\n\n";
    exit();
}

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

while (true) {
    $stmt = $conn->query("SELECT MAX(id) as max_id FROM notifications");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentId = $row['max_id'] ?? 0;

    if ($currentId > $lastEventId) {
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE id > ? AND `read` = 0"); // Only unread notifications
        $stmt->execute([$lastEventId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notifications as $notification) {
            echo "id: {$notification['id']}\n";
            echo "event: update\ndata: " . json_encode(['type' => $notification['type'], 'message' => $notification['message']]) . "\n\n";
            $lastEventId = $notification['id'];
        }
    }

    ob_flush();
    flush();
    sleep(2); // Poll every 2 seconds
}
?>