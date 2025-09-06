<?php
// File: app/workers/optimization_worker.php
// Processes one optimization job (given jobId) by calling Octave API (on Render) and saving results.

declare(strict_types=1);

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Called directly: bootstrap config then run
    $projectRoot = realpath(__DIR__ . '/../../');
    require_once $projectRoot . '/config/database.php'; // expects $DB (PDO)

    // ✅ Load the base Model class first
    require_once $projectRoot . '/app/core/Model.php';

    // Then load models that extend Model
    require_once $projectRoot . '/app/models/Optimization.php';
    require_once $projectRoot . '/app/models/OptimizationResult.php';

    if (!isset($DB) || !($DB instanceof PDO)) {
        fwrite(STDERR, "ERROR: PDO \$DB not found\n");
        exit(1);
    }

    // If jobId is passed as argument, use it; else run the next pending one
    $jobId = isset($argv[1]) ? (int)$argv[1] : null;
    run_optimization_worker($DB, $jobId);
    exit(0);
}

/**
 * Append a log line to storage/logs/optimizations.log (with rotation at 5 MB, keep 15 rotated).
 */
function log_to_file(string $message): void
{
    $logDir = realpath(__DIR__ . '/../../storage/logs');
    if ($logDir === false) {
        $logDir = __DIR__ . '/../../storage/logs';
        @mkdir($logDir, 0777, true);
    }
    $file = $logDir . '/optimizations.log';

    // Rotate if > 5 MB
    if (file_exists($file) && filesize($file) > 5 * 1024 * 1024) {
        $ts = date('Ymd-His');
        $rotated = $logDir . "/optimizations.log.$ts";
        @rename($file, $rotated);

        // Enforce max 15 rotated logs
        $files = glob($logDir . "/optimizations.log.*");
        if ($files !== false && count($files) > 15) {
            sort($files, SORT_STRING); // oldest first
            $excess = count($files) - 15;
            for ($i = 0; $i < $excess; $i++) {
                @unlink($files[$i]);
            }
        }
    }

    $line = '[' . date('c') . '] ' . $message . PHP_EOL;
    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Normalize a single result row from API.
 */
function normalize_result_row(array $row): ?array
{
    if (isset($row['item_id'])) {
        $itemId = (int)$row['item_id'];
    } elseif (isset($row['id'])) {
        $itemId = (int)$row['id'];
    } else {
        log_to_file("⚠️ Skipping row without item_id/id: " . json_encode($row));
        return null;
    }

    if ($itemId <= 0) {
        log_to_file("⚠️ Skipping row with invalid item_id={$itemId}: " . json_encode($row));
        return null;
    }

    $eoq = $row['eoq'] ?? ($row['EOQ'] ?? null);
    $rp  = $row['reorder_point'] ?? ($row['ROP'] ?? null);
    $ss  = $row['safety_stock'] ?? ($row['SS'] ?? null);

    return [
        'item_id'       => $itemId,
        'eoq'           => (isset($eoq) && $eoq !== '') ? (float)$eoq : null,
        'reorder_point' => (isset($rp)  && $rp  !== '') ? (float)$rp  : null,
        'safety_stock'  => (isset($ss)  && $ss  !== '') ? (float)$ss  : null,
    ];
}

/**
 * Normalize API JSON payload.
 */
function normalize_results_payload($decoded): array
{
    if (is_array($decoded) && isset($decoded['results']) && is_array($decoded['results'])) {
        $decoded = $decoded['results'];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $out = [];
    $seen = [];

    foreach ($decoded as $row) {
        if (!is_array($row)) continue;
        $norm = normalize_result_row($row);
        if ($norm !== null) {
            $itemId = $norm['item_id'];
            if (isset($seen[$itemId])) {
                log_to_file("⚠️ Duplicate result for item_id={$itemId}, skipping extra row: " . json_encode($row));
                continue;
            }
            $seen[$itemId] = true;
            $out[] = $norm;
        }
    }
    return $out;
}

/**
 * Determine Octave API URL: env override or default to Render /optimize endpoint.
 */
function get_octave_api_url(): string
{
    $env = getenv('OCTAVE_API_URL');
    if ($env && is_string($env) && trim($env) !== '') {
        return trim($env);
    }
    return 'https://octave-api.onrender.com/optimize';
}

/**
 * Call the external Octave API (Render).
 * Sends items plus optional controls.
 *
 * @param array $items      Array of item rows (already remapped to include item_id).
 * @param int|null $horizon Planning horizon in days (optional).
 * @param float|null $sl    Service level (0–1, optional).
 * @return array            Decoded JSON response.
 * @throws Exception
 */
function call_octave_api(array $items, ?int $horizon = null, ?float $sl = null): array
{
    $url = get_octave_api_url();

    $payload = [
        'items' => array_values($items),
    ];
    if ($horizon !== null && $horizon > 0) {
        $payload['horizon_days'] = $horizon;
    }
    if ($sl !== null && $sl > 0 && $sl < 1.0) {
        $payload['service_level'] = $sl;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        throw new Exception("Curl error [{$code}]: {$err}");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP {$httpCode}: {$response}");
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON from API: " . json_last_error_msg());
    }

    return $decoded;
}

/**
 * Try to fetch the job row (for optional params like horizon_days, service_level).
 */
function fetch_job_row(PDO $DB, int $jobId): ?array
{
    $stmt = $DB->prepare("SELECT * FROM optimization_jobs WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $jobId]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    return $job ?: null;
}

/**
 * Processes one optimization job.
 */
function run_optimization_worker(PDO $DB, ?int $jobId = null): void
{
    $logPrefix = '[' . date('c') . ']';

    // --- claim job ---
    $job = null;
    if ($jobId === null) {
        $DB->beginTransaction();
        $stmt = $DB->prepare("
            SELECT * FROM optimization_jobs
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            $DB->commit();
            $msg = "{$logPrefix} No pending jobs";
            echo $msg . "\n"; log_to_file($msg);
            return;
        }
        $jobId = (int)$job['id'];

        $upd = $DB->prepare("UPDATE optimization_jobs SET status = 'running', started_at = NOW() WHERE id = :id AND status = 'pending'");
        $upd->execute(['id' => $jobId]);
        if ($upd->rowCount() === 0) {
            $DB->commit();
            $msg = "{$logPrefix} Job {$jobId} was claimed by another process";
            echo $msg . "\n"; log_to_file($msg);
            return;
        }
        $DB->commit();
    } else {
        $upd = $DB->prepare("UPDATE optimization_jobs SET status = 'running', started_at = NOW() WHERE id = :id AND status = 'pending'");
        $upd->execute(['id' => $jobId]);
        if ($upd->rowCount() === 0) {
            $msg = "{$logPrefix} Job {$jobId} not found or already processing";
            echo $msg . "\n"; log_to_file($msg);
            return;
        }
        // Fetch job row for optional parameters
        $job = fetch_job_row($DB, $jobId);
    }

    $msg = "{$logPrefix} Claimed job {$jobId}";
    echo $msg . "\n"; log_to_file($msg);

    // Optional controls from job (fallbacks are safe defaults)
    $horizonDays   = null;
    $serviceLevel  = null;

    if (is_array($job)) {
        // If your table has these columns, use them; otherwise they stay null and are omitted.
        if (isset($job['horizon_days']) && is_numeric($job['horizon_days'])) {
            $horizonDays = max(1, (int)$job['horizon_days']);
        }
        if (isset($job['service_level']) && is_numeric($job['service_level'])) {
            $serviceLevel = (float)$job['service_level'];
        }
    }

    try {
        // --- query items ---
        $itStmt = $DB->prepare("
            SELECT id, avg_daily_demand, lead_time_days, unit_cost, safety_stock, IFNULL(order_cost, 50) AS order_cost
            FROM items
            WHERE is_active = 1
        ");
        $itStmt->execute();
        $items = $itStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) throw new Exception("No active items to optimize");

        // --- remap for API: add item_id and ensure numeric types are sane ---
        $payloadItems = [];
        foreach ($items as $it) {
            $itemId = isset($it['id']) ? (int)$it['id'] : 0;
            if ($itemId <= 0) {
                log_to_file("⚠️ Skipping item without valid id: " . json_encode($it));
                continue;
            }

            $payloadItems[] = [
                'item_id'          => $itemId,
                'avg_daily_demand' => isset($it['avg_daily_demand']) ? (float)$it['avg_daily_demand'] : 0.0,
                'lead_time_days'   => isset($it['lead_time_days'])   ? (float)$it['lead_time_days']   : 0.0,
                'unit_cost'        => isset($it['unit_cost'])        ? (float)$it['unit_cost']        : 0.0,
                // Optional inputs; the API can ignore if not used
                'safety_stock'     => isset($it['safety_stock'])     ? (float)$it['safety_stock']     : null,
                'order_cost'       => isset($it['order_cost'])       ? (float)$it['order_cost']       : null,
            ];
        }

        if (empty($payloadItems)) {
            throw new Exception("No valid items after remapping.");
        }

        // --- call Octave API (correct endpoint) ---
        $apiResponse = call_octave_api($payloadItems, $horizonDays, $serviceLevel);
        log_to_file("Raw API response for job {$jobId}: " . json_encode($apiResponse));

        $results = normalize_results_payload($apiResponse);
        log_to_file("Normalized results for job {$jobId}: " . json_encode($results));

        if (empty($results)) throw new Exception("API returned no usable rows after normalization.");

        // --- save results ---
        $optResultModel = new OptimizationResult();
        $optResultModel->saveResults($jobId, $results);
        $savedCount = count($results);
        log_to_file("Requested save of {$savedCount} rows for job {$jobId}");

        // ✅ Double-check DB
        $checkStmt = $DB->prepare("SELECT COUNT(*) AS c FROM optimization_results WHERE job_id = :job_id");
        $checkStmt->execute(['job_id' => $jobId]);
        $dbCount = (int)($checkStmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
        log_to_file("Verification: job {$jobId} has {$dbCount} rows in optimization_results (expected {$savedCount})");

        // --- update snapshot ---
        $optModel = new Optimization();
        $optModel->updateItemResults($results);

        // --- update job table ---
        $upd = $DB->prepare("
            UPDATE optimization_jobs
            SET status = 'complete',
                items_total = :total,
                items_processed = :processed,
                results = :res,
                completed_at = NOW()
            WHERE id = :id
        ");
        $upd->execute([
            'total'     => $dbCount,
            'processed' => $dbCount,
            'res'       => json_encode($results),
            'id'        => $jobId
        ]);

        $msg = "{$logPrefix} Job {$jobId} completed successfully with {$dbCount} rows saved";
        echo $msg . "\n"; log_to_file($msg);
    } catch (Exception $e) {
        $note = substr($e->getMessage(), 0, 2000);
        $DB->prepare("UPDATE optimization_jobs SET status='failed', results = :note WHERE id = :id")
           ->execute(['note' => json_encode(['error' => $note]), 'id' => $jobId]);

        $msg = "{$logPrefix} ERROR processing job {$jobId}: " . $e->getMessage();
        echo $msg . "\n"; log_to_file($msg);
    }
}
