<?php
// File: public/run_worker.php
// Trigger the CLI worker in background using a secret token.
// Usage: https://inventoryoptimization.rf.gd/run_worker.php?secret=THE_SECRET
// IMPORTANT: Protect with a strong secret and use HTTPS.

declare(strict_types=1);

// Basic response helper
function respond(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// Log helper (writes to storage/logs/optimization_service.log)
function log_to_file(string $message): void
{
    $root = dirname(__DIR__);
    $dir  = $root . '/storage/logs';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $file = $dir . '/optimization_service.log';
    $line = '[' . date('c') . '] [RUN_WORKER] ' . $message . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// Load expected secret from environment (preferable) or fallback to a hard-coded default
$expected = getenv('WORKER_SECRET') ?: 'V1y7q9Gk3Fh2Zs8LxP0rT6uN5dC4bQeR';

// Read incoming secret (GET param recommended for monitor)
$provided = $_GET['secret'] ?? '';

// Quick auth
if (!is_string($provided) || trim($provided) === '' || $provided !== $expected) {
    http_response_code(403);
    log_to_file("Unauthorized run attempt from {$_SERVER['REMOTE_ADDR']}");
    respond(['ok' => false, 'error' => 'Forbidden']);
}

// Build the background command
$php = PHP_BINARY ?: 'php';
$script = realpath(__DIR__ . '/../app/workers/optimization_worker.php');
if ($script === false) {
    log_to_file("Worker script not found at expected path.");
    respond(['ok' => false, 'error' => 'Worker script not found']);
}

// Use escapeshellcmd for binary and escapeshellarg for script
$cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 & echo $!';

// Try different spawn methods and record results
$attempts = [];
$success = false;
$pid = null;
$errorMessages = [];

// 1) exec()
if (!$success && function_exists('exec')) {
    try {
        @exec($cmd, $output, $ret);
        $attempts[] = ['method' => 'exec', 'ret' => $ret, 'output' => $output];
        // If command echoed something (pid), pick it up
        if (!empty($output) && is_array($output)) {
            $pidLine = trim(end($output));
            if ($pidLine !== '') {
                $pid = $pidLine;
                $success = true;
            }
        } elseif ($ret === 0) {
            $success = true; // best-effort
        }
    } catch (\Throwable $t) {
        $errorMessages[] = "exec() failed: " . $t->getMessage();
    }
}

// 2) shell_exec()
if (!$success && function_exists('shell_exec')) {
    try {
        $out = @shell_exec($cmd);
        $attempts[] = ['method' => 'shell_exec', 'output' => $out];
        if (is_string($out) && trim($out) !== '') {
            $pid = trim($out);
            $success = true;
        } elseif ($out === null) {
            // shell_exec may return null on failure, but we treat it as attempted
        } else {
            $success = true; // best-effort
        }
    } catch (\Throwable $t) {
        $errorMessages[] = "shell_exec() failed: " . $t->getMessage();
    }
}

// 3) proc_open()
if (!$success && function_exists('proc_open')) {
    try {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($cmd, $descriptorspec, $pipes);
        if (is_resource($process)) {
            // read stdout (may be PID)
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            foreach ($pipes as $p) { @fclose($p); }
            $status = @proc_close($process);
            $attempts[] = ['method' => 'proc_open', 'stdout' => $stdout, 'stderr' => $stderr, 'status' => $status];
            if (is_string($stdout) && trim($stdout) !== '') {
                $pid = trim($stdout);
                $success = true;
            } elseif ($status === 0) {
                $success = true;
            }
        } else {
            $attempts[] = ['method' => 'proc_open', 'note' => 'proc_open returned non-resource'];
        }
    } catch (\Throwable $t) {
        $errorMessages[] = "proc_open() failed: " . $t->getMessage();
    }
}

// If still not marked success, log attempts and return best-effort failure
if ($success) {
    $msg = "Worker triggered successfully";
    if ($pid) $msg .= " (pid: {$pid})";
    log_to_file($msg . ' via ' . json_encode($attempts));
    respond(['ok' => true, 'pid' => $pid, 'attempts' => $attempts]);
}

// If nothing worked, return details for debugging
log_to_file("Failed to trigger worker; attempts: " . json_encode($attempts) . '; errors: ' . json_encode($errorMessages));
respond(['ok' => false, 'attempts' => $attempts, 'errors' => $errorMessages]);
