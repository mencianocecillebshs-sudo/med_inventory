<?php
/**
 * Admin API - Analytics
 *
 * Purpose: Provide JSON endpoints used by the Admin analytics UI.
 * Actions supported (via `action` query param):
 *  - demand_forecast: daily demand/supply/unique_medicines over a date range
 *  - daily_trends: same as demand_forecast (kept for compatibility)
 *  - top_medicines: top N medicines by removal quantity
 *  - low_stock_forecast: estimate days until stock depletes for all medicines
 *
 * Inputs (query params):
 *  - start, end : optional ISO dates to limit the period (defaults calculated)
 *  - action      : which report to run (defaults to 'demand_forecast')
 *  - limit       : for `top_medicines` (defaults to 10)
 *  - threshold   : optional override for low_stock_forecast (low stock cutoff, in units)
 *  - critical_threshold : optional override for the critical stock cutoff, in units
 *
 * Security/assumptions:
 *  - Requires an authenticated session (`$_SESSION['user_id']`).
 *  - Returns JSON with `success` or `error` keys and appropriate HTTP codes.
 *  - Uses prepared statements where user input enters SQL to avoid injection.
 *
 * Threshold resolution (low_stock_forecast):
 *  The Settings page (Inventory Management section) marks `low_stock_threshold`
 *  and `critical_stock_threshold` as "System-Wide" — they are written to a
 *  global settings row (user_id IS NULL) AND a per-user row on every save.
 *  To honor that system-wide promise, this endpoint now reads the GLOBAL row
 *  first, falling back to the per-user row, then a hardcoded default. This
 *  matches what the Settings UI tells the admin will happen and means the
 *  Forecasting page reflects the configured values without needing the page
 *  to explicitly pass a `threshold` query param.
 */

header('Content-Type: application/json');
session_start();
require_once '../config/db.php';

// Authentication: ensure the API is called by an authenticated user.
// If not authenticated, respond with HTTP 401 and a JSON error.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Suppress display of PHP errors in responses; still log/report them.
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Basic request parsing: HTTP method and `action` selector.
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'demand_forecast';

/* -------------------------------------------------------------
    DATE RANGE DETERMINATION

    Determine the start/end date for queries in this priority order:
    1) `start` / `end` query params if provided
    2) min/max timestamp from `transactions` table (if any)
    3) fallback to last 30 days (default)

    All dates are used as strings in prepared statements and expected
    to be in `Y-m-d` format. No additional server-side normalization
    is performed here; callers should provide valid dates.
    ------------------------------------------------------------- */
$dateRangeQuery = "SELECT
                            COALESCE(MIN(DATE(timestamp)), CURDATE()) AS min_date,
                            COALESCE(MAX(DATE(timestamp)), CURDATE()) AS max_date
                         FROM transactions";
$dateResult = $conn->query($dateRangeQuery);
$dateRange  = $dateResult->fetch_assoc();

$defaultStart = date('Y-m-d', strtotime('-13 days'));
$defaultEnd   = date('Y-m-d');

// Use provided query params if present, otherwise fall back.
$start_date = $_GET['start'] ?? ($dateRange['min_date'] ?? $defaultStart);
$end_date   = $_GET['end']   ?? ($dateRange['max_date'] ?? $defaultEnd);

/* -------------------------------------------------------------
    Helper: resolve a numeric setting value with priority:
    1) explicit query param (if provided & valid)
    2) GLOBAL settings row (user_id IS NULL) - "System-Wide" values
    3) per-user settings row (legacy fallback, in case no global row exists yet)
    4) hardcoded default
    ------------------------------------------------------------- */
function resolveThreshold($conn, $queryParamValue, $settingKey, $userId, $default) {
    // 1. Explicit query param wins if present and valid
    if ($queryParamValue !== null && is_numeric($queryParamValue) && (int)$queryParamValue > 0) {
        return (int)$queryParamValue;
    }

    // 2. Global system-wide row (user_id IS NULL) - this is what the
    //    Settings page describes as applying to "all pages and roles".
    $stmt = $conn->prepare(
        "SELECT value FROM settings
         WHERE setting_key = ? AND user_id IS NULL
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('s', $settingKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && is_numeric($row['value']) && (int)$row['value'] > 0) {
            return (int)$row['value'];
        }
    }

    // 3. Per-user row (legacy fallback / before any global row was ever saved)
    $stmt = $conn->prepare(
        "SELECT value FROM settings
         WHERE setting_key = ? AND user_id = ?
         LIMIT 1"
    );
    if ($stmt) {
        $stmt->bind_param('si', $settingKey, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && is_numeric($row['value']) && (int)$row['value'] > 0) {
            return (int)$row['value'];
        }
    }

    // 4. Hardcoded default
    return $default;
}

function clamp($value, $min, $max) {
    return max($min, min($max, $value));
}

function averageArray($values) {
    if (!is_array($values) || count($values) === 0) {
        return 0;
    }

    return array_sum($values) / count($values);
}

function buildLiveModelForecastPayload($conn, $start_date, $end_date) {
    $sql = "SELECT
                DATE(t.timestamp) AS date,
                SUM(CASE WHEN t.action='remove' THEN t.quantity ELSE 0 END) AS total_demand,
                SUM(CASE WHEN t.action='add'    THEN t.quantity ELSE 0 END) AS total_supply
            FROM transactions t
                        WHERE t.timestamp >= ?
                            AND t.timestamp < DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY DATE(t.timestamp)
            ORDER BY date";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $period = [];
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $cursor = clone $start;
    while ($cursor <= $end) {
        $period[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }

    $lookup = [];
    foreach ($rows as $row) {
        $lookup[$row['date']] = $row;
    }

    $dates = [];
    $demand = [];
    $supply = [];
    foreach ($period as $day) {
        $row = $lookup[$day] ?? ['total_demand' => 0, 'total_supply' => 0];
        $dates[] = $day;
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
    $maxDemand = max(1, max($demand));

    $weekdayBuckets = array_fill(0, 7, []);
    foreach ($dates as $index => $day) {
        $weekdayBuckets[(int)date('w', strtotime($day))][] = $demand[$index];
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

        $demandPattern[] = (int)round(clamp(20 + 80 * ($demand[$i] / max(1, $recentDemand)), 20, 100));
        $stockTrend[] = (int)round(clamp(20 + 80 * ($supply[$i] / max(1, $recentSupply)), 20, 100));
        $seasonality[] = (int)round(clamp(20 + 80 * ($weekdayAverages[$dow] / max(1, $avgDemand)), 20, 100));
        $replenishment[] = (int)round(clamp(20 + 80 * ($supply[$i] / max(1, $demand[$i])), 20, 100));

        $deviation = $recentDemand > 0 ? abs($demand[$i] - $recentDemand) / $recentDemand * 100 : 0;
        $anomalyScores[] = (int)round(clamp(20 + $deviation * 0.8 + ($supply[$i] > $demand[$i] ? 8 : 0), 20, 100));
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
        $prophetForecast[] = (int)round(clamp(20 + 80 * ($forecastValue / max(1, $maxDemand)), 20, 100));
    }

    $trainCount = max(4, (int)floor($n * 0.7));
    $trainValues = array_slice($demand, 0, $trainCount);
    $testValues = array_slice($demand, $trainCount);
    if (count($testValues) > 0) {
        $linearResiduals = [];
        $slopeTrain = 0;
        $interceptTrain = 0;
        $trainN = count($trainValues);
        if ($trainN > 1) {
            $xSumTrain = 0;
            $ySumTrain = 0;
            $x2SumTrain = 0;
            $xySumTrain = 0;
            for ($i = 0; $i < $trainN; $i++) {
                $x = $i + 1;
                $y = $trainValues[$i];
                $xSumTrain += $x;
                $ySumTrain += $y;
                $x2SumTrain += $x * $x;
                $xySumTrain += $x * $y;
            }
            $slopeTrain = (($trainN * $xySumTrain) - ($xSumTrain * $ySumTrain)) / (($trainN * $x2SumTrain) - ($xSumTrain * $xSumTrain));
            $interceptTrain = ($ySumTrain - ($slopeTrain * $xSumTrain)) / $trainN;
        }

        for ($i = 0; $i < count($testValues); $i++) {
            $pred = $interceptTrain + $slopeTrain * ($trainN + $i + 1);
            $linearResiduals[] = $pred - $testValues[$i];
        }

        $rmse = count($linearResiduals) > 0 ? sqrt(array_sum(array_map(fn($v) => $v * $v, $linearResiduals)) / count($linearResiduals)) : 0;
        $linearRegressionScore = (int)round(max(20, min(100, 100 - ($rmse / max(1, $avgDemand) * 100))));
    } else {
        $linearRegressionScore = 50;
    }

    $rfResiduals = [];
    $svmResiduals = [];
    $prophetResiduals = [];

    for ($i = 0; $i < count($testValues); $i++) {
        $baseValue = $trainValues[$trainCount - 1] ?? $avgDemand;
        $recentBase = $trainValues[max(0, $trainCount - 2)] ?? $avgDemand;
        $rfPred = ($baseValue + $recentBase + $testValues[max(0, $i - 1)] + $avgDemand) / 4;
        $svmPred = ($baseValue * 0.6) + ($recentBase * 0.4);
        $prophetPred = $baseValue + ($demandPattern[max(0, $trainCount + $i - 1)] - 50) / 10;
        $rfResiduals[] = $rfPred - $testValues[$i];
        $svmResiduals[] = $svmPred - $testValues[$i];
        $prophetResiduals[] = $prophetPred - $testValues[$i];
    }

    $randomForestScore = count($rfResiduals) > 0 ? (int)round(max(20, min(100, 100 - (sqrt(array_sum(array_map(fn($v) => $v * $v, $rfResiduals)) / count($rfResiduals)) / max(1, $avgDemand) * 100)))) : 50;
    $svmXgboostScore = count($svmResiduals) > 0 ? (int)round(max(20, min(100, 100 - (sqrt(array_sum(array_map(fn($v) => $v * $v, $svmResiduals)) / count($svmResiduals)) / max(1, $avgDemand) * 100)))) : 50;
    $prophetScore = count($prophetResiduals) > 0 ? (int)round(max(20, min(100, 100 - (sqrt(array_sum(array_map(fn($v) => $v * $v, $prophetResiduals)) / count($prophetResiduals)) / max(1, $avgDemand) * 100)))) : 50;

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

/* ------------------------------------------------------------- */
switch ($method) {
    case 'GET':
                /*
                 * GET actions: different analytics reports. Each case returns
                 * JSON and follows the convention: `['success'=>true,'data'=>...]`.
                 */
                switch ($action) {
            /* ----------------------------------------------------- */
            // Daily demand/supply trends over the requested period.
            // Uses prepared statements to bind date range values.
            case 'demand_forecast':
            case 'daily_trends':
                $sql = "SELECT
                            DATE(t.timestamp) AS date,
                            SUM(CASE WHEN t.action='remove' THEN t.quantity ELSE 0 END) AS total_demand,
                            SUM(CASE WHEN t.action='add'    THEN t.quantity ELSE 0 END) AS total_supply,
                            COUNT(DISTINCT t.medicine_id) AS unique_medicines
                        FROM transactions t
                                                WHERE t.timestamp >= ?
                                                    AND t.timestamp < DATE_ADD(?, INTERVAL 1 DAY)
                        GROUP BY DATE(t.timestamp)
                        ORDER BY date";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $res = $stmt->get_result();
                $data = $res->fetch_all(MYSQLI_ASSOC);
                echo json_encode([
                    'success' => true,
                    'data' => $data,
                    'period' => ['start' => $start_date, 'end' => $end_date]
                ]);
                break;

            /* ----------------------------------------------------- */
            // Live forecast models derived from the current transaction history.
            case 'model_forecasts':
                $payload = buildLiveModelForecastPayload($conn, $start_date, $end_date);
                echo json_encode(['success' => true, 'data' => $payload]);
                break;

            /* ----------------------------------------------------- */
            // Top medicines by quantity removed (demand) in the period.
            // `limit` is taken from query params; ensure it's an integer.
            case 'top_medicines':
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $sql = "SELECT
                            m.name AS medicine_name,
                            m.id AS medicine_id,
                            SUM(CASE WHEN t.action='remove' THEN t.quantity ELSE 0 END) AS total_demand
                        FROM transactions t
                        JOIN medicines m ON t.medicine_id = m.id
                                                WHERE t.timestamp >= ?
                                                    AND t.timestamp < DATE_ADD(?, INTERVAL 1 DAY)
                          AND t.action = 'remove'
                        GROUP BY m.id, m.name
                        HAVING total_demand > 0
                        ORDER BY total_demand DESC
                        LIMIT ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssi', $start_date, $end_date, $limit);
                $stmt->execute();
                $res = $stmt->get_result();
                $data = $res->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['success' => true, 'data' => $data]);
                break;

            /* ----------------------------------------------------- */
            // Low stock forecast: estimate depletion days per medicine.
            // Approach:
            //  - LEFT JOIN all medicines to recent removal transactions
            //  - compute average daily demand over the selected date range
            //  - compute `days_until_empty` = stock / avg_daily_demand
            //  - keep only medicines that are low/critical on stock OR
            //    projected to run out soon
            case 'low_stock_forecast':
                /*  -------------------------------------------------
                    Threshold resolution (see resolveThreshold() above
                    for the full priority order). Two distinct, separately
                    configurable thresholds are used here, both sourced
                    from the Settings page's "Inventory Management" section:

                    - $lowThreshold      <- low_stock_threshold (units)
                                            "alert when stock falls below this level"
                    - $criticalThreshold <- critical_stock_threshold (units)
                                            "urgent alert when stock is critically low"

                    These are UNIT-based cutoffs on current_stock, and are
                    intentionally kept separate from the day-based depletion
                    forecast (days_until_empty), which is a different signal:
                    a medicine can have plenty of units left but still be
                    flagged because it's being consumed fast, or have very
                    few units left but barely be used at all. A medicine is
                    included in the results if EITHER signal says "low".
                    ------------------------------------------------- */
                $userId = (int)$_SESSION['user_id'];

                $lowThreshold = resolveThreshold(
                    $conn,
                    $_GET['threshold'] ?? null,
                    'low_stock_threshold',
                    $userId,
                    10 // hardcoded fallback default
                );

                $criticalThreshold = resolveThreshold(
                    $conn,
                    $_GET['critical_threshold'] ?? null,
                    'critical_stock_threshold',
                    $userId,
                    5 // hardcoded fallback default
                );

                // Guard against misconfiguration: critical should never exceed low.
                // (The Settings page already validates this on save, but defend
                // here too in case of stale/global rows from before validation existed.)
                if ($criticalThreshold >= $lowThreshold) {
                    $criticalThreshold = max(1, (int)round($lowThreshold / 2));
                }

                /*  -------------------------------------------------
                    Implementation notes:
                    - `days_tracked` is the count of distinct days with removals
                      (used to compute average daily removal). If zero, avg=0.
                    - If avg_daily == 0 we treat days_until_empty as null
                      (no depletion estimate available because demand is zero).
                    - Status levels now combine BOTH signals:
                        critical: current_stock <= criticalThreshold
                                  OR days_until_empty < 7
                        warning:  current_stock <= lowThreshold
                                  OR days_until_empty < 14
                        normal:   otherwise
                    - A medicine is included in the result set if it is
                      'critical' or 'warning' by either signal.
                    - Results are sorted by urgency (days_until_empty ascending)
                      and limited to 10 items for front-end display.
                    ------------------------------------------------- */
                $sql = "
                    SELECT
                        m.id,
                        m.name,
                        m.quantity AS current_stock,
                        COALESCE(SUM(CASE WHEN t.action='remove' THEN t.quantity ELSE 0 END), 0) AS total_removed,
                        COUNT(DISTINCT DATE(t.timestamp)) AS days_tracked
                    FROM medicines m
                    LEFT JOIN transactions t
                        ON m.id = t.medicine_id
                       AND t.action = 'remove'
                       AND t.timestamp >= ?
                       AND t.timestamp < DATE_ADD(?, INTERVAL 1 DAY)
                    GROUP BY m.id, m.name, m.quantity
                ";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("Low stock query prepare error: " . $conn->error);
                    echo json_encode(['success' => false, 'error' => 'Database query failed']);
                    break;
                }
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();

                if (!$result) {
                    error_log("Low stock query error: " . $conn->error);
                    echo json_encode(['success' => false, 'error' => 'Database query failed']);
                    $stmt->close();
                    break;
                }

                $data = [];
                $lookbackDays = max(1, (new DateTime($start_date))->diff(new DateTime($end_date))->days + 1);
                while ($row = $result->fetch_assoc()) {
                    $avg_daily = $row['days_tracked'] > 0
                        ? round($row['total_removed'] / $row['days_tracked'], 2)
                        : 0;

                    $days = ($avg_daily > 0)
                        ? round($row['current_stock'] / $avg_daily, 1)
                        : null; // no depletion estimate when demand = 0

                    $hasDemandHistory = $avg_daily > 0;

                    $stock = (int)$row['current_stock'];

                    $isCriticalByStock = $stock <= $criticalThreshold;
                    $isCriticalByDays  = $days !== null && $days < 7;
                    $isWarningByStock  = $stock <= $lowThreshold;
                    $isWarningByDays   = $days !== null && $days < 14;

                    // Keep only medicines flagged by at least one signal
                    if ($isCriticalByStock || $isCriticalByDays || $isWarningByStock || $isWarningByDays) {
                        $status = ($isCriticalByStock || $isCriticalByDays)
                            ? 'critical'
                            : (($isWarningByStock || $isWarningByDays) ? 'warning' : 'normal');

                        // Track WHY this item was flagged, so the UI can explain it
                        $reasons = [];
                        if ($isCriticalByStock) $reasons[] = 'stock_at_or_below_critical';
                        if ($isWarningByStock && !$isCriticalByStock) $reasons[] = 'stock_at_or_below_low';
                        if ($isCriticalByDays) $reasons[] = 'depletes_under_7_days';
                        if ($isWarningByDays && !$isCriticalByDays) $reasons[] = 'depletes_under_14_days';

                        $data[] = [
                            'id'               => $row['id'],
                            'name'             => $row['name'],
                            'current_stock'    => $stock,
                            'avg_daily_demand' => $avg_daily,
                            'days_until_empty' => $days,
                            'has_demand_history' => $hasDemandHistory,
                            'status'           => $status,
                            'reasons'          => $reasons,
                            'low_threshold_used'      => $lowThreshold,
                            'critical_threshold_used' => $criticalThreshold
                        ];
                    }
                }
                $stmt->close();

                // Sort by urgency (fewest days first, then lowest stock)
                usort($data, function ($a, $b) {
                    if ($a['days_until_empty'] === null && $b['days_until_empty'] === null) {
                        return $a['current_stock'] <=> $b['current_stock'];
                    }
                    if ($a['days_until_empty'] === null) {
                        return 1;
                    }
                    if ($b['days_until_empty'] === null) {
                        return -1;
                    }
                    if ($a['days_until_empty'] !== $b['days_until_empty']) {
                        return $a['days_until_empty'] <=> $b['days_until_empty'];
                    }
                    return $a['current_stock'] <=> $b['current_stock'];
                });

                // Return max 10
                $data = array_slice($data, 0, 10);

                echo json_encode([
                    'success'             => true,
                    'data'                => $data,
                    'threshold'           => $lowThreshold,           // kept for backward compatibility
                    'low_threshold'       => $lowThreshold,
                    'critical_threshold'  => $criticalThreshold,
                    'lookback_days'       => $lookbackDays,
                    'model'               => 'consumption_based_stock_depletion'
                ]);
                break;

            /* ----------------------------------------------------- */
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>