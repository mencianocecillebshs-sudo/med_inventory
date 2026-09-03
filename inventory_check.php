<?php
$c = new mysqli('localhost','root','','med_inventory');
if ($c->connect_error) { echo "CONNECT_ERROR: " . $c->connect_error; exit(1); }
$c->set_charset('utf8mb4');
$queries = [
  'SELECT COUNT(*) AS cnt FROM medicines',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE quantity < 0',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE quantity IS NULL',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE reorder_point IS NULL',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE reorder_point < 0',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE item_type NOT IN ("medicine","non-medicine")',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE quantity < reorder_point AND reorder_point IS NOT NULL',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE quantity = 0',
  'SELECT COUNT(*) AS cnt FROM medicines WHERE quantity > 10000'
];
foreach($queries as $q) {
  $r = $c->query($q);
  if (!$r) { echo "ERROR: $q\n" . $c->error . "\n"; exit(1); }
  $row = $r->fetch_assoc();
  echo "$q => {$row['cnt']}\n";
}
$c->close();
?>
