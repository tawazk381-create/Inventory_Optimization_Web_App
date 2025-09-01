<?php
// File: app/services/JobReportService.php

declare(strict_types=1);

class JobReportService
{
    protected PDO $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Generate a downloadable CSV report for a given optimization job.
     *
     * @param int $jobId The ID of the optimization job
     * @return void
     */
    public function generateCsvReport(int $jobId): void
    {
        // Fetch results from optimization_results table
        $stmt = $this->db->prepare("
            SELECT item_id, eoq, reorder_point, safety_stock
            FROM optimization_results
            WHERE job_id = :job_id
            ORDER BY item_id ASC
        ");
        $stmt->execute(['job_id' => $jobId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare CSV output
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
     * Generate a downloadable JSON report for a given optimization job.
     *
     * @param int $jobId The ID of the optimization job
     * @return void
     */
    public function generateJsonReport(int $jobId): void
    {
        // Fetch results from optimization_results table
        $stmt = $this->db->prepare("
            SELECT item_id, eoq, reorder_point, safety_stock
            FROM optimization_results
            WHERE job_id = :job_id
            ORDER BY item_id ASC
        ");
        $stmt->execute(['job_id' => $jobId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare JSON output
        $filename = "optimization_report_job_{$jobId}.json";
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // Output the JSON results
        echo json_encode($results, JSON_PRETTY_PRINT);
    }

    /**
     * Fetch the results for a given job in either CSV or JSON format.
     *
     * @param int $jobId The ID of the optimization job
     * @param string $format The format of the report (csv or json)
     * @return void
     */
    public function generateReport(int $jobId, string $format): void
    {
        if ($format === 'csv') {
            $this->generateCsvReport($jobId);
        } elseif ($format === 'json') {
            $this->generateJsonReport($jobId);
        } else {
            // Invalid format
            http_response_code(400);
            echo json_encode(['error' => 'Invalid report format.']);
        }
    }
}
