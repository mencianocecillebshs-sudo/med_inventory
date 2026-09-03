<?php
require_once 'Admin/config/db.php';
if (!$conn) {
    echo "Connection failed.\n";
    exit(1);
}
$res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
if ($res) {
    while ($row = $res->fetch_row()) {
        $viewName = $row[0];
        echo "View: $viewName\n";
        $defRes = $conn->query("SHOW CREATE VIEW `$viewName`");
        if ($defRes) {
            $defRow = $defRes->fetch_assoc();
            echo "Create View definition: \n" . $defRow['Create View'] . "\n\n";
        }
    }
} else {
    echo "Error showing tables: " . $conn->error . "\n";
}
$conn->close();
?>
