<?php
// File: app/workers/optimization_daemon.php
// Supervisor-friendly daemon that repeatedly runs the single-job worker.
// Run under supervisor or systemd: php app/workers/optimization_daemon.php
declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/../../');
chdir($projectRoot);

// Load bootstrap / DB config
require_once $projectRoot . '/config/database.php'; // provides $DB; used only for existence check
if (!isset($DB) || !($DB instanceof PDO)) {
    fwrite(STDERR, "ERROR: PDO \$DB not found. Ensure config/database.php returns \$DB.\n");
    exit(1);
}

$pidFile = $projectRoot . '/storage/daemon_optimization.pid';
$lockFile = $projectRoot . '/storage/daemon_optimization.lock';
$logFile  = $projectRoot . '/storage/daemon_optimization.log';
$sleepSeconds = getenv('OPT_DAEMON_SLEEP') ? (int)getenv('OPT_DAEMON_SLEEP') : 8;

// ensure storage folder exists
@mkdir($projectRoot . '/storage', 0755, true);

function logline($msg) {
    global $logFile;
    $line = '['.date('c').'] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

logline("Starting optimization daemon");

// PID/lock handling to avoid accidental multiple daemons
if (file_exists($pidFile)) {
    $oldPid = (int)@file_get_contents($pidFile);
    if ($oldPid > 0 && posix_kill($oldPid, 0)) {
        logline("Another daemon is running with PID {$oldPid}. Exiting.");
        exit(0);
    } else {
        // stale pid file
        @unlink($pidFile);
    }
}
file_put_contents($pidFile, getmypid());

// Acquire an exclusive lock on the lockfile to get mutual exclusion across processes
$lockFp = fopen($lockFile, 'c');
if ($lockFp === false) {
    logline("Failed to open lock file {$lockFile}");
    @unlink($pidFile);
    exit(1);
}
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    logline("Could not obtain lock (another instance?). Exiting.");
    fclose($lockFp);
    @unlink($pidFile);
    exit(0);
}

// Setup signal handling if pcntl exists
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function() use ($pidFile, $lockFp) {
        logline("Received SIGTERM, stopping daemon.");
        if (is_resource($lockFp)) flock($lockFp, LOCK_UN);
        @unlink($pidFile);
        exit(0);
    });
    pcntl_signal(SIGINT, function() use ($pidFile, $lockFp) {
        logline("Received SIGINT, stopping daemon.");
        if (is_resource($lockFp)) flock($lockFp, LOCK_UN);
        @unlink($pidFile);
        exit(0);
    });
}

$workerScript = PHP_BINARY . ' ' . escapeshellarg($projectRoot . '/app/workers/optimization_worker.php');

while (true) {
    // Run single-job worker as a separate process (keeps memory use low)
    logline("Invoking worker: {$workerScript}");
    $output = [];
    $ret = 0;
    exec($workerScript . ' 2>&1', $output, $ret);
    $outText = implode("\n", $output);
    if (trim($outText) !== '') {
        logline("Worker output (ret={$ret}): " . substr($outText, 0, 4000));
    }
    // sleep for configured interval
    sleep($sleepSeconds);
}
