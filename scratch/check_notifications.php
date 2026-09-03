<?php
require_once __DIR__ . '/../Admin/config/db.php';

$res = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
