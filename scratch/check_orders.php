<?php
require_once __DIR__ . '/../Admin/config/db.php';

$res = $conn->query("SELECT o.id, s.company, o.status, o.order_date, o.expected_delivery, o.actual_delivery FROM orders o JOIN suppliers s ON o.supplier_id = s.id LIMIT 10");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
