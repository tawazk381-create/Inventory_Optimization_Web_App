<?php
// File: app/bootstrap.php
declare(strict_types=1);

// ------------------------------------------------------------
//  Autoload controllers, models, core classes, services
// ------------------------------------------------------------
spl_autoload_register(function ($class) {
    $baseDirs = [
        __DIR__ . '/controllers/',
        __DIR__ . '/models/',
        __DIR__ . '/core/',
        __DIR__ . '/services/',
        __DIR__ . '/middleware/',
    ];
    foreach ($baseDirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ------------------------------------------------------------
//  Load global helpers
// ------------------------------------------------------------
$helpers = __DIR__ . '/helpers.php';
if (file_exists($helpers)) {
    require_once $helpers;
}

// ------------------------------------------------------------
//  Basic error handler (optional, dev mode)
// ------------------------------------------------------------
set_exception_handler(function ($e) {
    http_response_code(500);
    echo "<h1>Application Error</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    if (ini_get('display_errors')) {
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
    exit;
});
