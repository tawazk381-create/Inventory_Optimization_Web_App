<?php
// File: public/index.php
declare(strict_types=1);

// ------------------------------------------------------------
// Error reporting (development vs production)
// ------------------------------------------------------------
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
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
// InfinityFree serves from htdocs/, project root is one level above /public
define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
define('APP_DIR',     PROJECT_ROOT . '/app');
define('CONFIG_DIR',  PROJECT_ROOT . '/config');
define('ROUTES_DIR',  PROJECT_ROOT . '/routes');
define('VIEW_DIR',    PROJECT_ROOT . '/resources/views');

// ------------------------------------------------------------
// Optional Composer autoload
// ------------------------------------------------------------
$autoload = PROJECT_ROOT . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// ------------------------------------------------------------
// Config & DB
// ------------------------------------------------------------
require CONFIG_DIR . '/app.php';
require CONFIG_DIR . '/database.php';

// ------------------------------------------------------------
// App bootstrap (autoload, helpers, error handling)
// ------------------------------------------------------------
require APP_DIR . '/bootstrap.php';

// ------------------------------------------------------------
// Router + Routes
// ------------------------------------------------------------
// Detect base path (subfolder like "/public")
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($basePath === '/public') {
    // If project is accessed as .../public, normalize to parent folder
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
