<?php
require_once __DIR__ . '/../Admin/config/db.php';

$sql = "
    SELECT 
        t.id,
        t.action,
        t.quantity,
        t.timestamp,
        m.name AS medicine_name,
        u.username AS user_name,
        t.reason,
        t.notes
    FROM transactions t
    LEFT JOIN medicines m ON t.medicine_id = m.id
    LEFT JOIN users u ON COALESCE(t.user_id, t.pharmacist_id) = u.id
    ORDER BY t.timestamp DESC, t.id DESC
    LIMIT 5
";

$res = $conn->query($sql);
while($row = $res->fetch_assoc()) {
    print_r($row);
}
