<?php 
// File: app/models/Optimization.php

class Optimization extends Model
{
    protected $table = 'optimizations';

    /**
     * Update per-item optimization snapshot values.
     * Called after a job has completed successfully.
     */
    public function updateItemResults(array $results): void
    {
        foreach ($results as $row) {
            if (!isset($row['item_id'])) {
                continue;
            }

            $sql = "
                INSERT INTO {$this->table} 
                    (item_id, eoq, reorder_point, safety_stock, last_run_at, updated_at)
                VALUES 
                    (:item_id, :eoq, :rp, :ss, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    eoq           = VALUES(eoq), 
                    reorder_point = VALUES(reorder_point), 
                    safety_stock  = VALUES(safety_stock), 
                    last_run_at   = VALUES(last_run_at), 
                    updated_at    = VALUES(updated_at)
            ";

            $this->execute($sql, [
                'item_id' => $row['item_id'],
                'eoq'     => $row['eoq'] ?? null,
                'rp'      => $row['reorder_point'] ?? null,
                'ss'      => $row['safety_stock'] ?? null,
            ]);
        }
    }

    /**
     * Fetch the latest optimization snapshot for all items.
     */
    public function getAllSnapshots(): array
    {
        $sql = "
            SELECT o.item_id,
                   i.name AS item_name,
                   o.eoq,
                   o.reorder_point,
                   o.safety_stock,
                   o.last_run_at
            FROM {$this->table} o
            INNER JOIN items i ON o.item_id = i.id
            ORDER BY i.name ASC
        ";

        return $this->find($sql);
    }

    /**
     * Fetch the optimization snapshot for a single item.
     */
    public function getSnapshotForItem(int $itemId): ?array
    {
        $sql = "
            SELECT o.item_id,
                   i.name AS item_name,
                   o.eoq,
                   o.reorder_point,
                   o.safety_stock,
                   o.last_run_at
            FROM {$this->table} o
            INNER JOIN items i ON o.item_id = i.id
            WHERE o.item_id = :item_id
            LIMIT 1
        ";

        return $this->first($sql, ['item_id' => $itemId]) ?: null;
    }

    /**
     * Mirror job results into optimization_results table.
     * This delegates to the OptimizationResult model (row-based table).
     */
    public function populateOptimizationResults(int $jobId, array $results): void
    {
        if (empty($results)) {
            return;
        }

        require_once __DIR__ . '/OptimizationResult.php';
        $resultModel = new OptimizationResult();

        // Save all results for this job
        $resultModel->saveResults($jobId, $results);
    }
}
