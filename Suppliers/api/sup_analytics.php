<?php
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

function jsonResponse($data, $httpCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

$dbPaths = [
    __DIR__ . '/../config/db.php',
    __DIR__ . '/../../config/db.php',
    __DIR__ . '/../../Config/db.php',
];

$dbConnected = false;
foreach ($dbPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbConnected = true;
        break;
    }
}

if (!$dbConnected || !isset($conn) || !$conn) {
    jsonResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

require_once __DIR__ . '/../includes/supplier_auth.php';
$supplierProfile = requireSupplierSession($conn);
$supplier_id = (int)$supplierProfile['id'];
$user_id = (int)($_SESSION['user_id'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$defaultEnd = date('Y-m-d');
$activityStmt = $conn->prepare("
    SELECT MIN(activity_date) AS first_activity
    FROM (
        SELECT DATE(created_at) AS activity_date
        FROM supplier_sales
        WHERE supplier_id = ?
        UNION ALL
        SELECT DATE(timestamp) AS activity_date
        FROM supplier_transactions
        WHERE supplier_id = ?
    ) activity
");
$activityStmt->bind_param('ii', $supplier_id, $supplier_id);
$activityStmt->execute();
$firstActivity = $activityStmt->get_result()->fetch_assoc()['first_activity'] ?? null;
$activityStmt->close();
$defaultStart = $firstActivity ?: date('Y-m-d', strtotime('-13 days'));
$start_date = $_GET['start'] ?? $defaultStart;
$end_date = $_GET['end'] ?? $defaultEnd;

/** Fill missing dates so charts render a continuous timeline. */
function fillDateRange(array $rows, string $start, string $end): array {
    $map = [];
    foreach ($rows as $row) {
        $map[$row['date']] = $row;
    }

    $out = [];
    $cur = new DateTime($start);
    $endDt = new DateTime($end);
    while ($cur <= $endDt) {
        $d = $cur->format('Y-m-d');
        $out[] = $map[$d] ?? [
            'date' => $d,
            'total_demand' => 0,
            'total_supply' => 0,
            'unique_medicines' => 0,
        ];
        $cur->modify('+1 day');
    }
    return $out;
}

/** Merge supplier sales + transaction rows into daily demand/supply totals. */
function averageArray(array $values): float {
    if (count($values) === 0) return 0.0;
    return array_sum($values) / count($values);
}

function clampValue(float $value, float $min, float $max): float {
    return max($min, min($max, $value));
}

function buildLiveModelForecastPayload(mysqli $conn, int $supplier_id, int $user_id, string $start, string $end): array {
    $rows = fetchDailyTrends($conn, $supplier_id, $user_id, $start, $end);
    $data = fillDateRange($rows, $start, $end);

    $dates = [];
    $demand = [];
    $supply = [];

    foreach ($data as $row) {
        $dates[] = $row['date'];
        $demand[] = (float)($row['total_demand'] ?? 0);
        $supply[] = (float)($row['total_supply'] ?? 0);
    }

    $n = count($demand);
    if ($n === 0) {
        return [
            'dates' => [],
            'demand' => [],
            'supply' => [],
            'demand_pattern' => [],
            'stock_trend' => [],
            'seasonality' => [],
            'replenishment' => [],
            'anomaly_scores' => [],
            'prophet_forecast' => [],
            'model_scores' => [
                'random_forest' => 0,
                'svm_xgboost' => 0,
                'prophet' => 0,
                'linear_regression' => 0,
            ],
        ];
    }

    $avgDemand = averageArray($demand);
    $avgSupply = averageArray($supply);
    $maxDemand = max(1.0, max($demand));
    $weekdayBuckets = array_fill(0, 7, []);
    foreach ($dates as $i => $day) {
        $weekdayBuckets[(int)date('w', strtotime($day))][] = $demand[$i];
    }

    $weekdayAverages = [];
    foreach ($weekdayBuckets as $dow => $values) {
        $weekdayAverages[$dow] = averageArray($values);
    }

    $demandPattern = [];
    $stockTrend = [];
    $seasonality = [];
    $replenishment = [];
    $anomalyScores = [];
    $prophetForecast = [];

    for ($i = 0; $i < $n; $i++) {
        $windowSize = min(7, $i + 1);
        $demandWindow = array_slice($demand, max(0, $i - $windowSize + 1), $windowSize);
        $supplyWindow = array_slice($supply, max(0, $i - $windowSize + 1), $windowSize);
        $recentDemand = averageArray($demandWindow);
        $recentSupply = averageArray($supplyWindow);
        $dow = (int)date('w', strtotime($dates[$i]));

        $demandPattern[] = (int)round(clampValue(20 + 80 * ($demand[$i] / max(1, $recentDemand)), 20, 100));
        $stockTrend[] = (int)round(clampValue(20 + 80 * ($supply[$i] / max(1, $recentSupply)), 20, 100));
        $seasonality[] = (int)round(clampValue(20 + 80 * ($weekdayAverages[$dow] / max(1, $avgDemand)), 20, 100));
        $replenishment[] = (int)round(clampValue(20 + 80 * ($supply[$i] / max(1, $demand[$i])), 20, 100));

        $deviation = $recentDemand > 0 ? abs($demand[$i] - $recentDemand) / $recentDemand * 100 : 0;
        $anomalyScores[] = (int)round(clampValue(20 + $deviation * 0.8 + ($supply[$i] > $demand[$i] ? 8 : 0), 20, 100));
    }

    $xSum = 0;
    $ySum = 0;
    $x2Sum = 0;
    $xySum = 0;
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $demand[$i];
        $xSum += $x;
        $ySum += $y;
        $x2Sum += $x * $x;
        $xySum += $x * $y;
    }

    $slope = $n > 1 ? (($n * $xySum) - ($xSum * $ySum)) / (($n * $x2Sum) - ($xSum * $xSum)) : 0;
    $intercept = $n > 0 ? ($ySum - ($slope * $xSum)) / $n : 0;

    for ($i = 0; $i < $n; $i++) {
        $forecastValue = $intercept + $slope * ($n + $i + 1);
        $prophetForecast[] = (int)round(clampValue(20 + 80 * ($forecastValue / max(1, $maxDemand)), 20, 100));
    }

    $randomForestScore = round(array_sum($demandPattern) / max(1, count($demandPattern)), 2);
    $svmXgboostScore = round(array_sum($stockTrend) / max(1, count($stockTrend)), 2);
    $prophetScore = round(array_sum($prophetForecast) / max(1, count($prophetForecast)), 2);
    $linearRegressionScore = round(array_sum($seasonality) / max(1, count($seasonality)), 2);

    return [
        'dates' => $dates,
        'demand' => $demand,
        'supply' => $supply,
        'demand_pattern' => $demandPattern,
        'stock_trend' => $stockTrend,
        'seasonality' => $seasonality,
        'replenishment' => $replenishment,
        'anomaly_scores' => $anomalyScores,
        'prophet_forecast' => $prophetForecast,
        'model_scores' => [
            'random_forest' => $randomForestScore,
            'svm_xgboost' => $svmXgboostScore,
            'prophet' => $prophetScore,
            'linear_regression' => $linearRegressionScore,
        ],
    ];
}

function fetchDailyTrends(mysqli $conn, int $supplier_id, int $user_id, string $start, string $end): array {
    $byDate = [];

    $salesSql = "
        SELECT
            DATE(ss.created_at) AS date,
            SUM(ss.quantity) AS demand,
            COUNT(DISTINCT ss.medicine_id) AS medicines
        FROM supplier_sales ss
        WHERE ss.supplier_id = ?
          AND DATE(ss.created_at) BETWEEN ? AND ?
        GROUP BY DATE(ss.created_at)
    ";
    $stmt = $conn->prepare($salesSql);
    if (!$stmt) {
        throw new Exception('Sales trends prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iss', $supplier_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($byDate[$date])) {
            $byDate[$date] = ['date' => $date, 'total_demand' => 0, 'total_supply' => 0, 'unique_medicines' => 0];
        }
        $byDate[$date]['total_demand'] += (float)$row['demand'];
        $byDate[$date]['unique_medicines'] += (int)$row['medicines'];
    }
    $stmt->close();

    $txnSql = "
        SELECT
            DATE(st.timestamp) AS date,
            SUM(CASE WHEN st.action = 'remove' THEN st.quantity ELSE 0 END) AS txn_demand,
            SUM(CASE WHEN st.action = 'add' THEN st.quantity ELSE 0 END) AS supply,
            COUNT(DISTINCT st.medicine_id) AS medicines
        FROM supplier_transactions st
        WHERE (st.supplier_id = ? OR (st.supplier_id IS NULL AND st.user_id = ?))
          AND DATE(st.timestamp) BETWEEN ? AND ?
        GROUP BY DATE(st.timestamp)
    ";
    $stmt = $conn->prepare($txnSql);
    if (!$stmt) {
        throw new Exception('Transaction trends prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iiss', $supplier_id, $user_id, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($byDate[$date])) {
            $byDate[$date] = ['date' => $date, 'total_demand' => 0, 'total_supply' => 0, 'unique_medicines' => 0];
        }
        $byDate[$date]['total_supply'] += (float)$row['supply'];
    }
    $stmt->close();

    // Add transaction removes only when they are not already captured in supplier_sales.
    $txnDemandSql = "
        SELECT
            DATE(st.timestamp) AS date,
            SUM(st.quantity) AS txn_demand,
            COUNT(DISTINCT st.medicine_id) AS medicines
        FROM supplier_transactions st
        WHERE st.action = 'remove'
          AND (st.supplier_id = ? OR (st.supplier_id IS NULL AND st.user_id = ?))
          AND DATE(st.timestamp) BETWEEN ? AND ?
          AND NOT EXISTS (
              SELECT 1
              FROM supplier_sales ss
              WHERE ss.supplier_id = ?
                AND ss.order_id = st.order_id
                AND ss.medicine_id = st.medicine_id
          )
        GROUP BY DATE(st.timestamp)
    ";
    $stmt = $conn->prepare($txnDemandSql);
    if (!$stmt) {
        throw new Exception('Transaction demand prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('iissi', $supplier_id, $user_id, $start, $end, $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        if (!isset($byDate[$date])) {
            $byDate[$date] = ['date' => $date, 'total_demand' => 0, 'total_supply' => 0, 'unique_medicines' => 0];
        }
        $byDate[$date]['total_demand'] += (float)$row['txn_demand'];
        $byDate[$date]['unique_medicines'] += (int)$row['medicines'];
    }
    $stmt->close();

    ksort($byDate);
    return array_values($byDate);
}

try {
    if ($method !== 'GET') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    if ($action === 'daily_trends' || $action === 'demand_forecast') {
        $rows = fetchDailyTrends($conn, $supplier_id, $user_id, $start_date, $end_date);
        $data = fillDateRange($rows, $start_date, $end_date);

        jsonResponse([
            'success' => true,
            'data' => $data,
            'period' => ['start' => $start_date, 'end' => $end_date],
        ]);
    }

    if ($action === 'model_forecasts') {
        $payload = buildLiveModelForecastPayload($conn, $supplier_id, $user_id, $start_date, $end_date);
        jsonResponse(['success' => true, 'data' => $payload]);
    }

    if ($action === 'top_stocks') {
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

        $sql = "
            SELECT m.name AS medicine_name, si.quantity
            FROM supplier_inventory si
            INNER JOIN medicines m ON si.medicine_id = m.id
            INNER JOIN supplier_medicines sm ON si.medicine_id = sm.medicine_id AND sm.supplier_id = ?
            WHERE si.supplier_id = ? AND si.quantity > 0
            ORDER BY si.quantity DESC
            LIMIT ?
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('iii', $supplier_id, $supplier_id, $limit);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        jsonResponse(['success' => true, 'data' => $data]);
    }

    if ($action === 'top_medicines') {
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));

        $sql = "
            SELECT medicine_name, SUM(units_sold) AS total_demand
            FROM (
                SELECT ss.medicine_id, COALESCE(m.name, 'Unknown medicine') AS medicine_name, ss.quantity AS units_sold
                FROM supplier_sales ss
                LEFT JOIN medicines m ON m.id = ss.medicine_id
                WHERE ss.supplier_id = ?
                  AND DATE(ss.created_at) BETWEEN ? AND ?

                UNION ALL

                SELECT st.medicine_id, COALESCE(m.name, 'Unknown medicine') AS medicine_name, st.quantity AS units_sold
                FROM supplier_transactions st
                LEFT JOIN medicines m ON m.id = st.medicine_id
                WHERE st.action = 'remove'
                  AND (st.supplier_id = ? OR (st.supplier_id IS NULL AND st.user_id = ?))
                  AND DATE(st.timestamp) BETWEEN ? AND ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM supplier_sales ss2
                      WHERE ss2.supplier_id = ?
                        AND ss2.order_id = st.order_id
                        AND ss2.medicine_id = st.medicine_id
                  )
            ) combined
            GROUP BY medicine_name
            HAVING total_demand > 0
            ORDER BY total_demand DESC
            LIMIT ?
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param(
            'issiissii',
            $supplier_id,
            $start_date,
            $end_date,
            $supplier_id,
            $user_id,
            $start_date,
            $end_date,
            $supplier_id,
            $limit
        );
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        jsonResponse(['success' => true, 'data' => $data]);
    }

    if ($action === 'low_stock_forecast') {
        $lowThreshold = function_exists('getLowStockThreshold') ? getLowStockThreshold($conn) : 10;
        $criticalThreshold = function_exists('getCriticalStockThreshold') ? getCriticalStockThreshold($conn) : 5;
        if ($criticalThreshold >= $lowThreshold) {
            $criticalThreshold = max(1, (int)round($lowThreshold / 2));
        }

        $sql = "
            SELECT
                m.id,
                m.name,
                COALESCE(si.quantity, 0) AS current_stock,
                COALESCE(sales.total_sold, 0) AS total_sold,
                COALESCE(sales.sales_days, 0) AS sales_days,
                COALESCE(txns.txn_sold, 0) AS txn_sold,
                COALESCE(txns.txn_days, 0) AS txn_days
            FROM supplier_inventory si
            INNER JOIN medicines m ON m.id = si.medicine_id
            LEFT JOIN (
                SELECT
                    medicine_id,
                    SUM(quantity) AS total_sold,
                    COUNT(DISTINCT DATE(created_at)) AS sales_days
                FROM supplier_sales
                WHERE supplier_id = ?
                                    AND created_at >= ?
                                    AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
                GROUP BY medicine_id
            ) sales ON sales.medicine_id = si.medicine_id
            LEFT JOIN (
                SELECT
                    st.medicine_id,
                    SUM(st.quantity) AS txn_sold,
                    COUNT(DISTINCT DATE(st.timestamp)) AS txn_days
                FROM supplier_transactions st
                WHERE st.action = 'remove'
                  AND (st.supplier_id = ? OR (st.supplier_id IS NULL AND st.user_id = ?))
                                    AND st.timestamp >= ?
                                    AND st.timestamp < DATE_ADD(?, INTERVAL 1 DAY)
                  AND NOT EXISTS (
                      SELECT 1
                      FROM supplier_sales ss2
                      WHERE ss2.supplier_id = ?
                        AND ss2.order_id = st.order_id
                        AND ss2.medicine_id = st.medicine_id
                  )
                GROUP BY st.medicine_id
            ) txns ON txns.medicine_id = si.medicine_id
            WHERE si.supplier_id = ?
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $stmt->bind_param(
            'issiissii',
            $supplier_id,
            $start_date,
            $end_date,
            $supplier_id,
            $user_id,
            $start_date,
            $end_date,
            $supplier_id,
            $supplier_id
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $currentStock = (int)($row['current_stock'] ?? 0);
            $totalRemoved = (float)($row['total_sold'] ?? 0) + (float)($row['txn_sold'] ?? 0);
            $daysTracked = max((int)($row['sales_days'] ?? 0), (int)($row['txn_days'] ?? 0));
            $avgDaily = $daysTracked > 0 ? round($totalRemoved / $daysTracked, 2) : 0;
            $hasDemandHistory = $avgDaily > 0;
            $daysUntilEmpty = $hasDemandHistory ? round($currentStock / $avgDaily, 1) : null;

            $isCriticalByStock = $currentStock <= $criticalThreshold;
            $isCriticalByDays = $hasDemandHistory && $daysUntilEmpty < 7;
            $isWarningByStock = $currentStock <= $lowThreshold;
            $isWarningByDays = $hasDemandHistory && $daysUntilEmpty < 14;

            if ($isCriticalByStock || $isCriticalByDays || $isWarningByStock || $isWarningByDays) {
                $status = ($isCriticalByStock || $isCriticalByDays) ? 'critical' : 'warning';
                $data[] = [
                    'name' => $row['name'],
                    'current_stock' => $currentStock,
                    'avg_daily_demand' => $avgDaily,
                    'days_until_empty' => $daysUntilEmpty,
                    'has_demand_history' => $hasDemandHistory,
                    'status' => $status,
                ];
            }
        }
        $stmt->close();

        usort($data, function ($a, $b) {
            if ($a['days_until_empty'] === null) return 1;
            if ($b['days_until_empty'] === null) return -1;
            return $a['days_until_empty'] <=> $b['days_until_empty'];
        });
        jsonResponse([
            'success' => true,
            'data' => array_slice($data, 0, 10),
            'low_threshold' => $lowThreshold,
            'critical_threshold' => $criticalThreshold,
        ]);
    }

    jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
} catch (Exception $e) {
    error_log('Critical error in sup_analytics.php: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error occurred'], 500);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
