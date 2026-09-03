<?php
require_once 'Admin/config/db.php';
if (!$conn) {
    echo "Connection failed.\n";
    exit(1);
}
$res = $conn->query("DESCRIBE medicines");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "Field: {$row['Field']} | Type: {$row['Type']} | Null: {$row['Null']} | Default: {$row['Default']}\n";
    }
} else {
    echo "Error querying table medicines: " . $conn->error . "\n";
}
$conn->close();
?>
