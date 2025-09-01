<?php 
// File: config/database.php
// Database connection via PDO with prepared statements
declare(strict_types=1);

/**
 * This file will:
 *  - try to require Composer autoload if it exists (for vlucas/phpdotenv),
 *  - if Composer or vlucas/phpdotenv is not available, it'll attempt a tiny .env parser
 *    so development can proceed without installing Composer dependencies.
 *
 * After loading env vars we create a PDO $DB instance (used throughout the app).
 */

$projectRoot = dirname(__DIR__);

// -- tiny .env loader (fallback) ------------------------------------------------
function load_dotenv_fallback(string $envFile): void
{
    if (!file_exists($envFile)) return;
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;
    foreach ($lines as $line) {
        // skip comments
        if (strpos(trim($line), '#') === 0) continue;
        // basic KEY=VALUE parsing (handles quoted values)
        if (strpos($line, '=') === false) continue;
        list($key, $val) = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        // remove surrounding quotes
        if ((substr($val, 0, 1) === '"' && substr($val, -1) === '"')
            || (substr($val, 0, 1) === "'" && substr($val, -1) === "'")) {
            $val = substr($val, 1, -1);
        }
        // Only set if not already set in environment (so system env vars override .env)
        if (getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// -- Attempt to load Composer autoloader and vlucas/phpdotenv --------------------
$envPath = $projectRoot;
$envFile = $envPath . '/.env';
$vendorAutoload = $projectRoot . '/vendor/autoload.php';

if (file_exists($envFile)) {
    // Prefer Composer + phpdotenv if available
    if (file_exists($vendorAutoload)) {
        // safe require composer autoload when present
        require_once $vendorAutoload;
        if (class_exists('Dotenv\Dotenv')) {
            try {
                $dotenv = Dotenv\Dotenv::createImmutable($envPath);
                $dotenv->load();
            } catch (Throwable $e) {
                // fallback to manual loader if phpdotenv fails for some reason
                load_dotenv_fallback($envFile);
            }
        } else {
            // composer present but phpdotenv not installed -> fallback
            load_dotenv_fallback($envFile);
        }
    } else {
        // Composer not installed -> fallback to lightweight reader
        load_dotenv_fallback($envFile);
    }
}

// -- Read environment variables (defaults for production InfinityFree) ----------
$DB_HOST = getenv('DB_HOST') ?: 'sql100.infinityfree.com';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'if0_39836969_inventory';
$DB_USER = getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'if0_39836969';
$DB_PASS = getenv('DB_PASSWORD') ?: 'mXfZxRyrItM9';

// Optional DSN overrides (useful if set by .env)
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $DB = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Friendly error for local development. In production avoid echoing DB details.
    http_response_code(500);
    echo "Database connection failed. Check your .env and database server.\n";
    error_log('DB connection error: ' . $e->getMessage());
    exit;
}
