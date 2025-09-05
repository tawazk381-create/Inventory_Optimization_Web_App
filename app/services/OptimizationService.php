<?php 
// File: app/services/OptimizationService.php
// Purpose: Create & run optimization jobs by calling the Render-hosted Octave API.
// NOTES:
// - This version no longer shells out to octave-cli (InfinityFree restriction).
// - It collects items from MySQL, posts them as JSON to OCTAVE_API_URL (/run),
//   normalizes the response, writes results to DB, and updates the job record.
// - This revision dynamically selects only columns that exist in the `items` table
//   so the code won't attempt to SELECT non-existent columns (e.g. unit_cost).
// - Added automatic reconnect/retry for "MySQL server has gone away" (SQLSTATE 2006).

declare(strict_types=1);

class OptimizationService
{
    protected PDO $db;
    protected OptimizationResult $resultModel;

    // Fallback API URL if env not present
    private string $defaultApiUrl = 'https://octave-api.onrender.com/run';

    // How many reconnect attempts for an operation (1 retry after reconnect)
    private int $dbReconnectRetries = 1;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        $this->resultModel = new OptimizationResult();
    }

    /**
     * Small helper: attempt to re-create the $DB PDO connection by including config/database.php.
     * Returns true on success (and assigns $this->db), false otherwise.
     */
    protected function attemptReconnect(): bool
    {
        try {
            $root = dirname(__DIR__, 2);
            $dbFile = $root . '/config/database.php';
            if (!file_exists($dbFile)) {
                $this->log_to_file("⚠️ attemptReconnect: config/database.php not found at {$dbFile}");
                return false;
            }

            // Re-require config/database.php — it should set the global $DB PDO instance
            /** @noinspection PhpIncludeInspection */
            require_once $dbFile;
            global $DB;
            if (isset($DB) && $DB instanceof PDO) {
                $this->db = $DB;
                // Ensure exceptions for PDO so we catch them
                try {
                    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                } catch (\Throwable $t) {
                    // ignore if driver doesn't allow changing attributes
                }
                $this->log_to_file("🔁 Reconnected to database successfully.");
                return true;
            } else {
                $this->log_to_file("⚠️ attemptReconnect: config/database.php did not provide \$DB PDO instance.");
                return false;
            }
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ attemptReconnect failed: " . $t->getMessage());
            return false;
        }
    }

    /**
     * Execute a callable that performs DB work and retry once after reconnect if
     * we detect "MySQL server has gone away" (SQLSTATE 2006).
     *
     * Usage: $this->execWithReconnect(function() use (...) { return $this->db->query(...); });
     *
     * @param callable $fn Callable that performs DB work and returns a value.
     * @param int $retries Remaining retries (internal). Default uses $this->dbReconnectRetries.
     * @return mixed
     * @throws \Throwable rethrows final exception if failure persists.
     */
    protected function execWithReconnect(callable $fn, int $retries = null)
    {
        if ($retries === null) $retries = $this->dbReconnectRetries;
        try {
            return $fn();
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $this->log_to_file("⚠️ PDOException caught: " . $msg);
            $isGoneAway = (stripos($msg, 'MySQL server has gone away') !== false)
                || (stripos($msg, 'server has gone away') !== false)
                || ($e->errorInfo[1] ?? null) === 2006;

            if ($isGoneAway && $retries > 0) {
                $this->log_to_file("🔁 Detected MySQL gone away — attempting reconnect and retry ({$retries} retries left)...");
                if ($this->attemptReconnect()) {
                    // Retry the callable once
                    return $this->execWithReconnect($fn, $retries - 1);
                }
            }

            // Not recoverable or retries exhausted — rethrow
            throw $e;
        } catch (\Throwable $t) {
            // Non-PDO errors — rethrow
            throw $t;
        }
    }

    /**
     * Create a new optimization job and POST it to the Octave API.
     */
    public function createJob(int $userId, int $horizonDays, float $serviceLevel): int
    {
        // Get total count safely with reconnect logic
        $totalItems = (int)$this->execWithReconnect(function () {
            $row = $this->db
                ->query("SELECT COUNT(*) AS c FROM items")
                ->fetch(PDO::FETCH_ASSOC);
            return $row['c'] ?? 0;
        });

        // Insert job record (wrapped)
        $this->execWithReconnect(function () use ($userId, $horizonDays, $serviceLevel, $totalItems) {
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
            return true;
        });

        // lastInsertId also wrapped (some drivers may require active connection)
        $jobId = (int)$this->execWithReconnect(function () {
            return (int)$this->db->lastInsertId();
        });

        // Immediately run the job through the remote API (this method has its own DB wrappers)
        $this->runOctaveJob($jobId, $horizonDays, $serviceLevel);

        return $jobId;
    }

    /**
     * Main pipeline: read items -> call API -> normalize -> persist -> mark status.
     */
    protected function runOctaveJob(int $jobId, int $horizonDays, float $serviceLevel): void
    {
        $this->markJobRunning($jobId);

        // 1) Collect items to optimize
        $items = $this->fetchItemsForOptimization();
        if (empty($items)) {
            $this->log_to_file("❌ Job {$jobId}: No items available to optimize.");
            $this->markJobFailed($jobId, 'No items available to optimize.');
            return;
        }

        // 2) Prepare POST payload for Render API
        $payload = [
            'job_id'        => $jobId,
            'horizon_days'  => $horizonDays,
            'service_level' => $serviceLevel,
            'items'         => $items,
        ];

        // 3) Call Octave API
        $apiUrl = getenv('OCTAVE_API_URL') ?: $this->defaultApiUrl;
        $this->log_to_file("OptimizationService: POST {$apiUrl} (job {$jobId})");
        $response = $this->postJson($apiUrl, $payload, 120); // 120s timeout

        if (!$response['ok']) {
            $reason = "Remote API call failed: " . $response['error'];
            $this->log_to_file("❌ {$reason} (job {$jobId})");
            $this->markJobFailed($jobId, $reason);
            return;
        }

        // 4) Decode & normalize results
        $decoded = $response['json'];
        if (!is_array($decoded)) {
            $this->log_to_file("❌ Job {$jobId}: Invalid JSON from remote API.");
            $this->markJobFailed($jobId, 'Invalid JSON from remote API.');
            return;
        }

        $results = $this->normalize_results_payload($decoded);
        $this->log_to_file("Normalization produced " . count($results) . " rows (job {$jobId}).");

        if (empty($results)) {
            $this->markJobFailed($jobId, 'Remote API returned no usable rows after normalization.');
            return;
        }

        // 5) Update items table best-effort (EOQ/ROP/SS columns if present)
        $this->applyItemResultsBestEffort($results);

        // 6) Persist results in optimization_results (row-per-item)
        $savedCount = $this->saveOptimizationResults($jobId, $results);

        // 7) Mark job complete with a compact snapshot
        $this->markJobComplete($jobId, $results, $savedCount);

        $this->log_to_file("✅ Job {$jobId} complete. Saved {$savedCount} rows.");
    }

    /**
     * Get items to send to Octave service.
     *
     * Dynamically picks only the columns that actually exist in the `items` table.
     * Applies WHERE is_active=1 only if that column exists.
     */
    protected function fetchItemsForOptimization(): array
    {
        // Preferred column => alias in result
        $preferred = [
            'id'               => 'item_id',         // REQUIRED
            'avg_daily_demand' => 'avg_daily_demand',
            'lead_time_days'   => 'lead_time_days',
            'unit_cost'        => 'unit_cost',       // removed automatically if not present
            'safety_stock'     => 'safety_stock',
            'order_cost'       => 'order_cost',
        ];

        $existing = $this->fetchTableColumns('items');
        if (empty($existing)) {
            $this->log_to_file("⚠️ OptimizationService: DESCRIBE items returned no columns.");
            return [];
        }

        // Build SELECT parts only for columns that exist
        $selectParts = [];
        foreach ($preferred as $col => $alias) {
            if (!in_array($col, $existing, true)) {
                continue;
            }

            // Special handling/defaults
            if ($col === 'id') {
                $selectParts[] = "`id` AS `item_id`";
                continue;
            }
            if ($col === 'order_cost') {
                $selectParts[] = "COALESCE(`{$col}`, 50) AS `{$alias}`";
                continue;
            }

            $selectParts[] = "COALESCE(`{$col}`, 0) AS `{$alias}`";
        }

        if (empty($selectParts) || !in_array("`id` AS `item_id`", $selectParts, true)) {
            $this->log_to_file("⚠️ OptimizationService: items.id missing, cannot fetch items.");
            return [];
        }

        // Add WHERE clause only if is_active exists
        $where = in_array('is_active', $existing, true) ? "WHERE `is_active` = 1" : "";

        $sql = "SELECT " . implode(",\n       ", $selectParts) . "\nFROM `items` {$where}";

        try {
            $rows = $this->execWithReconnect(function () use ($sql) {
                $stmt = $this->db->query($sql);
                return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            });
        } catch (\Throwable $t) {
            $this->log_to_file("❌ OptimizationService: fetchItemsForOptimization query failed: " . $t->getMessage());
            return [];
        }

        // Ensure numeric types only for keys that are present
        foreach ($rows as &$r) {
            if (isset($r['item_id'])) {
                $r['item_id'] = (int)$r['item_id'];
            }
            if (isset($r['avg_daily_demand'])) {
                $r['avg_daily_demand'] = (float)$r['avg_daily_demand'];
            }
            if (isset($r['lead_time_days'])) {
                $r['lead_time_days'] = (float)$r['lead_time_days'];
            }
            if (isset($r['unit_cost'])) {
                $r['unit_cost'] = (float)$r['unit_cost'];
            }
            if (isset($r['safety_stock'])) {
                $r['safety_stock'] = (float)$r['safety_stock'];
            }
            if (isset($r['order_cost'])) {
                $r['order_cost'] = (float)$r['order_cost'];
            }
        }
        unset($r);

        $this->log_to_file("OptimizationService: fetched " . count($rows) . " items for optimization.");
        return $rows;
    }

    /**
     * POST JSON to the remote Octave API.
     */
    protected function postJson(string $url, array $data, int $timeoutSeconds = 60): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'json' => null, 'status' => null, 'error' => 'curl_init failed'];
        }

        $json = json_encode($data);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $errstr = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            return ['ok' => false, 'json' => null, 'status' => null, 'error' => "cURL error {$errno}: {$errstr}"];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'json' => null, 'status' => $status, 'error' => "HTTP {$status}: {$body}"];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['ok' => false, 'json' => null, 'status' => $status, 'error' => 'JSON decode error: ' . json_last_error_msg()];
        }

        return ['ok' => true, 'json' => $decoded, 'status' => $status, 'error' => null];
    }

    /**
     * Save results into optimization_results inside a transaction.
     */
    protected function saveOptimizationResults(int $jobId, array $results): int
    {
        if (empty($results)) return 0;

        try {
            return $this->execWithReconnect(function () use ($jobId, $results) {
                $this->db->beginTransaction();
                $count = 0;
                try {
                    foreach ($results as $r) {
                        if (!isset($r['item_id']) || (int)$r['item_id'] <= 0) {
                            continue;
                        }
                        $this->resultModel->saveResults($jobId, $r);
                        $count++;
                    }
                    $this->db->commit();
                    $this->log_to_file("✅ Saved {$count} optimization_results rows for job {$jobId}");
                    return $count;
                } catch (\Throwable $t) {
                    $this->db->rollBack();
                    throw $t;
                }
            });
        } catch (\Throwable $t) {
            $this->log_to_file("❌ Failed to save optimization_results for job {$jobId}: " . $t->getMessage());
            return 0;
        }
    }

    /**
     * Best-effort update of items table columns (EOQ/ROP/SS) if present.
     */
    protected function applyItemResultsBestEffort(array $results): void
    {
        $columns = $this->fetchTableColumns('items');

        $colEOQ = in_array('eoq', $columns, true) ? 'eoq' : (in_array('eoq_qty', $columns, true) ? 'eoq_qty' : null);
        $colROP = in_array('reorder_point', $columns, true) ? 'reorder_point' : (in_array('reorder_level', $columns, true) ? 'reorder_level' : null);
        $colSS  = in_array('safety_stock', $columns, true) ? 'safety_stock' : null;

        if (!$colEOQ && !$colROP && !$colSS) return;

        try {
            $this->execWithReconnect(function () use ($results, $colEOQ, $colROP, $colSS) {
                $this->db->beginTransaction();
                try {
                    foreach ($results as $r) {
                        if (!isset($r['item_id'])) continue;

                        $sets = [];
                        $params = ['id' => (int)$r['item_id']];

                        if ($colEOQ && isset($r['eoq'])) {
                            $sets[] = "$colEOQ = :eoq";
                            $params['eoq'] = (float)$r['eoq'];
                        }
                        if ($colROP && isset($r['reorder_point'])) {
                            $sets[] = "$colROP = :rop";
                            $params['rop'] = (float)$r['reorder_point'];
                        }
                        if ($colSS && isset($r['safety_stock'])) {
                            $sets[] = "$colSS = :ss";
                            $params['ss'] = (float)$r['safety_stock'];
                        }

                        if (empty($sets)) continue;

                        $sql = "UPDATE items SET " . implode(', ', $sets) . " WHERE id = :id";
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute($params);
                    }
                    $this->db->commit();
                } catch (\Throwable $t) {
                    $this->db->rollBack();
                    throw $t;
                }
                return true;
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ Best-effort items update failed: " . $t->getMessage());
        }
    }

    protected function fetchTableColumns(string $table): array
    {
        try {
            return $this->execWithReconnect(function () use ($table) {
                $safe = str_replace('`', '', $table);
                $stmt = $this->db->query("DESCRIBE `{$safe}`");
                return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
            });
        } catch (\Throwable $t) {
            return [];
        }
    }

    protected function normalize_result_row(array $row): ?array
    {
        if (isset($row['item_id'])) {
            $itemId = (int)$row['item_id'];
        } elseif (isset($row['id'])) {
            $itemId = (int)$row['id'];
        } else {
            $this->log_to_file("⚠️ Skipping row without item id: " . json_encode($row));
            return null;
        }

        if ($itemId <= 0) {
            $this->log_to_file("⚠️ Skipping row with invalid item_id={$itemId}: " . json_encode($row));
            return null;
        }

        $eoq = $row['eoq'] ?? ($row['EOQ'] ?? null);
        $rop = $row['reorder_point'] ?? ($row['ROP'] ?? null);
        $ss  = $row['safety_stock'] ?? ($row['SS'] ?? null);

        return [
            'item_id'       => $itemId,
            'eoq'           => (isset($eoq) && $eoq !== '') ? (float)$eoq : null,
            'reorder_point' => (isset($rop) && $rop !== '') ? (float)$rop : null,
            'safety_stock'  => (isset($ss)  && $ss  !== '') ? (float)$ss  : null,
        ];
    }

    protected function normalize_results_payload($decoded): array
    {
        if (is_array($decoded) && isset($decoded['results']) && is_array($decoded['results'])) {
            $decoded = $decoded['results'];
        }
        if (!is_array($decoded)) return [];

        $out  = [];
        $seen = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            $norm = $this->normalize_result_row($row);
            if ($norm === null) continue;

            $id = $norm['item_id'];
            if (isset($seen[$id])) {
                $this->log_to_file("⚠️ Duplicate item_id {$id}, keeping first and skipping.");
                continue;
            }
            $seen[$id] = true;
            $out[] = $norm;
        }
        return $out;
    }

    // Job status helpers

    public function markJobRunning(int $jobId): void
    {
        try {
            $this->execWithReconnect(function () use ($jobId) {
                $stmt = $this->db->prepare("
                    UPDATE optimization_jobs
                    SET status = 'running', started_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $jobId]);
                return true;
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ markJobRunning failed: " . $t->getMessage());
        }
    }

    public function markJobComplete(int $jobId, ?array $results = null, ?int $itemsProcessed = null): void
    {
        try {
            $this->execWithReconnect(function () use ($jobId, $results, $itemsProcessed) {
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
                return true;
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ markJobComplete failed: " . $t->getMessage());
        }
    }

    public function markJobFailed(int $jobId, string $error = ''): void
    {
        try {
            $this->execWithReconnect(function () use ($jobId, $error) {
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
                return true;
            });
        } catch (\Throwable $t) {
            // If even the failure marker can't be written, log locally and give up
            $this->log_to_file("⚠️ markJobFailed failed to write to DB for job {$jobId}: " . $t->getMessage());
        }
    }

    public function incrementProcessed(int $jobId, int $count = 1): void
    {
        try {
            $this->execWithReconnect(function () use ($jobId, $count) {
                $stmt = $this->db->prepare("
                    UPDATE optimization_jobs
                    SET items_processed = items_processed + :count
                    WHERE id = :id
                ");
                $stmt->execute(['id' => $jobId, 'count' => $count]);
                return true;
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ incrementProcessed failed: " . $t->getMessage());
        }
    }

    public function getJob(int $id): ?array
    {
        try {
            return $this->execWithReconnect(function () use ($id) {
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
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ getJob failed: " . $t->getMessage());
            return null;
        }
    }

    public function getAllJobs(): array
    {
        try {
            return $this->execWithReconnect(function () {
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
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ getAllJobs failed: " . $t->getMessage());
            return [];
        }
    }

    public function getLatestJobId(): ?int
    {
        try {
            return $this->execWithReconnect(function () {
                $stmt = $this->db->query("SELECT id FROM optimization_jobs ORDER BY created_at DESC LIMIT 1");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? (int)$row['id'] : null;
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ getLatestJobId failed: " . $t->getMessage());
            return null;
        }
    }

    // Logging helper

    protected function log_to_file(string $message): void
    {
        $root = dirname(__DIR__, 2);
        $dir  = $root . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $file = $dir . '/optimization_service.log';

        if (file_exists($file) && filesize($file) > 5 * 1024 * 1024) {
            $ts = date('Ymd-His');
            @rename($file, $dir . "/optimization_service.log.$ts");
        }

        $line = '[' . date('c') . '] ' . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
