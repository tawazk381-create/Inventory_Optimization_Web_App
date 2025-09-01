<?php  
// File: app/models/OptimizationResult.php

class OptimizationResult extends Model
{
    /** @var string */
    protected ?string $table = 'optimization_results';

    /**
     * Insert or update a single result row for a given job.
     * Expects $row to have keys:
     *   item_id, eoq, reorder_point, safety_stock
     */
    public function saveResults(int $jobId, array $row): void
    {
        if (!isset($row['item_id']) || (int)$row['item_id'] <= 0) {
            error_log("⚠️ Skipping invalid optimization row for job {$jobId}: " . json_encode($row));
            return;
        }

        try {
            $sql = "
                INSERT INTO {$this->table} 
                    (job_id, item_id, eoq, reorder_point, safety_stock, created_at, updated_at)
                VALUES 
                    (:job_id, :item_id, :eoq, :reorder_point, :safety_stock, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    eoq           = VALUES(eoq),
                    reorder_point = VALUES(reorder_point),
                    safety_stock  = VALUES(safety_stock),
                    updated_at    = NOW()
            ";

            $params = [
                'job_id'        => $jobId,
                'item_id'       => (int)$row['item_id'],
                'eoq'           => isset($row['eoq']) ? (int)$row['eoq'] : null,
                'reorder_point' => isset($row['reorder_point']) ? (int)$row['reorder_point'] : null,
                'safety_stock'  => isset($row['safety_stock']) ? (int)$row['safety_stock'] : null,
            ];

            $affected = $this->execute($sql, $params);

            error_log("✅ Saved/updated result for job {$jobId}, item {$row['item_id']} (affected {$affected} row[s])");
        } catch (Exception $e) {
            error_log("❌ Failed to save optimization result for job {$jobId}, item {$row['item_id']}: " . $e->getMessage());
        }
    }

    /**
     * Get all results for a given job ID.
     */
    public function getResultsForJob(int $jobId): array
    {
        $sql = "
            SELECT r.job_id,
                   r.item_id,
                   i.name AS item_name,
                   r.eoq,
                   r.reorder_point,
                   r.safety_stock,
                   r.created_at,
                   r.updated_at
            FROM {$this->table} r
            INNER JOIN items i ON r.item_id = i.id
            WHERE r.job_id = :job_id
            ORDER BY i.name ASC
        ";

        return $this->find($sql, ['job_id' => $jobId]) ?: [];
    }

    /**
     * Get the latest result entry for a single item across all jobs.
     */
    public function getLatestForItem(int $itemId): ?array
    {
        $sql = "
            SELECT r.job_id,
                   r.item_id,
                   i.name AS item_name,
                   r.eoq,
                   r.reorder_point,
                   r.safety_stock,
                   r.created_at,
                   r.updated_at
            FROM {$this->table} r
            INNER JOIN items i ON r.item_id = i.id
            WHERE r.item_id = :item_id
            ORDER BY r.created_at DESC
            LIMIT 1
        ";

        return $this->first($sql, ['item_id' => $itemId]) ?: null;
    }

    /**
     * Get the latest results for all items (from the most recent job).
     */
    public function getLatestJobResults(): array
    {
        $sql = "
            SELECT r.job_id,
                   r.item_id,
                   i.name AS item_name,
                   r.eoq,
                   r.reorder_point,
                   r.safety_stock,
                   r.created_at,
                   r.updated_at
            FROM {$this->table} r
            INNER JOIN items i ON r.item_id = i.id
            WHERE r.job_id = (
                SELECT MAX(job_id) FROM {$this->table}
            )
            ORDER BY i.name ASC
        ";

        return $this->find($sql) ?: [];
    }
}
