<?php
// File: public/index.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Show errors during development
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => !empty($_SERVER['HTTPS']),
    ]);
}

// ------------------------------------------------------------
// Paths
// ------------------------------------------------------------
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_DIR',     PROJECT_ROOT . '/app');
define('CONFIG_DIR',  PROJECT_ROOT . '/config');
define('ROUTES_DIR',  PROJECT_ROOT . '/routes');
define('VIEW_DIR',    PROJECT_ROOT . '/resources/views');

// Optional Composer autoload
$autoload = PROJECT_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// Config & DB
require CONFIG_DIR . '/app.php';
require CONFIG_DIR . '/database.php';

// App bootstrap (autoload, helpers, error handling)
require APP_DIR . '/bootstrap.php';

// ------------------------------------------------------------
// Router + Routes
// ------------------------------------------------------------
// Detect base path (subfolder like "/Inventory_Optimization_Web_App")
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '/public') {
    // If project is accessed as http://localhost/Inventory_Optimization_Web_App/public
    // normalize to parent folder
    $basePath = dirname($basePath);
}
if ($basePath === '\\' || $basePath === '.') {
    $basePath = '';
}

$router = new Router($basePath);

// Load route definitions
require ROUTES_DIR . '/web.php';
require ROUTES_DIR . '/api.php';

// ------------------------------------------------------------
// Dispatch request
// ------------------------------------------------------------
$uri    = $_SERVER['REQUEST_URI']  ?? '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$router->dispatch($uri, $method);
