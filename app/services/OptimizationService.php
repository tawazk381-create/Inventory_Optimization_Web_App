<?php
// File: app/services/OptimizationService.php
// Purpose: Create & run optimization jobs by calling the Render-hosted Octave API.
// Updates:
// - Stronger DB reconnect handling (ping + multiple retries).
// - All queries forced through execWithReconnect.
// - Extended retries for "server has gone away".
// - More resilient saveOptimizationResults batching.
// - createJob() now only inserts (queues) a job and returns its id.

declare(strict_types=1);

class OptimizationService
{
    protected PDO $db;
    protected OptimizationResult $resultModel;

    private string $defaultApiUrl = 'https://octave-api.onrender.com/optimize';

    private int $dbReconnectRetries = 3;
    private int $batchSize = 200;
    private int $httpTimeout = 60;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
        $this->resultModel = new OptimizationResult();
        $this->applyPdoAttributes();
    }

    protected function applyPdoAttributes(): void
    {
        try {
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_TIMEOUT, 60);
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $this->db->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8mb4");
            }
        } catch (\Throwable $t) {
            // best-effort only
        }
    }

    protected function attemptReconnect(): bool
    {
        try {
            $root = dirname(__DIR__, 2);
            $dbFile = $root . '/config/database.php';
            if (!file_exists($dbFile)) {
                $this->log_to_file("⚠️ attemptReconnect: config/database.php not found at {$dbFile}");
                return false;
            }

            require $dbFile;
            global $DB;
            if (isset($DB) && $DB instanceof PDO) {
                $this->db = $DB;
                $this->applyPdoAttributes();
                $this->log_to_file("🔁 Reconnected to database successfully.");
                return true;
            }
            $this->log_to_file("⚠️ attemptReconnect: \$DB not available after include.");
            return false;
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ attemptReconnect failed: " . $t->getMessage());
            return false;
        }
    }

    /** Wraps a DB operation, reconnecting if "server has gone away". */
    protected function execWithReconnect(callable $fn, int $retries = null)
    {
        if ($retries === null) $retries = $this->dbReconnectRetries;

        try {
            // ping before using; if this fails try to reconnect
            $this->db->query("SELECT 1");
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ Connection lost before query: " . $t->getMessage());
            if ($this->attemptReconnect()) {
                return $this->execWithReconnect($fn, max(0, $retries - 1));
            }
        }

        try {
            return $fn();
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            $this->log_to_file("⚠️ PDOException: {$msg}");
            $isGoneAway = (stripos($msg, 'server has gone away') !== false)
                || (($e->errorInfo[1] ?? null) === 2006);

            if ($isGoneAway && $retries > 0) {
                $this->log_to_file("🔁 DB gone away — reconnecting (retries left: {$retries})...");
                if ($this->attemptReconnect()) {
                    return $this->execWithReconnect($fn, $retries - 1);
                }
            }
            throw $e;
        }
    }

    // ----------------------
    // Main service functions
    // ----------------------

    /**
     * Insert a job and return its ID.
     * IMPORTANT: This no longer runs the heavy job inline.
     */
    public function createJob(int $userId, int $horizonDays, float $serviceLevel): int
    {
        $totalItems = (int)$this->execWithReconnect(function () {
            $row = $this->db->query("SELECT COUNT(*) AS c FROM items")->fetch(PDO::FETCH_ASSOC);
            return $row['c'] ?? 0;
        });

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

        $jobId = (int)$this->execWithReconnect(function () {
            return (int)$this->db->lastInsertId();
        });

        $this->log_to_file("Created job {$jobId} (enqueued).");
        return $jobId;
    }

    /**
     * Run the heavy job processing (used by the CLI worker).
     */
    protected function runOctaveJob(int $jobId, int $horizonDays, float $serviceLevel): void
    {
        $this->markJobRunning($jobId);
        $allItems = $this->fetchItemsForOptimization();
        $total = count($allItems);

        if ($total === 0) {
            $this->markJobFailed($jobId, 'No items available to optimize.');
            return;
        }

        $apiUrl = getenv('OCTAVE_API_URL') ?: $this->defaultApiUrl;
        $batchSize = max(1, $this->batchSize);
        $timeout = max(30, $this->httpTimeout);

        $aggregateResults = [];
        $anyBatchSucceeded = false;
        $itemsProcessed = 0;

        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $batch = array_slice($allItems, $offset, $batchSize);
            $payload = [
                'job_id'        => $jobId,
                'horizon_days'  => $horizonDays,
                'service_level' => $serviceLevel,
                'items'         => $batch,
            ];

            $this->log_to_file("POST {$apiUrl} (job {$jobId}, batch size " . count($batch) . ")");
            $response = $this->postJson($apiUrl, $payload, $timeout);

            if (!$response['ok']) {
                $reason = "Remote API failed: " . $response['error'] . " [HTTP " . ($response['status'] ?? 'n/a') . "]";
                $this->markJobFailed($jobId, $reason);
                continue;
            }

            $decoded = $response['json'];
            $results = $this->normalize_results_payload($decoded);

            if (empty($results)) {
                $this->log_to_file("No usable results in batch (job {$jobId}).");
                continue;
            }

            $this->applyItemResultsBestEffort($results);
            $saved = $this->saveOptimizationResults($jobId, $results);

            $itemsProcessed += $saved;
            if ($saved > 0) {
                $anyBatchSucceeded = true;
                $this->incrementProcessed($jobId, $saved);
                foreach ($results as $r) {
                    if (count($aggregateResults) >= 1000) break;
                    $aggregateResults[] = [
                        'item_id'       => $r['item_id'],
                        'eoq'           => $r['eoq'],
                        'reorder_point' => $r['reorder_point'],
                        'safety_stock'  => $r['safety_stock'],
                    ];
                }
            }
        }

        if ($anyBatchSucceeded && $itemsProcessed > 0) {
            $this->markJobComplete($jobId, $aggregateResults, $itemsProcessed);
        } else {
            $this->markJobFailed($jobId, 'All batches failed or returned no results.');
        }
    }

    // ----------------------
    // DB operations
    // ----------------------

    protected function fetchItemsForOptimization(): array
    {
        $preferred = [
            'id'               => 'item_id',
            'avg_daily_demand' => 'avg_daily_demand',
            'lead_time_days'   => 'lead_time_days',
            'unit_cost'        => 'unit_cost',
            'safety_stock'     => 'safety_stock',
            'order_cost'       => 'order_cost',
        ];

        $existing = $this->fetchTableColumns('items');
        if (empty($existing)) return [];

        $selectParts = [];
        foreach ($preferred as $col => $alias) {
            if (!in_array($col, $existing, true)) continue;
            if ($col === 'id') {
                $selectParts[] = "`id` AS `item_id`";
            } elseif ($col === 'order_cost') {
                $selectParts[] = "COALESCE(`{$col}`, 50) AS `{$alias}`";
            } else {
                $selectParts[] = "COALESCE(`{$col}`, 0) AS `{$alias}`";
            }
        }

        if (!in_array("`id` AS `item_id`", $selectParts, true)) return [];

        $where = in_array('is_active', $existing, true) ? "WHERE `is_active` = 1" : "";
        $sql = "SELECT " . implode(", ", $selectParts) . " FROM `items` {$where}";

        try {
            $rows = $this->execWithReconnect(function () use ($sql) {
                $stmt = $this->db->query($sql);
                return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ fetchItemsForOptimization failed: " . $t->getMessage());
            return [];
        }

        foreach ($rows as &$r) {
            $r['item_id'] = (int)$r['item_id'];
        }
        return $rows;
    }

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

    protected function saveOptimizationResults(int $jobId, array $results): int
    {
        if (empty($results)) return 0;

        return $this->execWithReconnect(function () use ($jobId, $results) {
            $this->db->beginTransaction();
            $count = 0;
            try {
                foreach ($results as $r) {
                    // resultModel->saveResults accepts either a single row or array depending on implementation.
                    // Existing implementation expects ($jobId, $row) for individual saves, so keep that.
                    $this->resultModel->saveResults($jobId, $r);
                    $count++;
                }
                $this->db->commit();
                return $count;
            } catch (\Throwable $t) {
                $this->db->rollBack();
                $this->log_to_file("⚠️ saveOptimizationResults failed: " . $t->getMessage());
                throw $t;
            }
        });
    }

    protected function applyItemResultsBestEffort(array $results): void
    {
        $columns = $this->fetchTableColumns('items');
        $colEOQ = in_array('eoq', $columns, true) ? 'eoq' : null;
        $colROP = in_array('reorder_point', $columns, true) ? 'reorder_point' : null;
        $colSS  = in_array('safety_stock', $columns, true) ? 'safety_stock' : null;

        if (!$colEOQ && !$colROP && !$colSS) return;

        try {
            $this->execWithReconnect(function () use ($results, $colEOQ, $colROP, $colSS) {
                $this->db->beginTransaction();
                try {
                    foreach ($results as $r) {
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
            $this->log_to_file("⚠️ applyItemResultsBestEffort failed: " . $t->getMessage());
        }
    }

    protected function fetchTableColumns(string $table): array
    {
        try {
            return $this->execWithReconnect(function () use ($table) {
                $stmt = $this->db->query("DESCRIBE `{$table}`");
                return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
            });
        } catch (\Throwable $t) {
            $this->log_to_file("⚠️ fetchTableColumns failed for {$table}: " . $t->getMessage());
            return [];
        }
    }

    protected function normalize_result_row(array $row): ?array
    {
        $itemId = $row['item_id'] ?? $row['id'] ?? null;
        if (!$itemId) return null;

        return [
            'item_id'       => (int)$itemId,
            'eoq'           => isset($row['eoq']) ? (float)$row['eoq'] : null,
            'reorder_point' => isset($row['reorder_point']) ? (float)$row['reorder_point'] : null,
            'safety_stock'  => isset($row['safety_stock']) ? (float)$row['safety_stock'] : null,
        ];
    }

    protected function normalize_results_payload($decoded): array
    {
        if (isset($decoded['results']) && is_array($decoded['results'])) {
            $decoded = $decoded['results'];
        }
        if (!is_array($decoded)) return [];

        $out = [];
        $seen = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) continue;
            $norm = $this->normalize_result_row($row);
            if ($norm) {
                $itemId = $norm['item_id'];
                if (isset($seen[$itemId])) {
                    $this->log_to_file("⚠️ Duplicate result for item_id={$itemId}, skipping extra row.");
                    continue;
                }
                $seen[$itemId] = true;
                $out[] = $norm;
            }
        }
        return $out;
    }

    public function markJobRunning(int $jobId): void
    {
        $this->execWithReconnect(function () use ($jobId) {
            $stmt = $this->db->prepare("UPDATE optimization_jobs SET status = 'running', started_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $jobId]);
            return true;
        });
    }

    public function markJobComplete(int $jobId, ?array $results = null, ?int $itemsProcessed = null): void
    {
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
    }

    public function markJobFailed(int $jobId, string $error = ''): void
    {
        $this->execWithReconnect(function () use ($jobId, $error) {
            $payload = $error ? json_encode(['error' => $error]) : null;
            $stmt = $this->db->prepare("
                UPDATE optimization_jobs
                SET status = 'failed',
                    results = :results,
                    completed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['id' => $jobId, 'results' => $payload]);
            return true;
        });
    }

    public function incrementProcessed(int $jobId, int $count = 1): void
    {
        $this->execWithReconnect(function () use ($jobId, $count) {
            $stmt = $this->db->prepare("UPDATE optimization_jobs SET items_processed = items_processed + :count WHERE id = :id");
            $stmt->execute(['id' => $jobId, 'count' => $count]);
            return true;
        });
    }

    public function getJob(int $id): ?array
    {
        return $this->execWithReconnect(function () use ($id) {
            $stmt = $this->db->prepare("
                SELECT j.*, u.name AS user_name
                FROM optimization_jobs j
                LEFT JOIN users u ON j.user_id = u.id
                WHERE j.id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        });
    }

    public function getAllJobs(): array
    {
        return $this->execWithReconnect(function () {
            $stmt = $this->db->query("
                SELECT j.*, u.name AS user_name
                FROM optimization_jobs j
                LEFT JOIN users u ON j.user_id = u.id
                ORDER BY j.created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    public function getLatestJobId(): ?int
    {
        return $this->execWithReconnect(function () {
            $stmt = $this->db->query("SELECT id FROM optimization_jobs ORDER BY created_at DESC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        });
    }

    protected function log_to_file(string $message): void
    {
        $root = dirname(__DIR__, 2);
        $dir  = $root . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        $file = $dir . '/optimization_service.log';

        $line = '[' . date('c') . '] ' . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
