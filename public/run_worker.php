<?php
// File: public/run_worker.php
// Purpose: Trigger the CLI worker in background using a secret token.
// Usage: https://your-site/run_worker.php?secret=LONG_SECRET
// IMPORTANT: Protect with a strong secret and use HTTPS.

declare(strict_types=1);

// get secret from environment or fallback to .env value via getenv
$expected = getenv('WORKER_SECRET') ?: 'replace-with-a-strong-secret';

// read incoming secret (GET is easiest for external monitors)
$provided = $_GET['secret'] ?? '';

// simple auth
if (!is_string($provided) || trim($provided) === '' || $provided !== $expected) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// path to PHP and worker script — adjust if your install path differs
$php = PHP_BINARY ?: 'php';
$script = __DIR__ . '/../app/workers/optimization_worker.php';

// build command — run in background (non-blocking)
$cmd = escapeshellcmd("{$php} {$script}") . " > /dev/null 2>&1 &";

// Try to exec; if exec is disabled, attempt safe fallback: invoke via HTTP (synchronous)
$exec_ok = false;
$exec_error = null;
if (function_exists('exec')) {
    @exec($cmd);
    $exec_ok = true;
} else {
    $exec_error = 'exec() not available on this host';
}

// Return quickly so the monitor doesn't wait for the worker
echo json_encode([
    'ok' => $exec_ok,
    'cmd' => $cmd,
    'msg' => $exec_ok ? 'Worker triggered' : 'Could not exec; ' . $exec_error
]);
