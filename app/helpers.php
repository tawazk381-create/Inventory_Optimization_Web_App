<?php 
// File: app/helpers.php
declare(strict_types=1);

// ------------------------------------------------------------
// Session handling (important for CSRF + flash)
// ------------------------------------------------------------

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Set safe session cookie params (InfinityFree friendly)
    session_name('INVOPTSESSID'); // custom name to avoid clashes

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax', // 'Strict' may break on InfinityFree redirects
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);

    session_start();
}

// ------------------------------------------------------------
// Load helper files
// ------------------------------------------------------------
$base = __DIR__ . '/helpers';

$files = [
    $base . '/functions.php',   // csrf_field(), csrf_token(), redirect(), flash(), etc.
    $base . '/validation.php',  // validators
    $base . '/rbac.php',        // role helper (optional)
];

foreach ($files as $f) {
    if (is_file($f)) {
        require_once $f;
    }
}

// ------------------------------------------------------------
// Ensure CSRF token always exists
// ------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
