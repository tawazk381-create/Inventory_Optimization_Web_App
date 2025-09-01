<?php
// File: app/workers/optimization_cron.php
// Cron-friendly runner: execute once and exit. Suitable for running every minute via cron.
// Usage: php app/workers/optimization_cron.php
declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/../../');
require_once $projectRoot . '/config/database.php';
if (!isset($DB) || !($DB instanceof PDO)) {
    fwrite(STDERR, "ERROR: PDO \$DB not found\n");
    exit(1);
}

// Reuse the single-job worker (in-process include for faster startup)
require_once $projectRoot . '/app/workers/optimization_worker.php';
// The included file defines a function run_optimization_worker($DB) — see worker below
run_optimization_worker($DB);
