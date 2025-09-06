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
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
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
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        if (class_exists('Dotenv\Dotenv')) {
            try {
                $dotenv = Dotenv\Dotenv::createImmutable($envPath);
                $dotenv->load();
            } catch (Throwable $e) {
                load_dotenv_fallback($envFile);
            }
        } else {
            load_dotenv_fallback($envFile);
        }
    } else {
        load_dotenv_fallback($envFile);
    }
}

// -- Read environment variables -------------------------------------------------
// InfinityFree defaults (override via .env or hosting control panel)
$DB_HOST = getenv('DB_HOST') ?: 'sql100.infinityfree.com';
$DB_PORT = getenv('DB_PORT') ?: '3306';
$DB_NAME = getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'if0_39836969_inventory';
$DB_USER = getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'if0_39836969';
$DB_PASS = getenv('DB_PASSWORD') ?: 'mXfZxRyrItM9';

// Optional DSN overrides (useful if set by .env)
$charset = 'utf8mb4';
$dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,

    // Added options for stability
    PDO::ATTR_TIMEOUT            => 60,
    PDO::ATTR_PERSISTENT         => false,
];
if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
    $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
}

// -- Try connecting with retries ------------------------------------------------
$maxRetries = 3;
$DB = null;
for ($i = 1; $i <= $maxRetries; $i++) {
    try {
        $DB = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        break; // success
    } catch (PDOException $e) {
        if ($i === $maxRetries) {
            http_response_code(500);
            echo "Database connection failed after {$maxRetries} attempts.\n";
            error_log('DB connection error: ' . $e->getMessage());
            exit;
        }
        // wait a little before retrying
        sleep(2);
    }
}
