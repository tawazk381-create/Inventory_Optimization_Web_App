<?php  
// File: app/workers/optimization_worker.php
// Processes one optimization job (given jobId) by calling GNU Octave and saving results.

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
 * Normalize a single result row from Octave.
 * Skips invalid item_id rows early.
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
 * Normalize Octave JSON payload.
 * Deduplicates by item_id (keeps first valid entry).
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
 * Processes one optimization job.
 */
function run_optimization_worker(PDO $DB, ?int $jobId = null): void
{
    $logPrefix = '[' . date('c') . ']';
    $tmpDir = sys_get_temp_dir();

    // --- claim job ---
    if ($jobId === null) {
        $DB->beginTransaction();
        $stmt = $DB->prepare("SELECT * FROM optimization_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
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
    }

    $msg = "{$logPrefix} Claimed job {$jobId}";
    echo $msg . "\n"; log_to_file($msg);

    try {
        // --- query items ---
        $itStmt = $DB->prepare("
            SELECT id, avg_daily_demand, lead_time_days, unit_cost, safety_stock, IFNULL(order_cost,50) as order_cost
            FROM items
            WHERE is_active = 1
        ");
        $itStmt->execute();
        $items = $itStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) throw new Exception("No active items to optimize");

        // --- write input CSV ---
        $inPath  = $tmpDir . "/opt_in_job_{$jobId}_" . getmypid() . ".csv";
        $outPath = $tmpDir . "/opt_out_job_{$jobId}_" . getmypid() . ".json";
        $fh = fopen($inPath, 'w');
        if (!$fh) throw new Exception("Failed to open temp input file {$inPath}");
        fputcsv($fh, ['id','avg_daily_demand','lead_time_days','unit_cost','safety_stock','order_cost']);
        foreach ($items as $it) {
            fputcsv($fh, [$it['id'],$it['avg_daily_demand'],$it['lead_time_days'],$it['unit_cost'],$it['safety_stock'],$it['order_cost']]);
        }
        fclose($fh);

        // --- run Octave ---
        $octaveScript = realpath(__DIR__ . '/../../app/octave/worker_runner.m');
        if (!$octaveScript || !file_exists($octaveScript)) throw new Exception("Octave runner missing at {$octaveScript}");
        $call = sprintf("worker_runner('%s','%s');", addslashes($inPath), addslashes($outPath));
        $octaveCmd = "octave-cli --quiet --eval " . escapeshellarg($call) . " 2>&1";

        $output = []; $ret = 0;
        exec($octaveCmd, $output, $ret);
        log_to_file("Octave output for job {$jobId}:\n" . implode("\n", $output));
        if ($ret !== 0) throw new Exception("Octave runner failed (ret={$ret})");
        if (!file_exists($outPath)) throw new Exception("Octave did not produce output file {$outPath}");

        $json = file_get_contents($outPath);
        if ($json === false) throw new Exception("Failed to read JSON output {$outPath}");
        log_to_file("Raw JSON from Octave for job {$jobId}: " . $json);

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON: " . json_last_error_msg());

        $results = normalize_results_payload($decoded);
        log_to_file("Normalized results for job {$jobId}: " . json_encode($results));

        if (empty($results)) throw new Exception("Octave returned no usable rows after normalization.");

        // --- save results ---
        $optResultModel = new OptimizationResult();
        foreach ($results as $r) {
            log_to_file("Saving row for job {$jobId}: " . json_encode($r));
        }
        $optResultModel->saveResults($jobId, $results);
        $savedCount = count($results);
        log_to_file("Requested save of {$savedCount} rows for job {$jobId}");

        // ✅ Double-check and log what was actually written in DB
        $checkStmt = $DB->prepare("SELECT * FROM optimization_results WHERE job_id = :job_id");
        $checkStmt->execute(['job_id' => $jobId]);
        $rows = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
        $dbCount = count($rows);
        log_to_file("Verification: job {$jobId} has {$dbCount} rows in optimization_results (expected {$savedCount})");
        foreach ($rows as $row) {
            log_to_file("🔎 DB row: " . json_encode($row));
        }

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
            'total'     => $dbCount, // use actual DB count
            'processed' => $dbCount,
            'res'       => json_encode($results),
            'id'        => $jobId
        ]);

        @unlink($inPath); @unlink($outPath);
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
