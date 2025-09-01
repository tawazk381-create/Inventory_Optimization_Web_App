<?php 
// File: app/controllers/ReportController.php

declare(strict_types=1);

class ReportController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->requireRole(['Admin', 'Manager']);
    }

    /**
     * Show the reports page.
     */
    public function index()
    {
        // ✅ Inventory items with current_stock
        $sql = "
            SELECT i.id, i.sku, i.name, i.unit_price, i.safety_stock,
                   COALESCE(SUM(sm.quantity),0) AS current_stock
            FROM items i
            LEFT JOIN stock_movements sm ON sm.item_id = i.id
            GROUP BY i.id
        ";
        $stmt  = $this->db->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalValue = 0.0;
        $lowStock = [];
        foreach ($items as $it) {
            $stock = (int)($it['current_stock'] ?? 0);
            $value = ((float)($it['unit_price'] ?? 0)) * $stock;
            $totalValue += $value;
            if ($stock <= (int)($it['safety_stock'] ?? 5)) {
                $lowStock[] = $it;
            }
        }

        // ✅ Recent movements (last 7 days)
        $mstmt = $this->db->prepare("
            SELECT sm.*, i.sku, i.name
            FROM stock_movements sm
            LEFT JOIN items i ON i.id = sm.item_id
            WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY sm.created_at DESC
            LIMIT 100
        ");
        $mstmt->execute();
        $movements = $mstmt->fetchAll(PDO::FETCH_ASSOC);

        // ✅ Latest optimization job results
        $latestJobStmt = $this->db->query("
            SELECT id FROM optimization_jobs 
            WHERE status = 'complete'
            ORDER BY completed_at DESC LIMIT 1
        ");
        $latestJob = $latestJobStmt->fetch(PDO::FETCH_ASSOC);

        $optimizationResults = [];
        if ($latestJob) {
            $jobId = (int)$latestJob['id'];
            $resStmt = $this->db->prepare("
                SELECT r.item_id, i.sku, i.name,
                       r.eoq, r.reorder_point, r.safety_stock
                FROM optimization_results r
                JOIN items i ON r.item_id = i.id
                WHERE r.job_id = :job_id
                ORDER BY r.item_id ASC
            ");
            $resStmt->execute(['job_id' => $jobId]);
            $optimizationResults = $resStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // ✅ Pass everything to the view
        $this->view('reports/index.php', [
            'title'               => 'Reports',
            'totalValue'          => $totalValue,
            'lowStock'            => $lowStock,
            'movements'           => $movements,
            'optimizationResults' => $optimizationResults,
        ]);
    }

    /**
     * Fetch job results for the given jobId as JSON.
     */
    public function getJobResults(): void
    {
        $jobId = filter_input(INPUT_GET, 'job', FILTER_VALIDATE_INT);

        if (!$jobId) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid Job ID']);
            return;
        }

        // Fetch results from optimization_results table
        $stmt = $this->db->prepare("
            SELECT item_id, eoq, reorder_point, safety_stock
            FROM optimization_results
            WHERE job_id = :job_id
            ORDER BY item_id ASC
        ");
        $stmt->execute(['job_id' => $jobId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['results' => $results]);
    }

    /**
     * Download the optimization job report as CSV or JSON.
     */
    public function downloadReport(): void
    {
        $jobId = filter_input(INPUT_GET, 'job', FILTER_VALIDATE_INT);
        $format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRING);

        if (!$jobId || !$format || !in_array($format, ['csv', 'json'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        if ($format === 'csv') {
            $this->generateCsvReport($jobId);
        } elseif ($format === 'json') {
            $this->generateJsonReport($jobId);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Unsupported report format']);
        }
    }

    /**
     * Generate a CSV report for the given jobId.
     */
    private function generateCsvReport(int $jobId): void
    {
        $stmt = $this->db->prepare("
            SELECT item_id, eoq, reorder_point, safety_stock
            FROM optimization_results
            WHERE job_id = :job_id
            ORDER BY item_id ASC
        ");
        $stmt->execute(['job_id' => $jobId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = "optimization_report_job_{$jobId}.csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item ID', 'EOQ', 'Reorder Point', 'Safety Stock']);

        foreach ($results as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }

    /**
     * Generate a JSON report for the given jobId.
     */
    private function generateJsonReport(int $jobId): void
    {
        $stmt = $this->db->prepare("
            SELECT item_id, eoq, reorder_point, safety_stock
            FROM optimization_results
            WHERE job_id = :job_id
            ORDER BY item_id ASC
        ");
        $stmt->execute(['job_id' => $jobId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = "optimization_report_job_{$jobId}.json";
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo json_encode($results, JSON_PRETTY_PRINT);
    }
}
