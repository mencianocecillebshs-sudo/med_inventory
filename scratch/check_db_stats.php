<?php
require_once __DIR__ . '/../Admin/config/db.php';

// 1. Sales today
$todaySales = $conn->query("SELECT SUM(total_amount) FROM sales_invoices WHERE order_id IS NULL AND DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
// 2. Sales yesterday
$yesterdaySales = $conn->query("SELECT SUM(total_amount) FROM sales_invoices WHERE order_id IS NULL AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetch_row()[0] ?? 0;

// 3. Inventory Value
$invValueSelling = $conn->query("SELECT SUM(quantity * selling_price) FROM medicines")->fetch_row()[0] ?? 0;
$invValueCost = $conn->query("SELECT SUM(quantity * (selling_price / (1 + COALESCE(markup_percentage, 0) / 100))) FROM medicines")->fetch_row()[0] ?? 0;

// 4. Low stock count (using threshold 10 as default)
$lowStock = $conn->query("SELECT COUNT(*) FROM medicines WHERE quantity <= COALESCE(NULLIF(reorder_point, 0), 10)")->fetch_row()[0] ?? 0;

// 5. Expiring in 30 days
$expiring30 = $conn->query("SELECT COUNT(*) FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()")->fetch_row()[0] ?? 0;

// 6. Pending orders
$pendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending' OR status = 'ordered'")->fetch_row()[0] ?? 0;

echo "Today Sales: " . $todaySales . "\n";
echo "Yesterday Sales: " . $yesterdaySales . "\n";
echo "Inventory Value (Selling): " . $invValueSelling . "\n";
echo "Inventory Value (Cost): " . $invValueCost . "\n";
echo "Low Stock Count: " . $lowStock . "\n";
echo "Expiring in 30 Days Count: " . $expiring30 . "\n";
echo "Pending Orders Count: " . $pendingOrders . "\n";
