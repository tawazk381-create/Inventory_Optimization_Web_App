<?php 
// File: app/controllers/OptimizationController.php

declare(strict_types=1);

class OptimizationController extends Controller
{
    protected $auth;
    protected $service;
    protected $resultModel;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->service = new OptimizationService();
        $this->resultModel = new OptimizationResult();
        $this->requireRole(['Admin', 'Manager']); // restrict access
    }

    public function index(): void
    {
        $jobs = $this->service->getAllJobs();

        if (!$jobs || count($jobs) === 0) {
            $this->view('optimizations/empty', [
                'title'   => 'Optimizations',
                'message' => 'No optimization jobs available.'
            ]);
            return;
        }

        try {
            $db = Database::getInstance();
            $columns = $db->query("DESCRIBE items")->fetchAll(PDO::FETCH_COLUMN, 0);
            $hasActive = in_array('is_active', $columns, true);

            $itemsTotal = $hasActive
                ? (int)($db->query("SELECT COUNT(*) AS c FROM items WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)
                : (int)($db->query("SELECT COUNT(*) AS c FROM items")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

            $stmtProcessed = $db->prepare("SELECT COUNT(*) AS c FROM optimization_results WHERE job_id = :job_id");

            foreach ($jobs as &$j) {
                $stmtProcessed->execute(['job_id' => (int)$j['id']]);
                $processed = (int)($stmtProcessed->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
                $j['items_total'] = $itemsTotal;
                $j['items_processed'] = $processed;

                if (($j['status'] ?? '') === 'running') {
                    $j['status'] = 'processing';
                }

                $j['progress_percent'] = ($itemsTotal > 0)
                    ? min(100, (int)floor(($processed / $itemsTotal) * 100))
                    : 0;
            }
            unset($j);
        } catch (Throwable $e) {
            // swallow enrichment errors
        }

        $this->view('optimizations/index', [
            'title' => 'Optimizations',
            'jobs'  => $jobs,
            'user'  => $this->auth->user()
        ]);
    }

    public function run(): void
    {
        verify_csrf();
        $horizon = filter_input(INPUT_POST, 'horizon_days', FILTER_VALIDATE_INT) ?: 90;
        $serviceLevel = filter_input(INPUT_POST, 'service_level', FILTER_VALIDATE_FLOAT) ?: 0.95;

        $jobId = $this->service->createJob($this->auth->id(), $horizon, $serviceLevel);

        $worker = realpath(__DIR__ . '/../../app/workers/optimization_worker.php');

        if ($worker && is_file($worker)) {
            try {
                if (stripos(PHP_OS, 'WIN') === 0) {
                    pclose(popen('start /B php "' . $worker . '" ' . (int)$jobId, 'r'));
                } else {
                    exec('php ' . escapeshellarg($worker) . ' ' . (int)$jobId . ' > /dev/null 2>&1 &');
                }
            } catch (Throwable $e) {
                error_log('Failed to launch optimization worker: ' . $e->getMessage());
            }
        }

        $this->redirect('/optimizations/view?job=' . (int)$jobId);
    }

    public function getJobJson(): void
    {
        $jobId = filter_input(INPUT_GET, 'job', FILTER_VALIDATE_INT);
        if (!$jobId) {
            $jobId = $this->service->getLatestJobId();
            if (!$jobId) {
                $this->json(['error' => 'No optimization jobs found. Please run one.'], 404);
                return;
            }
        }

        $job = $this->service->getJob($jobId);
        if (!$job) {
            $this->json(['error' => 'Optimization job not found.'], 404);
            return;
        }

        if (($job['status'] ?? '') === 'running') {
            $job['status'] = 'processing';
        }

        $results = $this->resultModel->getResultsForJob($jobId);

        $db = Database::getInstance();
        $columns = $db->query("DESCRIBE items")->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasActive = in_array('is_active', $columns, true);

        $itemsTotal = $hasActive
            ? (int)($db->query("SELECT COUNT(*) AS c FROM items WHERE is_active = 1")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0)
            : (int)($db->query("SELECT COUNT(*) AS c FROM items")->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

        $itemsProcessed = count($results);
        $progressPercent = ($itemsTotal > 0) ? min(100, (int)floor(($itemsProcessed / $itemsTotal) * 100)) : 0;

        $job['results'] = $results;
        $job['items_total'] = $itemsTotal;
        $job['items_processed'] = $itemsProcessed;
        $job['progress_percent'] = $progressPercent;

        $this->json($job);
    }

    public function viewPage(): void
    {
        $jobId = filter_input(INPUT_GET, 'job', FILTER_VALIDATE_INT);
        if (!$jobId) {
            $jobId = $this->service->getLatestJobId();
            if (!$jobId) {
                $this->view('optimizations/empty', [
                    'title'   => 'Optimizations',
                    'message' => 'No optimization jobs have been run yet. Please start one.'
                ]);
                return;
            }
        }

        $job = $this->service->getJob($jobId);
        $results = $this->resultModel->getResultsForJob($jobId);

        $this->view('optimizations/view', [
            'title'   => 'Optimization Job Results',
            'job'     => $job,
            'results' => $results,
            'user'    => $this->auth->user()
        ]);
    }

    public function downloadReport(): void
    {
        $jobId = filter_input(INPUT_GET, 'job', FILTER_VALIDATE_INT);
        if (!$jobId) {
            $jobId = $this->service->getLatestJobId();
            if (!$jobId) {
                http_response_code(404);
                echo "No optimization jobs found.";
                return;
            }
        }

        $results = $this->resultModel->getResultsForJob($jobId);
        if (empty($results)) {
            http_response_code(404);
            echo "No results found for job.";
            return;
        }

        // 🔧 Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }

        $filename = "optimization_report_job_{$jobId}.csv";
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item ID', 'Item Name', 'EOQ', 'Reorder Point', 'Safety Stock']);

        foreach ($results as $row) {
            $item = $this->getItemById($row['item_id']);
            fputcsv($output, [
                $item['id'],
                $item['name'],
                $row['eoq'] ?? 'N/A',
                $row['reorder_point'] ?? 'N/A',
                $row['safety_stock'] ?? 'N/A'
            ]);
        }

        fclose($output);
        exit;
    }

    private function getItemById($itemId)
    {
        global $DB;
        $stmt = $DB->prepare("SELECT * FROM items WHERE id = :itemId");
        $stmt->execute([':itemId' => $itemId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
