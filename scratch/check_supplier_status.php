<?php
require_once __DIR__ . '/../Admin/config/db.php';

$sql = "
    SELECT 
        s.id,
        s.company,
        s.name,
        o.status AS order_status,
        o.expected_delivery,
        o.actual_delivery,
        DATEDIFF(CURDATE(), o.expected_delivery) as days_late
    FROM suppliers s
    LEFT JOIN (
        SELECT o1.*
        FROM orders o1
        INNER JOIN (
            SELECT supplier_id, MAX(id) as max_id
            FROM orders
            GROUP BY supplier_id
        ) o2 ON o1.id = o2.max_id
    ) o ON s.id = o.supplier_id
    ORDER BY s.bought_quantity DESC
    LIMIT 5
";

$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
    print_r($row);
}
