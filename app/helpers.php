<?php
// File: app/helpers.php
declare(strict_types=1);

// Always start the session (needed by CSRF + flash helpers).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Pull in the real helper files that live in app/helpers/
$base = __DIR__ . '/helpers';

$files = [
    $base . '/functions.php',   // provides csrf_field(), verify_csrf(), base_path(), redirect(), flash(), etc.
    $base . '/validation.php',  // simple validators
    $base . '/rbac.php',        // role helper (optional)
];

foreach ($files as $f) {
    if (is_file($f)) {
        require_once $f;
    }
}
