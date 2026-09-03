<?php
$c = new mysqli('localhost','root','','med_inventory');
if ($c->connect_error) { echo "CONNECT_ERROR: " . $c->connect_error; exit(1); }
$c->set_charset('utf8mb4');
$ids = [28,31,33,34,35,36,37,38];
foreach ($ids as $id) {
  $row = $c->query("SELECT p.id,p.purchase_number,p.medicine_id,p.quantity,p.status,p.total_cost,p.purchase_date,m.name,m.quantity AS current_stock FROM purchases p JOIN medicines m ON p.medicine_id = m.id WHERE p.id = $id")->fetch_assoc();
  echo "---- PURCHASE $id ----\n";
  if ($row) {
    echo json_encode($row) . "\n";
    $sales = $c->query("SELECT id, quantity, cost_price, selling_price, profit, sale_date FROM sales WHERE purchase_id = {$row['id']}");
    while ($s = $sales->fetch_assoc()) echo "SALE: " . json_encode($s) . "\n";
    $trans = $c->query("SELECT id, action, quantity, total_cost, reason, timestamp FROM transactions WHERE purchase_id = {$row['id']}");
    while ($t = $trans->fetch_assoc()) echo "TRANS: " . json_encode($t) . "\n";
  } else {
    echo "purchase missing\n";
  }
}
// list related transaction totals per medicine
$meds = [301,304,318,349,353];
foreach ($meds as $mid) {
  $m = $c->query("SELECT id, name, quantity FROM medicines WHERE id = $mid")->fetch_assoc();
  echo "---- MEDICINE $mid ({$m['name']}) ----\n";
  echo "CURRENT QTY: {$m['quantity']}\n";
  $sales = $c->query("SELECT COALESCE(SUM(quantity),0) AS total_sold FROM sales WHERE medicine_id = $mid")->fetch_assoc();
  echo "SOLD QTY: {$sales['total_sold']}\n";
  $trans = $c->query("SELECT SUM(CASE WHEN action='remove' THEN quantity WHEN action='add' THEN -quantity ELSE 0 END) AS net FROM transactions WHERE medicine_id = $mid")->fetch_assoc();
  echo "NET TRANS REMOVE: {$trans['net']}\n";
}
$c->close();
?>
