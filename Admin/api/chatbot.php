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

function assistant_json(array $payload, int $status = 200): void {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    assistant_json(['success' => false, 'message' => 'Please sign in before using the assistant.'], 401);
}

if (!isset($conn) || !$conn) {
    assistant_json(['success' => false, 'message' => 'Service unavailable', 'response' => 'The assistant is temporarily unavailable. Please try again in a moment or open your dashboard directly.'], 503);
}

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput ?: '', true);
$query     = trim((string)($jsonInput['query']    ?? $_POST['query']    ?? $_GET['query']    ?? ''));
$history   = $jsonInput['history'] ?? [];   // array of {role, content} pairs
$topic     = strtolower(trim((string)($jsonInput['topic'] ?? $_POST['topic'] ?? $_GET['topic'] ?? '')));
$userRole  = (string)($_SESSION['role']    ?? 'guest');
$userId    = (int)($_SESSION['user_id']    ?? 0);

if ($query === '') {
    assistant_json([
        'success'     => true,
        'response'    => 'Ask me about inventory, sales, suppliers, purchases, transactions, reports, users, or system steps.',
        'intent'      => 'empty',
        'suggestions' => default_suggestions($userRole)
    ]);
}

/* ─── helpers ─────────────────────────────────────────────────────────────── */

function default_suggestions(string $role): array {
    $base = [
        ['label' => 'System overview',    'query' => 'system overview'],
        ['label' => 'Low stock',          'query' => 'show low stock medicines'],
        ['label' => 'Expiring soon',      'query' => 'show medicines expiring soon'],
        ['label' => 'Sales this month',   'query' => 'summarize sales this month'],
        ['label' => 'Supplier advice',    'query' => 'which suppliers should I order from'],
    ];
    if ($role === 'admin') {
        $base[] = ['label' => 'User activity', 'query' => 'summarize recent user activity'];
    }
    return $base;
}

function can_view_admin_data(string $role): bool  { return $role === 'admin'; }
function can_view_business_data(string $role): bool { return in_array($role, ['admin','pharmacist','staff'], true); }

function is_admin_portal_role(string $role): bool {
    return in_array(strtolower($role), ['admin', 'pharmacist', 'staff'], true);
}

/** Supplier workspace tables are private to each supplier account — never expose in admin assistant. */
function is_supplier_private_data_request(string $q): bool {
    return (bool)preg_match(
        '/\b(supplier[_\s-]?(inventory|sales|transactions|workspace|portal|private)|other supplier|competitor|their stock|their inventory|their sales|their revenue)\b/i',
        $q
    );
}

function supplier_private_data_response(): array {
    return response_pack('restricted', 'Supplier workspace inventory, sales, and transaction details are private to each supplier account. I can help with pharmacy inventory, orders placed with suppliers, supplier profiles, and system-wide sales instead.', [
        'confidence'  => 'high',
        'suggestions' => [
            ['label' => 'Supplier profiles', 'query' => 'show supplier profiles and reliability'],
            ['label' => 'Pending orders',  'query' => 'show pending orders'],
            ['label' => 'Pharmacy stock',  'query' => 'show low stock medicines'],
        ],
    ]);
}

function infer_types(array $params): string {
    $t = '';
    foreach ($params as $p) {
        $t .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
    }
    return $t;
}

function rows(mysqli $conn, string $sql, array $params = []): array {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException($conn->error);
    if ($params) { $stmt->bind_param(infer_types($params), ...$params); }
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $data;
}

function scalar(mysqli $conn, string $sql, array $params = [], $default = 0) {
    $data = rows($conn, $sql, $params);
    if (!$data) return $default;
    return array_values($data[0])[0] ?? $default;
}

function table_exists(mysqli $conn, string $table): bool {
    return (int)scalar($conn, "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", [$table]) > 0;
}

function money($v): string { return 'PHP ' . number_format((float)$v, 2); }

function short_date($v): string {
    if (!$v) return 'N/A';
    $t = strtotime((string)$v);
    return $t ? date('M d, Y', $t) : (string)$v;
}

function detect_range(string $q): array {
    $today = date('Y-m-d');
    if (preg_match('/\byesterday\b/', $q)) {
        $d = date('Y-m-d', strtotime('-1 day'));
        return [$d.' 00:00:00', $d.' 23:59:59', 'yesterday'];
    }
    if (preg_match('/\btoday\b/', $q)) return [$today.' 00:00:00', $today.' 23:59:59', 'today'];
    if (preg_match('/\b(this|current)\s+week\b|\bweek\b/', $q))
        return [date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 23:59:59', strtotime('sunday this week')), 'this week'];
    if (preg_match('/\blast\s+week\b/', $q))
        return [date('Y-m-d 00:00:00', strtotime('monday last week')), date('Y-m-d 23:59:59', strtotime('sunday last week')), 'last week'];
    if (preg_match('/\b(this|current)\s+month\b|\bmonth\b/', $q))
        return [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59'), 'this month'];
    if (preg_match('/\blast\s+month\b/', $q))
        return [date('Y-m-01 00:00:00', strtotime('first day of last month')), date('Y-m-t 23:59:59', strtotime('last day of last month')), 'last month'];
    if (preg_match('/\b(\d{4})\b/', $q, $m))
        return [$m[1].'-01-01 00:00:00', $m[1].'-12-31 23:59:59', $m[1]];
    return [date('Y-m-d 00:00:00', strtotime('-30 days')), date('Y-m-d 23:59:59'), 'last 30 days'];
}

function find_medicine(mysqli $conn, string $query): ?array {
    if (preg_match('/\b(\d{10,13})\b/', $query, $m)) {
        $r = rows($conn, "SELECT * FROM medicines WHERE barcode=? LIMIT 1", [$m[1]]);
        return $r[0] ?? null;
    }
    $stop = ['show','find','medicine','medicines','stock','quantity','price','cost','for','of','about','the','a','an',
             'who','supplies','supplier','details','info','information','how','many','is','are','available','left',
             'what','check','get','list','give','tell','me','its','with','has','have','does'];
    $tokens = preg_split('/[^a-z0-9]+/i', strtolower($query));
    $tokens = array_values(array_filter($tokens, fn($t) => strlen($t) > 2 && !in_array($t, $stop, true)));
    if (!$tokens) return null;
    $terms = array_slice($tokens, 0, 4);
    $where = []; $params = [];
    foreach ($terms as $term) {
        $where[] = "(LOWER(name) LIKE ? OR LOWER(barcode) LIKE ? OR LOWER(category) LIKE ? OR LOWER(description) LIKE ?)";
        $like = '%'.$term.'%';
        array_push($params, $like, $like, $like, $like);
    }
    $r = rows($conn, "SELECT * FROM medicines WHERE ".implode(' OR ', $where)." ORDER BY name ASC LIMIT 1", $params);
    return $r[0] ?? null;
}

function response_pack(string $intent, string $response, array $extra = []): array {
    return array_merge([
        'success'     => true,
        'intent'      => $intent,
        'response'    => $response,
        'confidence'  => $extra['confidence'] ?? 'high',
        'cards'       => [],
        'table'       => null,
        'actions'     => [],
        'suggestions' => default_suggestions((string)($_SESSION['role'] ?? 'guest')),
        'ai_enhanced' => false,
    ], $extra);
}

function last_user_topic(array $history, string $currentQuery = ''): string {
    $currentQuery = strtolower(trim($currentQuery));
    for ($i = count($history) - 1; $i >= 0; $i--) {
        $item = $history[$i] ?? [];
        if (($item['role'] ?? '') === 'user') {
            $content = trim((string)($item['content'] ?? ''));
            if ($currentQuery !== '' && strtolower($content) === $currentQuery) continue;
            if ($content !== '') return $content;
        }
    }
    return '';
}

function topic_context_phrase(string $topic): string {
    $map = [
        'system'   => 'system status and urgent inventory risks',
        'stock'    => 'medicine stock levels, low stock, and reorder needs',
        'expiry'   => 'medicine expiry dates and expired items',
        'supplier' => 'suppliers, supplier reliability, and ordering sources',
        'order'    => 'orders, pending orders, and delivery status',
        'sales'    => 'sales, invoices, revenue, and sold medicines',
        'workflow' => 'system workflow steps and where to navigate',
    ];
    return $map[$topic] ?? '';
}

function enrich_followup_query(string $query, array $history, string $topic = ''): string {
    $trimmed = trim($query);
    $q = strtolower($trimmed);
    $hasDomain = preg_match('/\b(medicine|medicines|stock|supplier|suppliers|order|orders|sales|invoice|expiry|expired|purchase|transaction|notification|report|dashboard|system|user|activity)\b/', $q);
    $isShortQuestion = preg_match('/^(what|where|when|why|who|how|show me more|more details|details|explain|yes|okay|ok)(\s+about\s+that|\s+about\s+this|\s+now|\?)?$/', $q);

    if (!$hasDomain && $isShortQuestion) {
        $topicPhrase = topic_context_phrase($topic);
        if ($topicPhrase !== '') {
            return $trimmed . ' about ' . $topicPhrase;
        }

        $topic = last_user_topic($history, $trimmed);
        if ($topic !== '') {
            return $trimmed . ' about: ' . $topic;
        }
    }

    return $trimmed;
}

function question_word(string $q): ?string {
    if (preg_match('/\b(what|where|when|why|who|how)\b/', strtolower($q), $m)) {
        return $m[1];
    }
    return null;
}

function five_w_one_h_response(mysqli $conn, string $query, string $q, string $role): ?array {
    $word = question_word($q);
    if (!$word) return null;

    if ($word === 'who') {
        if (preg_match('/\b(supplier|supplies|vendor|order from|provide|provides)\b/', $q) || find_medicine($conn, $query)) {
            return suppliers($conn, $q);
        }
        if (preg_match('/\b(user|staff|pharmacist|admin|activity|login)\b/', $q)) {
            return user_activity($conn, $role);
        }
    }

    if ($word === 'what') {
        if (preg_match('/\b(status|summary|overview|system|dashboard|risk|priority|urgent)\b/', $q)) {
            return overview($conn, $role);
        }
        if (preg_match('/\b(reorder|order today|running low|low)\b/', $q)) {
            return low_stock($conn);
        }
        if (find_medicine($conn, $query)) {
            return medicine_lookup($conn, $query);
        }
    }

    if ($word === 'where') {
        if (preg_match('/\b(risk|urgent|priority|problem|issue)\b/', $q)) {
            $pack = overview($conn, $role);
            $pack['response'] = 'The urgent risks are shown in the system summary below. Start with critical stock, expired medicines, and expiring items, then open the linked page to act on them.';
            return $pack;
        }
        if (preg_match('/\b(create|update|manage|review|find|open|go|navigate|see|check)\b/', $q)) {
            return workflow_response($q);
        }
    }

    if ($word === 'when') {
        if (preg_match('/\b(expir|expired|expiry|dispose|medicine|medicines)\b/', $q)) {
            return expiry($conn, $q);
        }
        if (preg_match('/\b(order|delivery|deliveries|arrive|arrival)\b/', $q)) {
            return orders($conn, $q);
        }
        if (preg_match('/\b(sale|sales|invoice|sold|transaction)\b/', $q)) {
            return sales_summary($conn, $q);
        }
    }

    if ($word === 'why') {
        if (preg_match('/\b(low|stock|reorder|shortage|running out|critical)\b/', $q)) {
            $pack = low_stock($conn);
            $pack['response'] = 'Stock is considered risky when quantity falls below the configured threshold, reaches critical levels, or daily usage suggests the item will run out soon. The table highlights the items most likely to need reorder action first.';
            $pack['suggestions'] = [
                ['label' => 'Who supplies these?', 'query' => 'who supplies the low stock medicines'],
                ['label' => 'How do I reorder?', 'query' => 'how do I reorder low stock medicines'],
                ['label' => 'Show pending orders', 'query' => 'show pending orders'],
            ];
            return $pack;
        }
        if (preg_match('/\b(expir|expired|expiry)\b/', $q)) {
            $pack = expiry($conn, $q);
            $pack['response'] = 'Expiry risk is based on the configured alert window and the medicine expiry dates. Items closest to expiry should be sold first if still valid, isolated if expired, and reviewed for disposal.';
            return $pack;
        }
    }

    if ($word === 'how') {
        if (preg_match('/\b(order|reorder|purchase|supplier|report|sale|medicine|settings|purchase|navigate|create|update|manage|generate)\b/', $q)) {
            return workflow_response($q);
        }
        return response_pack('help', 'You can ask me using 5W and 1H questions: what is low stock, where can I update orders, when do items expire, why is stock critical, who supplies an item, or how do I create a report.', [
            'suggestions' => default_suggestions($role),
        ]);
    }

    return null;
}

/* ─── database snapshot for AI context ────────────────────────────────────── */

function build_db_snapshot(mysqli $conn, string $role): array {
    $snapshot = [];

    // Key inventory stats
    $threshold   = (int)scalar($conn, "SELECT COALESCE(value,500) FROM settings WHERE setting_key='low_stock_threshold' ORDER BY id DESC LIMIT 1", [], 500);
    $expiryDays  = (int)scalar($conn, "SELECT COALESCE(value,60) FROM settings WHERE setting_key='expiry_alert_days' ORDER BY id DESC LIMIT 1", [], 60);
    $expiryEnd   = date('Y-m-d', strtotime("+{$expiryDays} days"));

    $snapshot['inventory'] = [
        'total_medicines' => (int)scalar($conn, "SELECT COUNT(*) FROM medicines"),
        'total_units'     => (int)scalar($conn, "SELECT COALESCE(SUM(quantity),0) FROM medicines"),
        'low_stock_count' => (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE quantity<=?", [$threshold]),
        'critical_count'  => (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE quantity<=5"),
        'expiring_count'  => (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND ?", [$expiryEnd]),
        'expired_count'   => (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()"),
        'low_threshold'   => $threshold,
        'expiry_days'     => $expiryDays,
    ];

    // Top low-stock items
    $snapshot['low_stock_items'] = rows($conn, "
        SELECT name, quantity, type, expiry_date, selling_price, reorder_point, average_daily_usage, suppliers
        FROM medicines WHERE quantity<=? ORDER BY quantity ASC LIMIT 10
    ", [$threshold]);

    // Expiring items
    $snapshot['expiring_items'] = rows($conn, "
        SELECT name, quantity, type, expiry_date,
               DATEDIFF(expiry_date, CURDATE()) AS days_left
        FROM medicines
        WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND ?
        ORDER BY expiry_date ASC LIMIT 10
    ", [$expiryEnd]);

    // Sales summary (current month)
    if (table_exists($conn, 'sales_invoices')) {
        $snapshot['sales_this_month'] = rows($conn, "
            SELECT COUNT(*) AS invoices,
                   COALESCE(SUM(total_amount),0) AS total,
                   COALESCE(AVG(total_amount),0) AS avg_invoice
            FROM sales_invoices WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
        ")[0] ?? [];
    }

    // Orders
    $snapshot['pending_orders'] = (int)scalar($conn, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','ordered')");
    $snapshot['recent_orders']  = rows($conn, "
        SELECT o.id, o.status, o.order_date, o.expected_delivery, o.quantity, o.unit_price, o.total_cost,
               m.name AS medicine_name, s.company AS supplier_name
        FROM orders o
        LEFT JOIN medicines m ON m.id=o.medicine_id
        LEFT JOIN suppliers s ON s.id=o.supplier_id
        ORDER BY o.order_date DESC LIMIT 5
    ");

    // Top suppliers
    $snapshot['top_suppliers'] = rows($conn, "
        SELECT company, contact, lead_time, reliability_score, total_due, order_status
        FROM suppliers ORDER BY reliability_score DESC LIMIT 8
    ");

    // Pharmacy-level movement only — never aggregate supplier workspace tables here.
    if (table_exists($conn, 'transactions')) {
        $snapshot['top_selling_30d'] = rows($conn, "
            SELECT m.name, SUM(t.quantity) AS units_moved
            FROM transactions t
            JOIN medicines m ON m.id=t.medicine_id
            WHERE t.action='remove' AND t.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY t.medicine_id, m.name
            ORDER BY units_moved DESC LIMIT 8
        ");
    }

    $snapshot['data_scope'] = 'pharmacy_admin_only';
    $snapshot['excluded'] = 'supplier workspace private inventory/sales/transactions';

    if (can_view_admin_data($role) && table_exists($conn, 'user_activity')) {
        $snapshot['recent_activity_count'] = (int)scalar($conn, "SELECT COUNT(*) FROM user_activity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }

    return $snapshot;
}

/* ─── AI-powered response via Anthropic API ───────────────────────────────── */

function ai_enhanced_response(string $query, array $history, array $dbContext, string $role): ?array {
    $systemPrompt = <<<PROMPT
You are an intelligent pharmacy inventory assistant for the ADMIN workspace in a medicine inventory management system in the Philippines. Prices are in PHP (Philippine Peso).

You have access to real-time PHARMACY-LEVEL database data provided in JSON context. Use this data to give precise, accurate answers. Always refer to actual numbers from the context — never make up values.

Database context:
PROMPT;
    $systemPrompt .= "\n" . json_encode($dbContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $systemPrompt .= "\n\nUser role: {$role}\n\n";
    $systemPrompt .= <<<RULES
Rules:
- Answer all types of questions naturally: What, Where, When, Why, Who, and How.
- Be helpful, clear, and professional like a senior pharmacy staff member.
- Use exact figures from the database context when available.
- DATA ACCESS BOUNDARY: You may use pharmacy inventory, orders, sales invoices, purchases, supplier profiles (company/contact/reliability), and notifications.
- NEVER invent or infer supplier workspace private data (supplier_inventory, supplier_sales, supplier_transactions, or another supplier's internal stock/revenue).
- If asked about a supplier's private warehouse stock or internal sales, explain that data is only visible inside that supplier's own portal.
- For "how to" questions, give clear step-by-step instructions.
- For general questions without specific data, provide practical pharmacy advice.
- Be concise but informative. Use bullet points or numbered lists when listing multiple items.
- For stock queries, always mention quantity, supplier if known, and expiry if relevant.
- For financial data, always use PHP currency prefix.
- Never fabricate medicine names, prices, or quantities.
- Suggest actionable next steps when relevant (e.g., "You can place an order under Orders page").
- Keep responses under 220 words unless the user asks for a detailed breakdown.
- Format numbers clearly: 1,234 units, PHP 12,345.00.
- Only show admin-level data (user activity) if user role is admin.
RULES;

    // Build messages array with conversation history
    $messages = [];
    foreach (array_slice($history, -10) as $h) {
        if (isset($h['role'], $h['content']) && in_array($h['role'], ['user','assistant'])) {
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
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err || $code !== 200 || !$raw) return null;

    $data = json_decode($raw, true);
    $text = $data['content'][0]['text'] ?? null;
    if (!$text) return null;

    return ['text' => trim($text)];
}

/* ─── intent handlers ─────────────────────────────────────────────────────── */

function workflow_response(string $q): array {
    $guides = [
        'medicine' => ['response' => 'To manage medicines, open Medicines. You can add items, search by name or barcode, edit details, and delete records.',
            'actions' => [['label' => 'Open Medicines', 'href' => 'medicine.php', 'icon' => 'bi-capsule']]],
        'order'    => ['response' => 'To create an order, go to Orders, select a supplier and medicine, set quantity and expected delivery, then submit.',
            'actions' => [['label' => 'Open Orders', 'href' => 'orders.php', 'icon' => 'bi-box-seam']]],
        'report'   => ['response' => 'To generate reports, open Reports, choose Inventory, Transactions, or Sales, apply filters, then preview or export.',
            'actions' => [['label' => 'Open Reports', 'href' => 'reports.php', 'icon' => 'bi-file-earmark-bar-graph']]],
        'sale'     => ['response' => 'To process a sale, open Sales, search medicines, add quantities, collect payment, and print or view the invoice.',
            'actions' => [['label' => 'Open Sales', 'href' => 'sales.php', 'icon' => 'bi-cart-check']]],
        'setting'  => ['response' => 'To adjust system settings like stock thresholds and expiry alerts, open Settings.',
            'actions' => [['label' => 'Open Settings', 'href' => 'settings.php', 'icon' => 'bi-gear']]],
        'supplier' => ['response' => 'To manage suppliers, open Suppliers. Add profiles, edit contacts, and review linked medicines.',
            'actions' => [['label' => 'Open Suppliers', 'href' => 'suppliers.php', 'icon' => 'bi-truck']]],
    ];
    foreach ($guides as $key => $guide) {
        if (str_contains($q, $key)) {
            return response_pack('workflow', $guide['response'], [
                'actions'     => $guide['actions'],
                'suggestions' => [
                    ['label' => 'System overview', 'query' => 'system overview'],
                    ['label' => 'Low stock',        'query' => 'show low stock medicines'],
                    ['label' => 'Generate report',  'query' => 'how do I generate a report'],
                ]
            ]);
        }
    }
    return response_pack('workflow', 'Tell me which part of the system you need help with — medicines, orders, sales, suppliers, reports, or settings.', [
        'confidence' => 'medium',
        'actions'    => [['label' => 'Open Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2']],
    ]);
}

function overview(mysqli $conn, string $role): array {
    $threshold  = (int)scalar($conn, "SELECT COALESCE(value,500) FROM settings WHERE setting_key='low_stock_threshold' ORDER BY id DESC LIMIT 1", [], 500);
    $expiryDays = (int)scalar($conn, "SELECT COALESCE(value,60) FROM settings WHERE setting_key='expiry_alert_days' ORDER BY id DESC LIMIT 1", [], 60);
    $expiryEnd  = date('Y-m-d', strtotime("+{$expiryDays} days"));

    $totalMeds     = (int)scalar($conn, "SELECT COUNT(*) FROM medicines");
    $totalUnits    = (int)scalar($conn, "SELECT COALESCE(SUM(quantity),0) FROM medicines");
    $lowStock      = (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE quantity<=?", [$threshold]);
    $critical      = (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE quantity<=5");
    $expiring      = (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND ?", [$expiryEnd]);
    $expired       = (int)scalar($conn, "SELECT COUNT(*) FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");
    $pendingOrders = (int)scalar($conn, "SELECT COUNT(*) FROM orders WHERE status IN ('pending','ordered')");
    $pendingRx     = table_exists($conn, 'purchases') ? (int)scalar($conn, "SELECT COUNT(*) FROM purchases WHERE status='pending'") : 0;
    $salesToday    = table_exists($conn, 'sales_invoices') ? (float)scalar($conn, "SELECT COALESCE(SUM(total_amount),0) FROM sales_invoices WHERE DATE(created_at)=CURDATE()") : 0;
    $unread        = table_exists($conn, 'notifications') ? (int)scalar($conn, "SELECT COUNT(*) FROM notifications WHERE `read`=0") : 0;

    $priority = [];
    if ($critical  > 0) $priority[] = "{$critical} critical stock item(s) at 5 units or fewer";
    if ($expired   > 0) $priority[] = "{$expired} expired medicine(s) needing disposal";
    if ($expiring  > 0) $priority[] = "{$expiring} item(s) expiring within {$expiryDays} days";
    if ($pendingRx > 0) $priority[] = "{$pendingRx} pending purchase(s) awaiting fill";

    $response = $priority
        ? 'Current operating picture — priority items: ' . implode('; ', $priority) . '.'
        : 'System looks stable. No critical stock, expiry, or purchase issues detected right now.';

    return response_pack('overview', $response, [
        'cards' => [
            ['label' => 'Total medicines',  'value' => number_format($totalMeds),  'meta' => number_format($totalUnits).' units total'],
            ['label' => 'Low stock',        'value' => (string)$lowStock,           'meta' => "Threshold: {$threshold} units"],
            ['label' => 'Expiring soon',    'value' => (string)$expiring,           'meta' => "{$expiryDays}-day window"],
            ['label' => 'Pending orders',   'value' => (string)$pendingOrders,      'meta' => 'Pending or ordered'],
            ['label' => 'Pending Rx',       'value' => (string)$pendingRx,          'meta' => 'Awaiting fill'],
            ['label' => 'Sales today',      'value' => money($salesToday),          'meta' => "{$unread} unread alerts"],
        ],
        'actions' => [
            ['label' => 'Open Dashboard',    'href' => 'dashboard.php',    'icon' => 'bi-speedometer2'],
            ['label' => 'View Notifications','href' => 'notifications.php','icon' => 'bi-bell'],
        ],
        'suggestions' => [
            ['label' => 'What should I reorder?', 'query' => 'what should I reorder today'],
            ['label' => 'Expiring medicines',      'query' => 'show expiring medicines'],
            ['label' => 'Sales this month',        'query' => 'summarize sales this month'],
        ]
    ]);
}

function low_stock(mysqli $conn): array {
    $threshold = (int)scalar($conn, "SELECT COALESCE(value,500) FROM settings WHERE setting_key='low_stock_threshold' ORDER BY id DESC LIMIT 1", [], 500);

    $data = rows($conn, "
        SELECT m.id, m.name, m.quantity, m.type, m.expiry_date,
               m.suppliers, m.selling_price, m.reorder_point, m.average_daily_usage,
               m.forecast_demand
        FROM medicines m
        WHERE m.quantity <= ?
        ORDER BY m.quantity ASC, m.name ASC
        LIMIT 25
    ", [$threshold]);

    if (!$data) {
        return response_pack('low_stock', "No medicines are at or below the low-stock threshold of {$threshold} units.", [
            'actions' => [['label' => 'Open Medicines', 'href' => 'medicine.php', 'icon' => 'bi-capsule']]
        ]);
    }

    $critical = array_filter($data, fn($r) => (int)$r['quantity'] <= 5);

    $tableRows = array_map(function ($r) {
        $adu       = (float)($r['average_daily_usage'] ?? 0);
        $daysLeft  = $adu > 0 ? round((int)$r['quantity'] / $adu) : '—';
        $suggested = max((int)($r['reorder_point'] ?? 0), 50 - (int)$r['quantity'], 20);
        return [
            'Medicine'       => $r['name'],
            'Qty'            => (int)$r['quantity'],
            'Type'           => $r['category'] ?: '—',
            'Supplier'       => $r['suppliers'] ?: 'No supplier',
            'Days left'      => is_numeric($daysLeft) ? $daysLeft.'d' : '—',
            'Suggested order'=> $suggested,
        ];
    }, $data);

    return response_pack('low_stock', 'Found '.count($data).' low-stock item(s). Address the '.count($critical).' critical item(s) (≤5 units) first.', [
        'cards' => [
            ['label' => 'Critical (≤5)',  'value' => (string)count($critical), 'meta' => 'Immediate reorder needed'],
            ['label' => 'Low stock total','value' => (string)count($data),     'meta' => "At or below {$threshold} units"],
        ],
        'table' => ['columns' => ['Medicine','Qty','Type','Supplier','Days left','Suggested order'], 'rows' => $tableRows],
        'actions' => [
            ['label' => 'Open Orders',    'href' => 'orders.php',    'icon' => 'bi-box-seam'],
            ['label' => 'Open Suppliers', 'href' => 'suppliers.php', 'icon' => 'bi-truck'],
            ['label' => 'View Medicines', 'href' => 'medicine.php',  'icon' => 'bi-capsule'],
        ],
        'suggestions' => [
            ['label' => 'Best suppliers',   'query' => 'which suppliers should I order from'],
            ['label' => 'Pending orders',   'query' => 'show pending orders'],
            ['label' => 'Expiring soon',    'query' => 'show medicines expiring soon'],
        ]
    ]);
}

function expiry(mysqli $conn, string $q): array {
    $days = (int)scalar($conn, "SELECT COALESCE(value,60) FROM settings WHERE setting_key='expiry_alert_days' ORDER BY id DESC LIMIT 1", [], 60);
    if (preg_match('/\b(\d+)\s*(day|days)\b/', $q, $m)) $days = max(1, min(365, (int)$m[1]));

    $includeExpired = (bool)preg_match('/\bexpired|dispose|disposal\b/', $q);
    $end = date('Y-m-d', strtotime("+{$days} days"));

    $sql  = $includeExpired
        ? "SELECT name, quantity, category, expiry_date FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date <= ? ORDER BY expiry_date ASC LIMIT 30"
        : "SELECT name, quantity, category, expiry_date FROM medicines WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND ? ORDER BY expiry_date ASC LIMIT 30";
    $data = rows($conn, $sql, [$end]);

    if (!$data) {
        return response_pack('expiry', "No medicines are expiring within the next {$days} days.", [
            'actions' => [['label' => 'Open Medicines', 'href' => 'medicine.php', 'icon' => 'bi-capsule']]
        ]);
    }

    $today = strtotime(date('Y-m-d'));
    $tableRows = array_map(function ($r) use ($today) {
        $diff = (int)floor((strtotime($r['expiry_date']) - $today) / 86400);
        return [
            'Medicine' => $r['name'],
            'Qty'      => (int)$r['quantity'],
            'Type'     => $r['category'] ?: 'N/A',
            'Expiry'   => short_date($r['expiry_date']),
            'Status'   => $diff < 0 ? abs($diff).' days expired' : $diff.' days left',
        ];
    }, $data);

    $alreadyExpired = count(array_filter($tableRows, fn($r) => str_contains($r['Status'], 'expired')));

    return response_pack('expiry', 'Found '.count($data).' expiry item(s)'.($alreadyExpired ? " — {$alreadyExpired} already expired." : ' expiring soon.'), [
        'table'  => ['columns' => ['Medicine','Qty','Type','Expiry','Status'], 'rows' => $tableRows],
        'actions' => [
            ['label' => 'Open Medicines',    'href' => 'medicine.php',    'icon' => 'bi-capsule'],
            ['label' => 'Open Notifications','href' => 'notifications.php','icon' => 'bi-bell'],
        ],
        'suggestions' => [
            ['label' => 'Expired only',  'query' => 'show expired medicines'],
            ['label' => 'Next 7 days',   'query' => 'show medicines expiring in 7 days'],
            ['label' => 'Low stock too', 'query' => 'show low stock medicines'],
        ]
    ]);
}

function medicine_lookup(mysqli $conn, string $query): array {
    $med = find_medicine($conn, $query);
    if (!$med) {
        return response_pack('medicine_lookup', 'I could not find that medicine. Try the name or barcode.', [
            'confidence' => 'low',
            'actions'    => [['label' => 'Open Medicines', 'href' => 'medicine.php', 'icon' => 'bi-capsule']],
        ]);
    }

    // Supplier info from the medicines.suppliers column (comma-separated supplier names)
    // Also check supplier_medicines table if it exists
    $supplierInfo = [];
    if (table_exists($conn, 'supplier_medicines')) {
        $supplierInfo = rows($conn, "
            SELECT s.company, s.contact, s.lead_time, s.reliability_score, sm.unit_price, sm.quantity_supplied, sm.preferred
            FROM supplier_medicines sm
            JOIN suppliers s ON s.id=sm.supplier_id
            WHERE sm.medicine_id=?
            ORDER BY sm.preferred DESC, s.reliability_score DESC LIMIT 5
        ", [(int)$med['id']]);
    }
    if (!$supplierInfo && $med['suppliers']) {
        // Fall back to suppliers column + suppliers table name match
        $names = array_map('trim', explode(',', $med['suppliers']));
        foreach ($names as $name) {
            $s = rows($conn, "SELECT company, contact, lead_time, reliability_score FROM suppliers WHERE company LIKE ? LIMIT 1", ['%'.$name.'%']);
            if ($s) $supplierInfo[] = array_merge($s[0], ['preferred' => 1, 'unit_price' => null, 'quantity_supplied' => null]);
        }
    }

    // Movement last 30 days from transactions table
    $sold30 = 0;
    if (table_exists($conn, 'transactions')) {
        $sold30 = (int)scalar($conn, "SELECT COALESCE(SUM(quantity),0) FROM transactions WHERE medicine_id=? AND action='remove' AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [(int)$med['id']]);
    }
    $daysLeft = $med['expiry_date']
        ? (int)floor((strtotime($med['expiry_date']) - strtotime(date('Y-m-d'))) / 86400)
        : null;

    $adu     = (float)($med['average_daily_usage'] ?? 0);
    $daysOfStock = $adu > 0 ? round((int)$med['quantity'] / $adu) : null;

    $response = "{$med['name']} — {$med['quantity']} unit(s) on hand";
    if ($med['selling_price']) $response .= ", selling at ".money($med['selling_price']);
    $response .= ". Dispensed/sold {$sold30} unit(s) in the last 30 days.";
    if ($daysLeft !== null) {
        $response .= $daysLeft < 0 ? ' ⚠️ Already expired.' : " Expires in {$daysLeft} day(s).";
    }
    if ($daysOfStock !== null) {
        $response .= " At current usage (~{$adu}/day), stock lasts ~{$daysOfStock} day(s).";
    }

    $supplierRows = array_map(fn($s) => [
        'Supplier'      => $s['company'],
        'Contact'       => $s['contact'] ?: 'N/A',
        'Lead time'     => isset($s['lead_time']) ? $s['lead_time'].'d' : 'N/A',
        'Unit price'    => $s['unit_price'] !== null ? money($s['unit_price']) : 'N/A',
        'Reliability'   => isset($s['reliability_score']) ? $s['reliability_score'].'/100' : 'N/A',
        'Preferred'     => (int)($s['preferred'] ?? 0) === 1 ? '✓' : '',
    ], $supplierInfo);

    return response_pack('medicine_lookup', $response, [
        'cards' => [
            ['label' => 'Current stock',   'value' => number_format((int)$med['quantity']), 'meta' => $med['category'] ?: 'No category'],
            ['label' => '30-day movement', 'value' => (string)$sold30,                       'meta' => 'Units dispensed/sold'],
            ['label' => 'Selling price',   'value' => money($med['selling_price'] ?? 0),     'meta' => 'Per unit'],
        ],
        'table'  => $supplierRows ? ['columns' => ['Supplier','Contact','Lead time','Unit price','Reliability','Preferred'], 'rows' => $supplierRows] : null,
        'actions' => [
            ['label' => 'Open Medicines', 'href' => 'medicine.php', 'icon' => 'bi-capsule'],
            ['label' => 'Open Orders',    'href' => 'orders.php',   'icon' => 'bi-box-seam'],
        ],
        'suggestions' => [
            ['label' => 'Reorder advice', 'query' => 'should I reorder '.$med['name']],
            ['label' => 'Transactions',   'query' => 'recent transactions for '.$med['name']],
        ]
    ]);
}

function suppliers(mysqli $conn, string $q): array {
    $med = find_medicine($conn, $q);
    if ($med && preg_match('/\b(who|which|supplier|supplies|provides|order)\b/', $q)) {
        // Get suppliers for this medicine via suppliers column or supplier_medicines
        $data = [];
        if (table_exists($conn, 'supplier_medicines')) {
            $data = rows($conn, "
                SELECT s.company, s.contact, s.lead_time, s.reliability_score, sm.unit_price, sm.quantity_supplied, sm.preferred
                FROM supplier_medicines sm JOIN suppliers s ON s.id=sm.supplier_id
                WHERE sm.medicine_id=? ORDER BY sm.preferred DESC, s.reliability_score DESC LIMIT 10
            ", [(int)$med['id']]);
        }
        if (!$data && $med['suppliers']) {
            $names = array_map('trim', explode(',', $med['suppliers']));
            foreach ($names as $name) {
                $s = rows($conn, "SELECT company, contact, lead_time, reliability_score FROM suppliers WHERE company LIKE ? LIMIT 1", ['%'.$name.'%']);
                if ($s) $data[] = array_merge($s[0], ['preferred' => 1, 'unit_price' => null, 'quantity_supplied' => null]);
            }
        }
        if (!$data) {
            return response_pack('suppliers', "Found {$med['name']}, but no supplier is linked to it yet.", [
                'actions' => [['label' => 'Open Suppliers', 'href' => 'suppliers.php', 'icon' => 'bi-truck']]
            ]);
        }
        $rows = array_map(fn($s) => [
            'Supplier'    => $s['company'],
            'Contact'     => $s['contact'] ?: 'N/A',
            'Lead time'   => isset($s['lead_time']) ? $s['lead_time'].'d' : 'N/A',
            'Unit price'  => $s['unit_price'] !== null ? money($s['unit_price']) : 'N/A',
            'Reliability' => isset($s['reliability_score']) ? $s['reliability_score'].'/100' : 'N/A',
        ], $data);
        return response_pack('suppliers', "Suppliers for {$med['name']} — sorted by preference and reliability:", [
            'table'  => ['columns' => ['Supplier','Contact','Lead time','Unit price','Reliability'], 'rows' => $rows],
            'actions' => [
                ['label' => 'Open Suppliers', 'href' => 'suppliers.php', 'icon' => 'bi-truck'],
                ['label' => 'Open Orders',    'href' => 'orders.php',   'icon' => 'bi-box-seam'],
            ]
        ]);
    }

    $data = rows($conn, "
        SELECT company, contact, lead_time, reliability_score, total_due, total_buy, order_status,
               bought_medicines, bought_quantity
        FROM suppliers
        ORDER BY reliability_score DESC, company ASC LIMIT 20
    ");
    $table = array_map(fn($s) => [
        'Supplier'    => $s['company'],
        'Contact'     => $s['contact'] ?: 'N/A',
        'Lead time'   => $s['lead_time'].'d',
        'Reliability' => $s['reliability_score'].'/100',
        'Total due'   => money($s['total_due'] ?? 0),
        'Status'      => ucfirst((string)$s['order_status']),
    ], $data);
    return response_pack('suppliers', 'Here are your suppliers, sorted by reliability score.', [
        'table'  => ['columns' => ['Supplier','Contact','Lead time','Reliability','Total due','Status'], 'rows' => $table],
        'actions' => [['label' => 'Open Suppliers', 'href' => 'suppliers.php', 'icon' => 'bi-truck']],
    ]);
}

function orders(mysqli $conn, string $q): array {
    // Schema: orders table is flat (no order_items), each row is one medicine
    $status = null;
    foreach (['pending','ordered','accepted','delivered','cancelled'] as $s) {
        if (str_contains($q, $s)) { $status = $s; break; }
    }
    $where  = $status ? 'WHERE o.status=?' : '';
    $params = $status ? [$status] : [];

    $data = rows($conn, "
        SELECT o.id, o.status, o.order_date, o.expected_delivery, o.actual_delivery,
               o.quantity, o.unit_price, o.total_cost, o.notes,
               m.name AS medicine_name, s.company AS supplier_name, s.reliability_score
        FROM orders o
        LEFT JOIN medicines m ON m.id=o.medicine_id
        LEFT JOIN suppliers s ON s.id=o.supplier_id
        {$where}
        ORDER BY o.order_date DESC LIMIT 20
    ", $params);

    if (!$data) {
        return response_pack('orders', $status ? "No {$status} orders found." : 'No orders found.', [
            'actions' => [['label' => 'Open Orders', 'href' => 'orders.php', 'icon' => 'bi-box-seam']]
        ]);
    }

    $totalValue = array_sum(array_column($data, 'total_cost'));
    $table = array_map(fn($o) => [
        'Order'    => '#'.$o['id'],
        'Medicine' => $o['medicine_name'] ?: 'N/A',
        'Supplier' => $o['supplier_name'] ?: 'N/A',
        'Status'   => ucfirst((string)$o['status']),
        'Qty'      => (int)$o['quantity'],
        'Total'    => money($o['total_cost'] ?? 0),
        'Expected' => short_date($o['expected_delivery']),
    ], $data);

    return response_pack('orders', 'Showing '.count($data).' order(s)'.($status ? " with status '{$status}'" : '').'. Total value: '.money($totalValue).'.', [
        'table'  => ['columns' => ['Order','Medicine','Supplier','Status','Qty','Total','Expected'], 'rows' => $table],
        'actions' => [['label' => 'Open Orders', 'href' => 'orders.php', 'icon' => 'bi-box-seam']],
        'suggestions' => [
            ['label' => 'Pending orders',   'query' => 'show pending orders'],
            ['label' => 'Delivered orders', 'query' => 'show delivered orders'],
        ]
    ]);
}

function sales_summary(mysqli $conn, string $q): array {
    if (!table_exists($conn, 'sales_invoices')) {
        return response_pack('sales', 'No sales invoices are recorded yet for the selected period.', ['confidence' => 'low']);
    }
    [$start, $end, $label] = detect_range($q);

    $summary = rows($conn, "
        SELECT COUNT(*) AS invoices,
               COALESCE(SUM(total_amount),0) AS total,
               COALESCE(AVG(total_amount),0) AS average,
               COALESCE(SUM(discount),0) AS total_discount
        FROM sales_invoices WHERE created_at BETWEEN ? AND ?
    ", [$start, $end])[0] ?? [];

    // Payment method breakdown
    $payMethods = rows($conn, "
        SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS amt
        FROM sales_invoices WHERE created_at BETWEEN ? AND ?
        GROUP BY payment_method ORDER BY amt DESC
    ", [$start, $end]);

    $top = [];
    if (table_exists($conn, 'transactions')) {
        $top = rows($conn, "
            SELECT m.name, SUM(t.quantity) AS qty, SUM(t.total_cost) AS amount
            FROM transactions t
            JOIN medicines m ON m.id=t.medicine_id
            WHERE t.action='remove' AND t.timestamp BETWEEN ? AND ?
            GROUP BY t.medicine_id ORDER BY qty DESC LIMIT 8
        ", [$start, $end]);
    }

    $table = array_map(fn($r) => [
        'Medicine'  => $r['name'],
        'Qty sold'  => number_format((int)$r['qty']),
        'Revenue'   => money($r['amount']),
    ], $top);

    $payStr = implode(', ', array_map(fn($p) => ucfirst($p['payment_method']).': '.money($p['amt']), $payMethods));

    return response_pack('sales', "Sales for {$label}: ".number_format((int)($summary['invoices'] ?? 0))." invoice(s), total ".money($summary['total'] ?? 0).".".($payStr ? " Payment breakdown — {$payStr}." : ''), [
        'cards' => [
            ['label' => 'Invoices',       'value' => number_format((int)($summary['invoices'] ?? 0)), 'meta' => $label],
            ['label' => 'Total sales',    'value' => money($summary['total'] ?? 0),                   'meta' => 'Gross amount'],
            ['label' => 'Avg invoice',    'value' => money($summary['average'] ?? 0),                 'meta' => 'Per transaction'],
            ['label' => 'Total discount', 'value' => money($summary['total_discount'] ?? 0),          'meta' => 'Applied discounts'],
        ],
        'table'  => $table ? ['columns' => ['Medicine','Qty sold','Revenue'], 'rows' => $table] : null,
        'actions' => [
            ['label' => 'Open Sales',   'href' => 'sales.php',   'icon' => 'bi-cart-check'],
            ['label' => 'Open Reports', 'href' => 'reports.php', 'icon' => 'bi-file-earmark-bar-graph'],
        ],
    ]);
}

function transactions(mysqli $conn, string $q): array {
    if (!table_exists($conn, 'transactions')) {
        return response_pack('transactions', 'No pharmacy transaction records are available for that request.', ['confidence' => 'low']);
    }
    $tbl = 'transactions';

    [$start, $end, $label] = detect_range($q);
    $med = find_medicine($conn, $q);

    $where  = "WHERE t.timestamp BETWEEN ? AND ?";
    $params = [$start, $end];
    if ($med) { $where .= " AND t.medicine_id=?"; $params[] = (int)$med['id']; }

    $data = rows($conn, "
        SELECT t.action, t.quantity, t.total_cost, t.timestamp, t.notes,
               m.name AS medicine_name, u.username
        FROM {$tbl} t
        LEFT JOIN medicines m ON m.id=t.medicine_id
        LEFT JOIN users u ON u.id=t.user_id
        {$where}
        ORDER BY t.timestamp DESC LIMIT 20
    ", $params);

    $table = array_map(fn($t) => [
        'Medicine' => $t['medicine_name'] ?: 'N/A',
        'Action'   => ucfirst((string)$t['action']),
        'Qty'      => $t['quantity'] ?? 'N/A',
        'User'     => $t['username'] ?: 'N/A',
        'Cost'     => money($t['total_cost'] ?? 0),
        'Time'     => short_date($t['timestamp']),
    ], $data);

    return response_pack('transactions', 'Latest transaction records for '.($med ? $med['name'] : $label).'.', [
        'table'  => ['columns' => ['Medicine','Action','Qty','User','Cost','Time'], 'rows' => $table],
        'actions' => [['label' => 'Open Transactions', 'href' => 'transactions.php', 'icon' => 'bi-receipt']],
    ]);
}

function purchases(mysqli $conn, string $q): array {
    if (!table_exists($conn, 'purchases')) return response_pack('purchases', 'Purchases table is not available.', ['confidence' => 'low']);

    $status = str_contains($q,'filled') ? 'filled' : (str_contains($q,'cancel') ? 'cancelled' : (str_contains($q,'pending') ? 'pending' : null));
    $where  = $status ? 'WHERE p.status=?' : '';
    $params = $status ? [$status] : [];

    $data = rows($conn, "
        SELECT p.purchase_number, p.status, p.quantity, p.purchase_date, p.total_cost,
               m.name AS medicine_name, u.username AS pharmacist
        FROM purchases p
        JOIN medicines m ON m.id=p.medicine_id
        LEFT JOIN users u ON u.id=p.pharmacist_id
        {$where}
        ORDER BY p.purchase_date DESC, p.id DESC LIMIT 20
    ", $params);

    $table = array_map(fn($p) => [
        'Rx #'     => $p['purchase_number'],
        'Medicine' => $p['medicine_name'],
        'Qty'      => (int)$p['quantity'],
        'Status'   => ucfirst((string)$p['status']),
        'Cost'     => money($p['total_cost'] ?? 0),
        'Date'     => short_date($p['purchase_date']),
    ], $data);

    return response_pack('purchases', count($data).' purchase record(s)'.($status ? " with status '{$status}'" : '').'.', [
        'table'  => ['columns' => ['Rx #','Medicine','Qty','Status','Cost','Date'], 'rows' => $table],
        'actions' => [['label' => 'Open Sales', 'href' => 'sales.php', 'icon' => 'bi-cart-check']],
    ]);
}

function notifications(mysqli $conn): array {
    if (!table_exists($conn, 'notifications')) return response_pack('notifications', 'Notifications table is not available.', ['confidence' => 'low']);

    $data  = rows($conn, "SELECT message, type, `read`, created_at FROM notifications ORDER BY `read` ASC, created_at DESC LIMIT 20");
    $unread = count(array_filter($data, fn($n) => (int)$n['read'] === 0));
    $table  = array_map(fn($n) => [
        'Type'    => ucfirst((string)$n['type']),
        'Message' => $n['message'],
        'Status'  => (int)$n['read'] ? 'Read' : 'Unread',
        'Date'    => short_date($n['created_at']),
    ], $data);

    return response_pack('notifications', "You have {$unread} unread notification(s) out of ".count($data)." shown.", [
        'table'  => ['columns' => ['Type','Message','Status','Date'], 'rows' => $table],
        'actions' => [['label' => 'Open Notifications', 'href' => 'notifications.php', 'icon' => 'bi-bell']],
    ]);
}

function user_activity(mysqli $conn, string $role): array {
    if (!can_view_admin_data($role)) return response_pack('restricted', 'Only admins can view user activity data.', ['confidence' => 'high']);
    if (!table_exists($conn, 'user_activity')) return response_pack('activity', 'User activity records are not available.', ['confidence' => 'low']);

    $data  = rows($conn, "
        SELECT ua.action_type, ua.ip_address, ua.created_at, u.username, u.role
        FROM user_activity ua LEFT JOIN users u ON u.id=ua.user_id
        ORDER BY ua.created_at DESC LIMIT 20
    ");
    $table = array_map(fn($a) => [
        'User'   => $a['username'] ?: 'Unknown',
        'Role'   => ucfirst((string)$a['role']),
        'Action' => ucwords(str_replace('_', ' ', (string)$a['action_type'])),
        'IP'     => $a['ip_address'] ?: 'N/A',
        'Date'   => short_date($a['created_at']),
    ], $data);

    return response_pack('activity', 'Latest '.count($data).' user activity record(s).', [
        'table'  => ['columns' => ['User','Role','Action','IP','Date'], 'rows' => $table],
        'actions' => [['label' => 'Open User Activity', 'href' => 'user_activity.php', 'icon' => 'bi-activity']],
    ]);
}

/* ─── main router ─────────────────────────────────────────────────────────── */

try {
    if (!is_admin_portal_role($userRole)) {
        assistant_json([
            'success'  => false,
            'message'  => 'This assistant is only available in the admin workspace.',
            'response' => 'Please sign in with an admin, pharmacist, or staff account to use this assistant.',
        ], 403);
    }

    $query = enrich_followup_query($query, $history, $topic);
    $q = strtolower($query);

    if (is_supplier_private_data_request($q)) {
        assistant_json(supplier_private_data_response());
    }

    // Greetings
    if (preg_match('/^(hi|hello|hey|good\s+(morning|afternoon|evening))\b/', $q)) {
        assistant_json(response_pack('greeting', 'Hello! I can check live inventory records, summarize operations, explain system steps, and point you to the right page. What do you need?', [
            'suggestions' => default_suggestions($userRole)
        ]));
    }

    // Help
    if (preg_match('/\b(help|what can you do|capabilities|commands)\b/', $q)) {
        assistant_json(response_pack('help', 'I can answer questions about medicines, stock levels, expiry dates, suppliers, orders, sales, purchases, transactions, notifications, and user activity. I also guide you through system workflows and give reorder recommendations.', [
            'actions'     => [['label' => 'Open Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2']],
            'suggestions' => default_suggestions($userRole),
        ]));
    }

    $fiveWOneH = five_w_one_h_response($conn, $query, $q, $userRole);
    if ($fiveWOneH) {
        assistant_json($fiveWOneH);
    }

    // Workflow guide
    if (preg_match('/\b(how do i|how to|where can i|guide|steps|tutorial|navigate)\b/', $q)) {
        assistant_json(workflow_response($q));
    }

    // Low stock / reorder
    if (preg_match('/\b(low stock|critical|shortage|reorder|restock|running low|what should i order|what should i reorder)\b/', $q)) {
        $pack = low_stock($conn);
        // Try to enhance with AI
        $snap = build_db_snapshot($conn, $userRole);
        $ai   = ai_enhanced_response($query, $history, $snap, $userRole);
        if ($ai) { $pack['response'] = $ai['text']; $pack['ai_enhanced'] = true; }
        assistant_json($pack);
    }

    // Expiry
    if (preg_match('/\b(expir|expired|expiry|dispose|disposal)\b/', $q)) {
        assistant_json(expiry($conn, $q));
    }

    // Suppliers
    if (preg_match('/\b(supplier|supplies|provides|vendor|who supplies)\b/', $q)) {
        assistant_json(suppliers($conn, $q));
    }

    // Orders
    if (preg_match('/\b(order|orders|purchase|ordered|pending order)\b/', $q)) {
        assistant_json(orders($conn, $q));
    }

    // Sales
    if (preg_match('/\b(sale|sales|invoice|revenue|sold|profit)\b/', $q)) {
        assistant_json(sales_summary($conn, $q));
    }

    // Transactions
    if (preg_match('/\b(transaction|transactions|movement|history|stock drop|added|removed|dispensed)\b/', $q)) {
        assistant_json(transactions($conn, $q));
    }

    // Purchases
    if (preg_match('/\b(purchase|purchases|rx)\b/', $q)) {
        assistant_json(purchases($conn, $q));
    }

    // Notifications
    if (preg_match('/\b(notification|alert|alerts|unread)\b/', $q)) {
        assistant_json(notifications($conn));
    }

    // User activity
    if (preg_match('/\b(user activity|activity log|login|logout|users|staff|pharmacist|admin)\b/', $q)) {
        assistant_json(user_activity($conn, $userRole));
    }

    // Overview / dashboard
    if (preg_match('/\b(overview|dashboard|system summary|operation summary|operations|priority|status|risk|risks|urgent|problem|problems|issue|issues)\b/', $q)) {
        $pack = overview($conn, $userRole);
        $snap = build_db_snapshot($conn, $userRole);
        $ai   = ai_enhanced_response($query, $history, $snap, $userRole);
        if ($ai) { $pack['response'] = $ai['text']; $pack['ai_enhanced'] = true; }
        assistant_json($pack);
    }

    // Medicine lookup by name or barcode
    if (preg_match('/\b(medicine|medicines|stock|barcode|price|cost|quantity|available|left|tablet|capsule|syrup|injection|mg|mcg|iu)\b/', $q) || find_medicine($conn, $q)) {
        $pack = medicine_lookup($conn, $query);
        // For medicine lookups, enhance with AI for richer context
        $snap = build_db_snapshot($conn, $userRole);
        $ai   = ai_enhanced_response($query, $history, $snap, $userRole);
        if ($ai) { $pack['response'] = $ai['text']; $pack['ai_enhanced'] = true; }
        assistant_json($pack);
    }

    // Thanks
    if (preg_match('/\b(thank|thanks)\b/', $q)) {
        assistant_json(response_pack('thanks', 'You\'re welcome! Let me know if you need anything else about the inventory.'));
    }

    // Fallback: try AI with full context
    $snap = build_db_snapshot($conn, $userRole);
    $ai   = ai_enhanced_response($query, $history, $snap, $userRole);
    if ($ai) {
        assistant_json(response_pack('ai_answer', $ai['text'], [
            'ai_enhanced' => true,
            'confidence'  => 'high',
            'actions'     => [['label' => 'Open Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2']],
            'suggestions' => default_suggestions($userRole),
        ]));
    }

    assistant_json(response_pack('fallback', 'I can help with inventory, sales, suppliers, orders, purchases, reports, and workflow steps. Try asking about specific medicines, stock levels, or system features.', [
        'confidence'  => 'low',
        'suggestions' => default_suggestions($userRole),
    ]));

} catch (Throwable $e) {
    error_log('Chatbot API error: '.$e->getMessage());
    assistant_json(response_pack('fallback', 'I could not find a clear answer for that question. Try one of the suggestions below, or rephrase using words like stock, orders, suppliers, sales, or expiry.', [
        'confidence'  => 'medium',
        'suggestions' => default_suggestions($userRole ?? 'guest'),
        'actions'     => [
            ['label' => 'Open Dashboard', 'href' => 'dashboard.php', 'icon' => 'bi-speedometer2'],
            ['label' => 'Open Medicines', 'href' => 'medicine.php',  'icon' => 'bi-capsule'],
        ],
    ]));
} finally {
    if (isset($conn) && $conn) $conn->close();
}
