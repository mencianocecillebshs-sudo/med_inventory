<?php
declare(strict_types=1);

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/supplier_auth.php';

function sup_assistant_json(array $payload, int $status = 200): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($conn) || !$conn) {
    sup_assistant_json(['success' => false, 'message' => 'Service unavailable', 'response' => 'The assistant is temporarily unavailable. Please try again in a moment or open your dashboard directly.'], 503);
}

try {
    $supplierProfile = requireSupplierSession($conn);
} catch (Throwable $e) {
    sup_assistant_json(['success' => false, 'message' => 'Unauthorized', 'response' => 'Please log in to use the supplier assistant.'], 401);
}

$supplierId      = (int)$supplierProfile['id'];
$supplierCompany = (string)($supplierProfile['company'] ?? $supplierProfile['name'] ?? 'Your company');
$userRole        = 'supplier';

$rawInput  = file_get_contents('php://input');
$jsonInput = json_decode($rawInput ?: '', true);
$query     = trim((string)($jsonInput['query'] ?? $_POST['query'] ?? $_GET['query'] ?? ''));
$history   = is_array($jsonInput['history'] ?? null) ? $jsonInput['history'] : [];
$topic     = strtolower(trim((string)($jsonInput['topic'] ?? $_POST['topic'] ?? $_GET['topic'] ?? '')));

function default_supplier_suggestions(): array {
    return [
        ['label' => 'My overview',       'query' => 'show my supplier overview', 'icon' => 'bi-speedometer2'],
        ['label' => 'Low stock items',   'query' => 'show my low stock medicines', 'icon' => 'bi-graph-down-arrow'],
        ['label' => 'Pending orders',    'query' => 'show my pending orders', 'icon' => 'bi-box-seam'],
        ['label' => 'Sales this month',  'query' => 'summarize my sales this month', 'icon' => 'bi-cash-stack'],
        ['label' => 'Recent activity',   'query' => 'show my recent transactions', 'icon' => 'bi-receipt'],
    ];
}

function infer_types(array $params): string {
    $t = '';
    foreach ($params as $p) {
        $t .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
    }
    return $t;
}

function sup_rows(mysqli $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($params) {
        $stmt->bind_param(infer_types($params), ...$params);
    }
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $data;
}

function sup_scalar(mysqli $conn, string $sql, array $params = [], $default = 0) {
    $data = sup_rows($conn, $sql, $params);
    if (!$data) {
        return $default;
    }
    return array_values($data[0])[0] ?? $default;
}

function sup_table_exists(mysqli $conn, string $table): bool {
    return (int)sup_scalar($conn, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", [$table]) > 0;
}

function sup_money($v): string {
    return 'PHP ' . number_format((float)$v, 2);
}

function sup_short_date($v): string {
    if (!$v) {
        return 'N/A';
    }
    $t = strtotime((string)$v);
    return $t ? date('M d, Y', $t) : (string)$v;
}

function sup_detect_range(string $q): array {
    $today = date('Y-m-d');
    if (preg_match('/\byesterday\b/', $q)) {
        $d = date('Y-m-d', strtotime('-1 day'));
        return [$d . ' 00:00:00', $d . ' 23:59:59', 'yesterday'];
    }
    if (preg_match('/\btoday\b/', $q)) {
        return [$today . ' 00:00:00', $today . ' 23:59:59', 'today'];
    }
    if (preg_match('/\b(this|current)\s+week\b|\bweek\b/', $q)) {
        return [date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 23:59:59', strtotime('sunday this week')), 'this week'];
    }
    if (preg_match('/\blast\s+week\b/', $q)) {
        return [date('Y-m-d 00:00:00', strtotime('monday last week')), date('Y-m-d 23:59:59', strtotime('sunday last week')), 'last week'];
    }
    if (preg_match('/\b(this|current)\s+month\b|\bmonth\b/', $q)) {
        return [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'), 'this month'];
    }
    if (preg_match('/\blast\s+month\b/', $q)) {
        return [date('Y-m-01 00:00:00', strtotime('first day of last month')), date('Y-m-t 23:59:59', strtotime('last day of last month')), 'last month'];
    }
    return [date('Y-m-d 00:00:00', strtotime('-30 days')), date('Y-m-d 23:59:59'), 'last 30 days'];
}

function sup_response_pack(string $intent, string $response, array $extra = []): array {
    return array_merge([
        'success'     => true,
        'intent'      => $intent,
        'response'    => $response,
        'confidence'  => $extra['confidence'] ?? 'high',
        'cards'       => [],
        'table'       => null,
        'actions'     => [],
        'suggestions' => default_supplier_suggestions(),
        'ai_enhanced' => false,
    ], $extra);
}

function sup_is_out_of_scope(string $q): bool {
    return (bool)preg_match(
        '/\b(all suppliers|other supplier|competitor|pharmacy stock|admin dashboard|prescription|user activity|staff|pharmacist|global inventory|system sales|total pharmacy|every supplier|list suppliers|supplier list)\b/i',
        $q
    );
}

function sup_out_of_scope_response(string $company): array {
    return sup_response_pack('restricted', "I can only answer questions about {$company}'s own supplier workspace — your inventory, orders, sales, and transactions. Pharmacy-wide or other suppliers' data is not available here.", [
        'confidence'  => 'high',
        'suggestions' => default_supplier_suggestions(),
        'actions'     => [
            ['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam'],
            ['label' => 'My Orders',    'href' => 'sup_orders.php',   'icon' => 'bi-bag-check'],
        ],
    ]);
}

function sup_find_medicine(mysqli $conn, int $supplierId, string $query): ?array {
    if (preg_match('/\b(\d{10,13})\b/', $query, $m)) {
        $r = sup_rows($conn, "
            SELECT m.*, si.quantity AS supplier_qty, si.unit_price AS supplier_unit_price
            FROM supplier_inventory si
            INNER JOIN medicines m ON m.id = si.medicine_id
            WHERE si.supplier_id = ? AND m.barcode = ?
            LIMIT 1
        ", [$supplierId, $m[1]]);
        return $r[0] ?? null;
    }

    $stop = ['show', 'find', 'medicine', 'medicines', 'stock', 'quantity', 'price', 'my', 'our', 'the', 'a', 'an', 'what', 'how', 'many'];
    $tokens = preg_split('/[^a-z0-9]+/i', strtolower($query));
    $tokens = array_values(array_filter($tokens, fn($t) => strlen($t) > 2 && !in_array($t, $stop, true)));
    if (!$tokens) {
        return null;
    }

    $terms = array_slice($tokens, 0, 3);
    $where = [];
    $params = [$supplierId];
    foreach ($terms as $term) {
        $where[] = '(LOWER(m.name) LIKE ? OR LOWER(m.barcode) LIKE ? OR LOWER(m.type) LIKE ?)';
        $like = '%' . $term . '%';
        array_push($params, $like, $like, $like);
    }

    $r = sup_rows($conn, "
        SELECT m.*, si.quantity AS supplier_qty, si.unit_price AS supplier_unit_price
        FROM supplier_inventory si
        INNER JOIN medicines m ON m.id = si.medicine_id
        WHERE si.supplier_id = ? AND (" . implode(' OR ', $where) . ")
        ORDER BY m.name ASC
        LIMIT 1
    ", $params);
    return $r[0] ?? null;
}

function sup_build_snapshot(mysqli $conn, int $supplierId, string $company): array {
    $threshold  = function_exists('getLowStockThreshold') ? (int)getLowStockThreshold($conn) : 10;
    $expiryDays = function_exists('getExpiryAlertDays') ? (int)getExpiryAlertDays($conn) : 30;
    $expiryEnd  = date('Y-m-d', strtotime("+{$expiryDays} days"));

    $snapshot = [
        'supplier' => [
            'id'      => $supplierId,
            'company' => $company,
        ],
        'data_scope' => 'supplier_private_only',
    ];

    $snapshot['inventory'] = [
        'total_medicines' => (int)sup_scalar($conn, "SELECT COUNT(DISTINCT medicine_id) FROM supplier_inventory WHERE supplier_id = ? AND quantity > 0", [$supplierId]),
        'total_units'     => (int)sup_scalar($conn, "SELECT COALESCE(SUM(quantity),0) FROM supplier_inventory WHERE supplier_id = ?", [$supplierId]),
        'low_stock_count' => (int)sup_scalar($conn, "SELECT COUNT(*) FROM supplier_inventory WHERE supplier_id = ? AND quantity > 0 AND quantity <= ?", [$supplierId, $threshold]),
        'zero_stock_count'=> (int)sup_scalar($conn, "
            SELECT COUNT(*)
            FROM supplier_medicines sm
            LEFT JOIN supplier_inventory si ON si.supplier_id = sm.supplier_id AND si.medicine_id = sm.medicine_id
            WHERE sm.supplier_id = ? AND COALESCE(si.quantity, 0) <= 0
        ", [$supplierId]),
        'low_threshold'   => $threshold,
    ];

    $snapshot['low_stock_items'] = sup_rows($conn, "
        SELECT m.name, si.quantity, m.type, m.expiry_date, si.unit_price
        FROM supplier_inventory si
        INNER JOIN medicines m ON m.id = si.medicine_id
        WHERE si.supplier_id = ? AND si.quantity > 0 AND si.quantity <= ?
        ORDER BY si.quantity ASC, m.name ASC
        LIMIT 10
    ", [$supplierId, $threshold]);

    $snapshot['expiring_items'] = sup_rows($conn, "
        SELECT m.name, si.quantity, m.expiry_date, DATEDIFF(m.expiry_date, CURDATE()) AS days_left
        FROM supplier_inventory si
        INNER JOIN medicines m ON m.id = si.medicine_id
        WHERE si.supplier_id = ? AND m.expiry_date IS NOT NULL
          AND m.expiry_date BETWEEN CURDATE() AND ?
        ORDER BY m.expiry_date ASC
        LIMIT 10
    ", [$supplierId, $expiryEnd]);

    $snapshot['orders'] = [
        'pending'   => (int)sup_scalar($conn, "SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status IN ('pending','ordered')", [$supplierId]),
        'accepted'  => (int)sup_scalar($conn, "SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = 'accepted'", [$supplierId]),
        'delivered' => (int)sup_scalar($conn, "SELECT COUNT(*) FROM orders WHERE supplier_id = ? AND status = 'delivered'", [$supplierId]),
    ];

    if (sup_table_exists($conn, 'supplier_sales')) {
        $snapshot['sales_this_month'] = sup_rows($conn, "
            SELECT COUNT(*) AS sale_lines, COALESCE(SUM(line_total),0) AS revenue
            FROM supplier_sales
            WHERE supplier_id = ? AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
        ", [$supplierId])[0] ?? [];
    }

    $snapshot['recent_orders'] = sup_rows($conn, "
        SELECT o.id, o.status, o.order_date, o.expected_delivery, o.quantity, o.total_cost, m.name AS medicine_name
        FROM orders o
        LEFT JOIN medicines m ON m.id = o.medicine_id
        WHERE o.supplier_id = ?
        ORDER BY o.order_date DESC
        LIMIT 5
    ", [$supplierId]);

    return $snapshot;
}

function sup_ai_response(string $query, array $history, array $dbContext, string $company): ?array {
    $systemPrompt = <<<PROMPT
You are a supplier inventory assistant for "{$company}" in a Philippine medicine supply portal. Prices are in PHP.

You ONLY have access to this supplier's private workspace data in JSON context. Never reference other suppliers, pharmacy-wide inventory, prescriptions, or admin-only records.

Database context:
PROMPT;
    $systemPrompt .= "\n" . json_encode($dbContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $systemPrompt .= "\n\nRules:\n";
    $systemPrompt .= "- Use exact numbers from context only.\n";
    $systemPrompt .= "- Scope every answer to this supplier account.\n";
    $systemPrompt .= "- If asked about data you do not have, say it is outside your supplier workspace.\n";
    $systemPrompt .= "- Be concise, actionable, and professional.\n";
    $systemPrompt .= "- Keep responses under 220 words.\n";

    $messages = [];
    foreach (array_slice($history, -10) as $h) {
        if (isset($h['role'], $h['content']) && in_array($h['role'], ['user', 'assistant'], true)) {
            $messages[] = ['role' => $h['role'], 'content' => (string)$h['content']];
        }
    }
    $messages[] = ['role' => 'user', 'content' => $query];

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 1024,
        'system'     => $systemPrompt,
        'messages'   => $messages,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01',
            'x-api-key: ' . (defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : getenv('ANTHROPIC_API_KEY')),
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) {
        return null;
    }

    $data = json_decode($raw, true);
    $text = $data['content'][0]['text'] ?? null;
    return $text ? ['text' => trim($text)] : null;
}

function sup_overview(mysqli $conn, int $supplierId, string $company): array {
    $threshold = function_exists('getLowStockThreshold') ? (int)getLowStockThreshold($conn) : 10;
    $snap      = sup_build_snapshot($conn, $supplierId, $company);
    $inv       = $snap['inventory'];
    $orders    = $snap['orders'];
    $sales     = $snap['sales_this_month'] ?? ['sale_lines' => 0, 'revenue' => 0];

    $priorities = [];
    if ((int)$inv['low_stock_count'] > 0) {
        $priorities[] = (int)$inv['low_stock_count'] . ' low-stock item(s)';
    }
    if ((int)$inv['zero_stock_count'] > 0) {
        $priorities[] = (int)$inv['zero_stock_count'] . ' out-of-stock listing(s)';
    }
    if ((int)$orders['pending'] > 0) {
        $priorities[] = (int)$orders['pending'] . ' pending order(s) to fulfill';
    }

    $response = $priorities
        ? "{$company} snapshot — focus on: " . implode('; ', $priorities) . '.'
        : "{$company} looks stable. No urgent stock or order issues detected.";

    return sup_response_pack('overview', $response, [
        'cards' => [
            ['label' => 'Stocked items',  'value' => number_format((int)$inv['total_medicines']), 'meta' => number_format((int)$inv['total_units']) . ' units'],
            ['label' => 'Low stock',      'value' => (string)(int)$inv['low_stock_count'],         'meta' => "Threshold: {$threshold}"],
            ['label' => 'Pending orders', 'value' => (string)(int)$orders['pending'],              'meta' => (int)$orders['accepted'] . ' accepted'],
            ['label' => 'Sales (month)',  'value' => sup_money($sales['revenue'] ?? 0),            'meta' => number_format((int)($sales['sale_lines'] ?? 0)) . ' sale lines'],
        ],
        'actions' => [
            ['label' => 'Open Dashboard', 'href' => 'sup_dashboard.php', 'icon' => 'bi-speedometer2'],
            ['label' => 'My Inventory',   'href' => 'sup_medicine.php',  'icon' => 'bi-box-seam'],
        ],
    ]);
}

function sup_low_stock(mysqli $conn, int $supplierId): array {
    $threshold = function_exists('getLowStockThreshold') ? (int)getLowStockThreshold($conn) : 10;
    $data = sup_rows($conn, "
        SELECT m.name, si.quantity, m.type, m.expiry_date, si.unit_price
        FROM supplier_inventory si
        INNER JOIN medicines m ON m.id = si.medicine_id
        WHERE si.supplier_id = ? AND si.quantity > 0 AND si.quantity <= ?
        ORDER BY si.quantity ASC, m.name ASC
        LIMIT 25
    ", [$supplierId, $threshold]);

    if (!$data) {
        return sup_response_pack('low_stock', "No medicines are at or below your low-stock threshold of {$threshold} units.", [
            'actions' => [['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam']],
        ]);
    }

    $tableRows = array_map(fn($r) => [
        'Medicine' => $r['name'],
        'Qty'      => (int)$r['quantity'],
        'Type'     => $r['type'] ?: '—',
        'Unit price'=> sup_money($r['unit_price'] ?? 0),
        'Expiry'   => sup_short_date($r['expiry_date']),
    ], $data);

    return sup_response_pack('low_stock', 'Found ' . count($data) . ' low-stock item(s) in your supplier inventory.', [
        'table'   => ['columns' => ['Medicine', 'Qty', 'Type', 'Unit price', 'Expiry'], 'rows' => $tableRows],
        'actions' => [
            ['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam'],
            ['label' => 'My Orders',    'href' => 'sup_orders.php', 'icon' => 'bi-bag-check'],
        ],
    ]);
}

function sup_inventory(mysqli $conn, int $supplierId, string $query): array {
    $med = sup_find_medicine($conn, $supplierId, $query);
    if ($med) {
        $response = "{$med['name']}: you have " . (int)($med['supplier_qty'] ?? 0) . ' unit(s) in your supplier stock';
        if (!empty($med['supplier_unit_price'])) {
            $response .= ' at ' . sup_money($med['supplier_unit_price']) . ' per unit';
        }
        $response .= '.';

        return sup_response_pack('medicine_lookup', $response, [
            'cards' => [
                ['label' => 'Your stock',  'value' => number_format((int)($med['supplier_qty'] ?? 0)), 'meta' => $med['type'] ?: 'No type'],
                ['label' => 'Unit price',  'value' => sup_money($med['supplier_unit_price'] ?? 0),    'meta' => 'Your listing price'],
                ['label' => 'Expiry',      'value' => sup_short_date($med['expiry_date'] ?? ''),       'meta' => 'Catalog expiry'],
            ],
            'actions' => [['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam']],
        ]);
    }

    $stats = sup_rows($conn, "
        SELECT COUNT(DISTINCT medicine_id) AS items, COALESCE(SUM(quantity),0) AS units
        FROM supplier_inventory WHERE supplier_id = ? AND quantity > 0
    ", [$supplierId])[0] ?? [];

    $top = sup_rows($conn, "
        SELECT m.name, si.quantity
        FROM supplier_inventory si
        INNER JOIN medicines m ON m.id = si.medicine_id
        WHERE si.supplier_id = ? AND si.quantity > 0
        ORDER BY si.quantity DESC, m.name ASC
        LIMIT 8
    ", [$supplierId]);

    $tableRows = array_map(fn($r) => [
        'Medicine' => $r['name'],
        'Qty'      => (int)$r['quantity'],
    ], $top);

    return sup_response_pack('inventory', 'You currently stock ' . number_format((int)($stats['items'] ?? 0)) . ' medicine(s) with ' . number_format((int)($stats['units'] ?? 0)) . ' total units.', [
        'table'   => $tableRows ? ['columns' => ['Medicine', 'Qty'], 'rows' => $tableRows] : null,
        'actions' => [['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam']],
    ]);
}

function sup_expiry(mysqli $conn, int $supplierId, string $q): array {
    $days = function_exists('getExpiryAlertDays') ? (int)getExpiryAlertDays($conn) : 30;
    if (preg_match('/\b(\d+)\s*(day|days)\b/', $q, $m)) {
        $days = max(1, min(365, (int)$m[1]));
    }
    $includeExpired = (bool)preg_match('/\bexpired|dispose\b/', $q);
    $end = date('Y-m-d', strtotime("+{$days} days"));

    $sql = $includeExpired
        ? "SELECT m.name, si.quantity, m.expiry_date FROM supplier_inventory si INNER JOIN medicines m ON m.id = si.medicine_id WHERE si.supplier_id = ? AND m.expiry_date IS NOT NULL AND m.expiry_date <= ? ORDER BY m.expiry_date ASC LIMIT 25"
        : "SELECT m.name, si.quantity, m.expiry_date FROM supplier_inventory si INNER JOIN medicines m ON m.id = si.medicine_id WHERE si.supplier_id = ? AND m.expiry_date IS NOT NULL AND m.expiry_date BETWEEN CURDATE() AND ? ORDER BY m.expiry_date ASC LIMIT 25";

    $data = sup_rows($conn, $sql, [$supplierId, $end]);
    if (!$data) {
        return sup_response_pack('expiry', "No items in your catalog are expiring within the next {$days} days.", [
            'actions' => [['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam']],
        ]);
    }

    $today = strtotime(date('Y-m-d'));
    $tableRows = array_map(function ($r) use ($today) {
        $diff = (int)floor((strtotime((string)$r['expiry_date']) - $today) / 86400);
        return [
            'Medicine' => $r['name'],
            'Qty'      => (int)$r['quantity'],
            'Expiry'   => sup_short_date($r['expiry_date']),
            'Status'   => $diff < 0 ? abs($diff) . ' days expired' : $diff . ' days left',
        ];
    }, $data);

    return sup_response_pack('expiry', 'Found ' . count($data) . ' expiry item(s) in your supplier catalog.', [
        'table' => ['columns' => ['Medicine', 'Qty', 'Expiry', 'Status'], 'rows' => $tableRows],
    ]);
}

function sup_orders(mysqli $conn, int $supplierId, string $q): array {
    $status = null;
    foreach (['pending', 'ordered', 'accepted', 'delivered', 'cancelled', 'declined'] as $s) {
        if (str_contains($q, $s)) {
            $status = $s;
            break;
        }
    }

    $where  = 'WHERE o.supplier_id = ?';
    $params = [$supplierId];
    if ($status) {
        $where .= ' AND o.status = ?';
        $params[] = $status;
    }

    $data = sup_rows($conn, "
        SELECT o.id, o.status, o.order_date, o.expected_delivery, o.quantity, o.total_cost, m.name AS medicine_name
        FROM orders o
        LEFT JOIN medicines m ON m.id = o.medicine_id
        {$where}
        ORDER BY o.order_date DESC
        LIMIT 20
    ", $params);

    if (!$data) {
        return sup_response_pack('orders', $status ? "No {$status} orders found for your account." : 'No orders found for your account.', [
            'actions' => [['label' => 'My Orders', 'href' => 'sup_orders.php', 'icon' => 'bi-bag-check']],
        ]);
    }

    $tableRows = array_map(fn($o) => [
        'Order'    => '#' . $o['id'],
        'Medicine' => $o['medicine_name'] ?: 'N/A',
        'Status'   => ucfirst((string)$o['status']),
        'Qty'      => (int)$o['quantity'],
        'Total'    => sup_money($o['total_cost'] ?? 0),
        'Expected' => sup_short_date($o['expected_delivery']),
    ], $data);

    $totalValue = array_sum(array_column($data, 'total_cost'));

    return sup_response_pack('orders', 'Showing ' . count($data) . ' of your order(s)' . ($status ? " with status '{$status}'" : '') . '. Total value: ' . sup_money($totalValue) . '.', [
        'table'   => ['columns' => ['Order', 'Medicine', 'Status', 'Qty', 'Total', 'Expected'], 'rows' => $tableRows],
        'actions' => [['label' => 'My Orders', 'href' => 'sup_orders.php', 'icon' => 'bi-bag-check']],
    ]);
}

function sup_sales(mysqli $conn, int $supplierId, string $q): array {
    if (!sup_table_exists($conn, 'supplier_sales')) {
        return sup_response_pack('sales', 'There are no sales records to show yet for your account.', ['confidence' => 'low']);
    }

    [$start, $end, $label] = sup_detect_range($q);
    $summary = sup_rows($conn, "
        SELECT COUNT(*) AS sale_lines, COALESCE(SUM(line_total),0) AS revenue, COALESCE(SUM(quantity),0) AS units
        FROM supplier_sales
        WHERE supplier_id = ? AND created_at BETWEEN ? AND ?
    ", [$supplierId, $start, $end])[0] ?? [];

    $top = sup_rows($conn, "
        SELECT m.name, SUM(ss.quantity) AS qty, SUM(ss.line_total) AS amount
        FROM supplier_sales ss
        INNER JOIN medicines m ON m.id = ss.medicine_id
        WHERE ss.supplier_id = ? AND ss.created_at BETWEEN ? AND ?
        GROUP BY ss.medicine_id, m.name
        ORDER BY qty DESC
        LIMIT 8
    ", [$supplierId, $start, $end]);

    $tableRows = array_map(fn($r) => [
        'Medicine' => $r['name'],
        'Qty sold' => number_format((int)$r['qty']),
        'Revenue'  => sup_money($r['amount']),
    ], $top);

    return sup_response_pack('sales', "Your sales for {$label}: " . number_format((int)($summary['sale_lines'] ?? 0)) . ' sale line(s), total ' . sup_money($summary['revenue'] ?? 0) . '.', [
        'cards' => [
            ['label' => 'Sale lines', 'value' => number_format((int)($summary['sale_lines'] ?? 0)),  'meta' => $label],
            ['label' => 'Revenue',    'value' => sup_money($summary['revenue'] ?? 0),           'meta' => 'Your supplier sales'],
            ['label' => 'Units sold', 'value' => number_format((int)($summary['units'] ?? 0)),  'meta' => 'Quantity moved'],
        ],
        'table'   => $tableRows ? ['columns' => ['Medicine', 'Qty sold', 'Revenue'], 'rows' => $tableRows] : null,
        'actions' => [
            ['label' => 'My Sales', 'href' => 'sup_sales.php', 'icon' => 'bi-cart-check'],
            ['label' => 'Reports',  'href' => 'sup_reports.php', 'icon' => 'bi-file-earmark-bar-graph'],
        ],
    ]);
}

function sup_transactions(mysqli $conn, int $supplierId, string $q): array {
    if (!sup_table_exists($conn, 'supplier_transactions')) {
        return sup_response_pack('transactions', 'There are no transaction records to show yet for your account.', ['confidence' => 'low']);
    }

    [$start, $end, $label] = sup_detect_range($q);
    $med = sup_find_medicine($conn, $supplierId, $q);

    $where  = 'WHERE st.supplier_id = ? AND st.timestamp BETWEEN ? AND ?';
    $params = [$supplierId, $start, $end];
    if ($med) {
        $where .= ' AND st.medicine_id = ?';
        $params[] = (int)$med['id'];
    }

    $data = sup_rows($conn, "
        SELECT st.action, st.transaction_type, st.quantity, st.total_cost, st.timestamp, st.notes, m.name AS medicine_name
        FROM supplier_transactions st
        LEFT JOIN medicines m ON m.id = st.medicine_id
        {$where}
        ORDER BY st.timestamp DESC
        LIMIT 20
    ", $params);

    $tableRows = array_map(fn($t) => [
        'Medicine' => $t['medicine_name'] ?: 'N/A',
        'Action'   => ucfirst((string)$t['action']),
        'Type'     => ucwords(str_replace('_', ' ', (string)($t['transaction_type'] ?? ''))),
        'Qty'      => (int)$t['quantity'],
        'Amount'   => sup_money($t['total_cost'] ?? 0),
        'Time'     => sup_short_date($t['timestamp']),
    ], $data);

    return sup_response_pack('transactions', 'Your latest transaction records for ' . ($med ? $med['name'] : $label) . '.', [
        'table'   => ['columns' => ['Medicine', 'Action', 'Type', 'Qty', 'Amount', 'Time'], 'rows' => $tableRows],
        'actions' => [['label' => 'Transactions', 'href' => 'sup_transactions.php', 'icon' => 'bi-receipt']],
    ]);
}

function sup_workflow(string $q): array {
    $guides = [
        'inventory' => ['response' => 'Open My Inventory to update quantities, review listings, and manage your supplier catalog.', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam', 'label' => 'My Inventory'],
        'medicine'  => ['response' => 'Open My Inventory to update quantities, review listings, and manage your supplier catalog.', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam', 'label' => 'My Inventory'],
        'order'     => ['response' => 'Open Orders to review incoming purchase requests, accept or decline them, and track delivery status.', 'href' => 'sup_orders.php', 'icon' => 'bi-bag-check', 'label' => 'My Orders'],
        'sale'      => ['response' => 'Open Sales to review completed supplier sales lines and revenue.', 'href' => 'sup_sales.php', 'icon' => 'bi-cart-check', 'label' => 'My Sales'],
        'report'    => ['response' => 'Open Reports to export your supplier performance and inventory summaries.', 'href' => 'sup_reports.php', 'icon' => 'bi-file-earmark-bar-graph', 'label' => 'Reports'],
        'setting'   => ['response' => 'Open Settings to adjust notification and display preferences for your supplier workspace.', 'href' => 'sup_settings.php', 'icon' => 'bi-gear', 'label' => 'Settings'],
    ];

    foreach ($guides as $key => $guide) {
        if (str_contains($q, $key)) {
            return sup_response_pack('workflow', $guide['response'], [
                'actions' => [['label' => $guide['label'], 'href' => $guide['href'], 'icon' => $guide['icon']]],
            ]);
        }
    }

    return sup_response_pack('workflow', 'Ask about your inventory, orders, sales, transactions, or say how do I manage orders/inventory.', [
        'confidence' => 'medium',
        'actions'    => [['label' => 'Dashboard', 'href' => 'sup_dashboard.php', 'icon' => 'bi-speedometer2']],
    ]);
}

if ($query === '') {
    sup_assistant_json(sup_response_pack('empty', 'Ask about your inventory, orders, low stock, sales, or transactions.', [
        'suggestions' => default_supplier_suggestions(),
    ]));
}

try {
    $q = strtolower($query);

    if (sup_is_out_of_scope($q)) {
        sup_assistant_json(sup_out_of_scope_response($supplierCompany));
    }

    if (preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening))\b/', $q)) {
        sup_assistant_json(sup_response_pack('greeting', "Hello! I'm your supplier assistant for {$supplierCompany}. I can check your stock, orders, sales, and transactions.", [
            'suggestions' => default_supplier_suggestions(),
        ]));
    }

    if (preg_match('/\b(help|what can you do|capabilities)\b/', $q)) {
        sup_assistant_json(sup_response_pack('help', "I answer questions about {$supplierCompany}'s supplier workspace only — your inventory, orders, sales, expiry alerts, and transactions.", [
            'actions'     => [['label' => 'Dashboard', 'href' => 'sup_dashboard.php', 'icon' => 'bi-speedometer2']],
            'suggestions' => default_supplier_suggestions(),
        ]));
    }

    if (preg_match('/\b(how do i|how to|where can i|guide|steps|navigate)\b/', $q)) {
        sup_assistant_json(sup_workflow($q));
    }

    if (preg_match('/\b(overview|dashboard|summary|status)\b/', $q)) {
        $pack = sup_overview($conn, $supplierId, $supplierCompany);
        $ai   = sup_ai_response($query, $history, sup_build_snapshot($conn, $supplierId, $supplierCompany), $supplierCompany);
        if ($ai) {
            $pack['response'] = $ai['text'];
            $pack['ai_enhanced'] = true;
        }
        sup_assistant_json($pack);
    }

    if (preg_match('/\b(low stock|critical|shortage|reorder|running low)\b/', $q)) {
        sup_assistant_json(sup_low_stock($conn, $supplierId));
    }

    if (preg_match('/\b(expir|expired|expiry|dispose)\b/', $q)) {
        sup_assistant_json(sup_expiry($conn, $supplierId, $q));
    }

    if (preg_match('/\b(order|orders|purchase|delivery|deliveries)\b/', $q)) {
        sup_assistant_json(sup_orders($conn, $supplierId, $q));
    }

    if (preg_match('/\b(sale|sales|revenue|invoice|sold)\b/', $q)) {
        sup_assistant_json(sup_sales($conn, $supplierId, $q));
    }

    if (preg_match('/\b(transaction|transactions|movement|history|activity)\b/', $q)) {
        sup_assistant_json(sup_transactions($conn, $supplierId, $q));
    }

    if (preg_match('/\b(stock|inventory|medicine|medicines|catalog|product)\b/', $q) || sup_find_medicine($conn, $supplierId, $query)) {
        $pack = sup_inventory($conn, $supplierId, $query);
        $ai   = sup_ai_response($query, $history, sup_build_snapshot($conn, $supplierId, $supplierCompany), $supplierCompany);
        if ($ai) {
            $pack['response'] = $ai['text'];
            $pack['ai_enhanced'] = true;
        }
        sup_assistant_json($pack);
    }

    $snap = sup_build_snapshot($conn, $supplierId, $supplierCompany);
    $ai   = sup_ai_response($query, $history, $snap, $supplierCompany);
    if ($ai) {
        sup_assistant_json(sup_response_pack('ai_answer', $ai['text'], [
            'ai_enhanced' => true,
            'actions'     => [['label' => 'Dashboard', 'href' => 'sup_dashboard.php', 'icon' => 'bi-speedometer2']],
        ]));
    }

    sup_assistant_json(sup_response_pack('fallback', "Try asking about your inventory, pending orders, sales this month, or low-stock medicines for {$supplierCompany}.", [
        'confidence'  => 'low',
        'suggestions' => default_supplier_suggestions(),
    ]));
} catch (Throwable $e) {
    error_log('Supplier chatbot API error: ' . $e->getMessage());
    sup_assistant_json(sup_response_pack('fallback', "I couldn't build a full answer for that question. Try one of the suggestions below, or ask about your inventory, orders, sales, or transactions.", [
        'confidence'  => 'medium',
        'suggestions' => default_supplier_suggestions(),
        'actions'     => [
            ['label' => 'My Inventory', 'href' => 'sup_medicine.php', 'icon' => 'bi-box-seam'],
            ['label' => 'My Orders',    'href' => 'sup_orders.php',   'icon' => 'bi-bag-check'],
        ],
    ]));
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
