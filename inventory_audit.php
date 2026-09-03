<?php
$c = new mysqli('localhost','root','','med_inventory');
if ($c->connect_error) { echo "CONNECT_ERROR: " . $c->connect_error; exit(1); }
$c->set_charset('utf8mb4');
$checks = [
  'COUNT(*) AS total_meds' => 'SELECT COUNT(*) AS total_meds FROM medicines',
  'SUM(quantity) AS total_units' => 'SELECT COALESCE(SUM(quantity),0) AS total_units FROM medicines',
  'COUNT(*) AS pending_purchases' => 'SELECT COUNT(*) AS pending_purchases FROM purchases WHERE status = "pending"',
  'COUNT(*) AS filled_purchases' => 'SELECT COUNT(*) AS filled_purchases FROM purchases WHERE status = "filled"',
  'COUNT(*) AS cancelled_purchases' => 'SELECT COUNT(*) AS cancelled_purchases FROM purchases WHERE status = "cancelled"',
  'COUNT(*) AS sales_with_purchase' => 'SELECT COUNT(DISTINCT purchase_id) AS sales_with_purchase FROM sales WHERE purchase_id IS NOT NULL',
  'COUNT(*) AS sales_without_purchase' => 'SELECT COUNT(*) AS sales_without_purchase FROM sales WHERE purchase_id IS NULL',
  'COUNT(*) AS filled_purchase_unmatched_sales' => 'SELECT COUNT(*) AS cnt FROM purchases p WHERE p.status = "filled" AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.purchase_id = p.id)',
  'COUNT(*) AS purchase_sales_mismatch' => 'SELECT COUNT(*) AS cnt FROM sales s WHERE s.purchase_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM purchases p WHERE p.id = s.purchase_id)',
  'COUNT(*) AS recent_transactions' => 'SELECT COUNT(*) AS recent_transactions FROM transactions WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
];
foreach ($checks as $label => $query) {
  $r = $c->query($query);
  if (!$r) { echo "ERROR $label: " . $c->error . "\n"; continue; }
  $row = $r->fetch_assoc();
  echo "$label => " . array_values($row)[0] . "\n";
}
$c->close();
?>
