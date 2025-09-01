<?php
// File: app/services/OptimizationService.php

class OptimizationService
{
    protected PDO $db;
    protected OptimizationResult $resultModel;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        $this->resultModel = new OptimizationResult();
    }

    /**
     * Create a new optimization job and run the Octave worker to completion.
     */
    public function createJob(int $userId, int $horizonDays, float $serviceLevel): int
    {
        $totalItems = (int)($this->db
            ->query("SELECT COUNT(*) AS c FROM items")
            ->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $stmt = $this->db->prepare("
            INSERT INTO optimization_jobs (
                user_id, horizon_days, service_level, status, items_total, items_processed, created_at
            ) VALUES (
                :user_id, :horizon_days, :service_level, 'pending', :items_total, 0, NOW()
            )
        ");
        $stmt->execute([
            'user_id'       => $userId,
            'horizon_days'  => $horizonDays,
            'service_level' => $serviceLevel,
            'items_total'   => $totalItems
        ]);

        $jobId = (int)$this->db->lastInsertId();

        $this->runOctaveJob($jobId);

        return $jobId;
    }

    protected function jobPaths(int $jobId): array
    {
        $root = dirname(__DIR__, 2);

        $jobDir   = $root . "/storage/opt_jobs/job_$jobId";
        $logDir   = $root . "/storage/logs";
        $octDir   = $root . "/app/octave";

        if (!is_dir($jobDir)) @mkdir($jobDir, 0777, true);
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

        return [
            'root'        => $root,
            'octave_dir'  => $octDir,
            'job_dir'     => $jobDir,
            'input_csv'   => "$jobDir/input.csv",
            'output_json' => "$jobDir/output.json",
            'runner_m'    => "$octDir/worker_runner.m",
            'shim_m'      => "$jobDir/run_job.m",
            'log_file'    => "$logDir/job_{$jobId}.log",
        ];
    }

    protected function buildInputCsv(string $csvPath): int
    {
        $sql = "
            SELECT
                id,
                COALESCE(avg_daily_demand, 0)   AS avg_daily_demand,
                COALESCE(lead_time_days, 0)     AS lead_time_days,
                COALESCE(safety_stock, 0)       AS safety_stock
            FROM items
        ";
        $stmt = $this->db->query($sql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $fh = fopen($csvPath, 'w');
        if (!$fh) throw new RuntimeException("Unable to write input CSV at: $csvPath");

        fputcsv($fh, ['id','avg_daily_demand','lead_time_days','safety_stock']);

        $count = 0;
        foreach ($rows as $r) {
            fputcsv($fh, [
                (int)$r['id'],
                (float)$r['avg_daily_demand'],
                (float)$r['lead_time_days'],
                (float)$r['safety_stock'],
            ]);
            $count++;
        }
        fclose($fh);

        return $count;
    }

    protected function runOctaveJob(int $jobId): void
    {
        $paths = $this->jobPaths($jobId);
        $exported = $this->buildInputCsv($paths['input_csv']);
        $this->markJobRunning($jobId);

        $octaveDir = $this->toOctavePath($paths['octave_dir']);
        $inPath    = $this->toOctavePath($paths['input_csv']);
        $outPath   = $this->toOctavePath($paths['output_json']);

        $shim = implode(PHP_EOL, [
            "% Auto-generated per job",
            "addpath('$octaveDir');",
            "infile  = '$inPath';",
            "outfile = '$outPath';",
            "worker_runner(infile, outfile);",
        ]);
        if (false === file_put_contents($paths['shim_m'], $shim)) {
            $this->markJobFailed($jobId, "Failed to write shim script");
            return;
        }

        $octave = 'octave';
        $cmd = $this->buildExecCommand([$octave, '-qf', $paths['shim_m']], $paths['log_file']);
        $exitCode = $this->runCommand($cmd);

        if ($exitCode !== 0) {
            $this->markJobFailed($jobId, "Octave exited with code $exitCode (see log)");
            return;
        }

        if (!is_file($paths['output_json'])) {
            $this->markJobFailed($jobId, "Octave did not produce output.json");
            return;
        }

        $json = file_get_contents($paths['output_json']);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            $this->markJobFailed($jobId, "Invalid JSON from worker");
            return;
        }

        // ✅ Update items table (best effort)
        $this->applyItemResultsBestEffort($decoded);

        // ✅ Persist job results in optimization_results table (single-row interface)
        $saved = $this->saveOptimizationResults($jobId, $decoded);

        // ✅ Mark job as complete, with JSON snapshot also in optimization_jobs
        // Use the number of saved rows as items_processed for accuracy.
        $this->markJobComplete($jobId, $decoded, $saved);
    }

    protected function toOctavePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        return str_replace("'", "''", $normalized);
    }

    protected function buildExecCommand(array $argv, string $logFile): string
    {
        $parts = array_map(function ($p) {
            $p = str_replace('"', '\"', $p);
            return "\"$p\"";
        }, $argv);

        $cmd = implode(' ', $parts);
        $cmd .= $this->isWindows()
            ? ' > "' . $logFile . '" 2>&1'
            : ' > ' . escapeshellarg($logFile) . ' 2>&1';

        return $cmd;
    }

    protected function runCommand(string $cmd): int
    {
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) return 1;

        if (isset($pipes[1])) { stream_get_contents($pipes[1]); fclose($pipes[1]); }
        if (isset($pipes[2])) { stream_get_contents($pipes[2]); fclose($pipes[2]); }

        return proc_close($proc);
    }

    protected function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    protected function applyItemResultsBestEffort(array $results): void
    {
        $columns = $this->fetchTableColumns('items');

        $colEOQ = in_array('eoq', $columns, true) ? 'eoq' : (in_array('eoq_qty', $columns, true) ? 'eoq_qty' : null);
        $colROP = in_array('reorder_point', $columns, true) ? 'reorder_point' : (in_array('reorder_level', $columns, true) ? 'reorder_level' : null);
        $colSS  = in_array('safety_stock', $columns, true) ? 'safety_stock' : null;

        if (!$colEOQ && !$colROP && !$colSS) return;

        $this->db->beginTransaction();
        try {
            foreach ($results as $r) {
                if (!isset($r['item_id'])) continue;

                $sets = [];
                $params = ['id' => (int)$r['item_id']];

                if ($colEOQ && isset($r['eoq'])) {
                    $sets[] = "$colEOQ = :eoq";
                    $params['eoq'] = (int)$r['eoq'];
                }
                if ($colROP && isset($r['reorder_point'])) {
                    $sets[] = "$colROP = :rop";
                    $params['rop'] = (int)$r['reorder_point'];
                }
                if ($colSS && isset($r['safety_stock'])) {
                    $sets[] = "$colSS = :ss";
                    $params['ss'] = (int)$r['safety_stock'];
                }

                if (empty($sets)) continue;

                $sql = "UPDATE items SET " . implode(', ', $sets) . " WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            $this->db->commit();
        } catch (\Throwable $t) {
            $this->db->rollBack();
        }
    }

    protected function fetchTableColumns(string $table): array
    {
        try {
            $stmt = $this->db->query("DESCRIBE `$table`");
            return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        } catch (\Throwable $t) {
            return [];
        }
    }

    /**
     * ✅ Save results into optimization_results inside a transaction.
     * Uses the model's single-row save interface and returns how many rows were saved.
     */
    protected function saveOptimizationResults(int $jobId, array $results): int
    {
        if (empty($results)) return 0;

        $this->db->beginTransaction();
        try {
            $count = 0;
            foreach ($results as $r) {
                // Only attempt save when item_id is present
                if (!isset($r['item_id']) || (int)$r['item_id'] <= 0) {
                    continue;
                }
                $this->resultModel->saveResults($jobId, $r);
                $count++;
            }
            $this->db->commit();
            error_log("✅ Saved {$count} optimization results for job {$jobId}");
            return $count;
        } catch (\Throwable $t) {
            $this->db->rollBack();
            error_log("❌ Failed to save optimization results for job {$jobId}: " . $t->getMessage());
            return 0;
        }
    }

    public function markJobRunning(int $jobId): void
    {
        $stmt = $this->db->prepare("
            UPDATE optimization_jobs
            SET status = 'running', started_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $jobId]);
    }

    public function markJobComplete(int $jobId, ?array $results = null, ?int $itemsProcessed = null): void
    {
        $json = $results ? json_encode($results) : null;

        $stmt = $this->db->prepare("
            UPDATE optimization_jobs
            SET status = 'complete',
                results = :results,
                items_processed = COALESCE(:items_processed, items_processed),
                completed_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id'              => $jobId,
            'results'         => $json,
            'items_processed' => $itemsProcessed,
        ]);
    }

    public function markJobFailed(int $jobId, string $error = ''): void
    {
        $payload = $error ? json_encode(['error' => $error]) : null;

        $stmt = $this->db->prepare("
            UPDATE optimization_jobs
            SET status = 'failed',
                results = :results,
                completed_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id'      => $jobId,
            'results' => $payload
        ]);
    }

    public function incrementProcessed(int $jobId, int $count = 1): void
    {
        $stmt = $this->db->prepare("
            UPDATE optimization_jobs
            SET items_processed = items_processed + :count
            WHERE id = :id
        ");
        $stmt->execute(['id' => $jobId, 'count' => $count]);
    }

    public function getJob(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT j.id,
                   j.user_id,
                   u.name AS user_name,
                   j.horizon_days,
                   j.service_level,
                   j.status,
                   j.results,
                   j.items_total,
                   j.items_processed,
                   j.created_at,
                   j.started_at,
                   j.completed_at
            FROM optimization_jobs j
            LEFT JOIN users u ON j.user_id = u.id
            WHERE j.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getAllJobs(): array
    {
        $sql = "
            SELECT j.id,
                   j.user_id,
                   u.name AS user_name,
                   j.horizon_days,
                   j.service_level,
                   j.status,
                   j.items_total,
                   j.items_processed,
                   j.created_at,
                   j.started_at,
                   j.completed_at
            FROM optimization_jobs j
            LEFT JOIN users u ON j.user_id = u.id
            ORDER BY j.created_at DESC
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLatestJobId(): ?int
    {
        $stmt = $this->db->query("SELECT id FROM optimization_jobs ORDER BY created_at DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}
