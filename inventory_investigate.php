<?php
$c = new mysqli('localhost','root','','med_inventory');
if ($c->connect_error) { echo "CONNECT_ERROR: " . $c->connect_error; exit(1); }
$c->set_charset('utf8mb4');
$queries = [
  'filled_purchase_unmatched_sales' => 'SELECT p.id, p.purchase_number, p.medicine_id, p.quantity, p.status, p.total_cost, p.purchase_date, m.name FROM purchases p JOIN medicines m ON p.medicine_id = m.id WHERE p.status = "filled" AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.purchase_id = p.id)',
  'filled_purchase_with_sales' => 'SELECT p.id, p.purchase_number, p.medicine_id, p.quantity, p.status, p.total_cost, p.purchase_date, COUNT(s.id) as sales_count FROM purchases p JOIN sales s ON s.purchase_id = p.id WHERE p.status = "filled" GROUP BY p.id ORDER BY p.purchase_date DESC LIMIT 20',
  'sales_without_purchase' => 'SELECT s.id, s.invoice_id, s.medicine_id, s.quantity, s.selling_price, s.cost_price, s.profit, s.sale_date, m.name FROM sales s JOIN medicines m ON s.medicine_id = m.id WHERE s.purchase_id IS NULL ORDER BY s.sale_date DESC LIMIT 20',
  'voided_invoices' => 'SELECT id, invoice_number, order_id, purchase_id, total_amount, amount_paid, change_given, status, created_at FROM sales_invoices WHERE status = "voided" ORDER BY created_at DESC LIMIT 20',
  'pending_purchases' => 'SELECT p.id, p.purchase_number, p.medicine_id, p.quantity, p.status, p.total_cost, p.purchase_date, m.name FROM purchases p JOIN medicines m ON p.medicine_id = m.id WHERE p.status = "pending" ORDER BY p.purchase_date DESC LIMIT 20',
];
foreach ($queries as $label => $query) {
  echo "---- $label ----\n";
  $r = $c->query($query);
  if (!$r) { echo "ERROR $label: " . $c->error . "\n"; continue; }
  while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . "\n";
  }
}
$c->close();
?>
