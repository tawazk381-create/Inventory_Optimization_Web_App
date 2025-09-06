<?php    
// File: routes/web.php

declare(strict_types=1);

// ✅ Ensure middleware is available
require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';

/** @var Router $router */

// ----------------------
// Root → send guests to login, users to dashboard
// ----------------------
$router->add('GET', '/', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        redirect('/login');
    } else {
        // ✅ Always normalize to /dashboard (never /public/dashboard)
        redirect('/dashboard');
    }
});

// ----------------------
// Authentication
// ----------------------
$router->add('GET',  '/login',  'AuthController@showLogin');
$router->add('POST', '/login',  'AuthController@login');
$router->add('GET',  '/logout', 'AuthController@logout');

// ----------------------
// User Management → Admin only
// ----------------------
$router->add('GET',  '/users/register', function () {
    AuthMiddleware::check(['Admin']);
    (new RegisterController())->showForm();
});
$router->add('POST', '/users/register', function () {
    AuthMiddleware::check(['Admin']);
    (new RegisterController())->handleRegister();
});

// ➕ Manage users list
$router->add('GET', '/users/manage', function () {
    AuthMiddleware::check(['Admin']);
    (new RegisterController())->manageUsers();
});

// 🗑️ Delete user (POST with ID in URL)
$router->add('POST', '/users/delete/(\d+)', function ($id) {
    AuthMiddleware::check(['Admin']);
    (new RegisterController())->deleteUser((int)$id);
});

// ----------------------
// Dashboard → all logged-in users
// ----------------------
$router->add('GET', '/dashboard', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new DashboardController())->index();
});

// ----------------------
// Items → Staff, Manager, Admin
// ----------------------
$router->add('GET', '/items', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->index();
});
$router->add('GET', '/items/show', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->show();
});

// ➕ Create
$router->add('GET', '/items/create', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->create();
});
$router->add('POST', '/items/store', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->store();
});

// ✏️ Edit + Update
$router->add('GET', '/items/edit', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->edit();
});
$router->add('POST', '/items/update', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->update();
});

// 🗑️ Delete
$router->add('POST', '/items/delete', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new ItemController())->delete();
});

// ----------------------
// Stock Movements
// ----------------------
$router->add('GET', '/stock-movements', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->index();
});

// ➕ Stock Entry
$router->add('GET', '/stock-movements/entry', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->entryForm();
});
$router->add('POST', '/stock-movements/handle-entry', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->handleEntry();
});

// ➖ Stock Exit
$router->add('GET', '/stock-movements/exit', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->exitForm();
});
$router->add('POST', '/stock-movements/handle-exit', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->handleExit();
});

// 🔄 Stock Transfer
$router->add('GET', '/stock-movements/transfer', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->transferForm();
});
$router->add('POST', '/stock-movements/handle-transfer', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->handleTransfer();
});

// 🛠 Stock Adjustment
$router->add('GET', '/stock-movements/adjustment', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->adjustmentForm();
});
$router->add('POST', '/stock-movements/handle-item-update', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->handleItemUpdate();
});
$router->add('POST', '/stock-movements/handle-adjustment', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new StockController())->handleAdjustment();
});

// ----------------------
// Optimization → Manager, Admin
// ----------------------
$router->add('GET', '/optimizations', function () {
    AuthMiddleware::check(['Manager','Admin']);
    (new OptimizationController())->index();
});
$router->add('POST', '/optimizations/run', function () {
    AuthMiddleware::check(['Manager','Admin']);
    (new OptimizationController())->run();
});
$router->add('GET', '/optimizations/view', function () {
    AuthMiddleware::check(['Manager','Admin']);
    (new OptimizationController())->viewPage();
});
$router->add('GET', '/optimizations/job-json', function () {
    AuthMiddleware::check(['Manager','Admin']);
    (new OptimizationController())->getJobJson();
});

// ----------------------
// Optimizations Download Report (CSV / JSON)
// ----------------------
$router->add('GET', '/optimizations/download-report', function () {
    AuthMiddleware::check(['Manager', 'Admin']);
    (new OptimizationController())->downloadReport();
});

// ----------------------
// Suppliers → Staff, Manager, Admin
// ----------------------
$router->add('GET', '/suppliers', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->index();
});
$router->add('GET', '/suppliers/show', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->show();
});

// ➕ Create
$router->add('GET', '/suppliers/create', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->create();
});
$router->add('POST', '/suppliers/store', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->store();
});

// ✏️ Edit + Update
$router->add('GET', '/suppliers/edit', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->edit();
});
$router->add('POST', '/suppliers/update', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->update();
});

// 🗑️ Delete
$router->add('POST', '/suppliers/delete', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new SupplierController())->delete();
});

// ----------------------
// Warehouses → Staff, Manager, Admin
// ----------------------
$router->add('GET', '/warehouses', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->index();
});
$router->add('GET', '/warehouses/show', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->show();
});

// ➕ Create
$router->add('GET', '/warehouses/create', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->create();
});
$router->add('POST', '/warehouses/store', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->store();
});

// ✏️ Edit + Update
$router->add('GET', '/warehouses/edit', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->edit();
});
$router->add('POST', '/warehouses/update', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->update();
});

// 🗑️ Delete
$router->add('POST', '/warehouses/delete', function () {
    AuthMiddleware::check(['Staff','Manager','Admin']);
    (new WarehouseController())->delete();
});

// ----------------------
// Reports → Manager, Admin
// ----------------------
$router->add('GET', '/reports', function () {
    AuthMiddleware::check(['Manager','Admin']);
    (new ReportController())->index();
});

// ----------------------
// Classification → Manager, Admin
// ----------------------
$router->add('GET', '/classification', function () {
    AuthMiddleware::check(['Manager','Admin']);
    (new ClassificationController())->index();
});

// Add this route near your other routes in routes/web.php
$router->get('/run_worker', function () {
    // Quick JSON responder
    header('Content-Type: application/json; charset=utf-8');

    // Load expected secret from environment (or fallback value)
    $expected = getenv('WORKER_SECRET') ?: 'V1y7q9Gk3Fh2Zs8LxP0rT6uN5dC4bQeR';
    $provided = $_GET['secret'] ?? '';

    // Simple auth
    if (!is_string($provided) || trim($provided) === '' || $provided !== $expected) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        return;
    }

    // log helper (append)
    $root = dirname(__DIR__);
    $logDir = $root . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
    $logFile = $logDir . '/optimization_service.log';
    $log = function($msg) use ($logFile) {
        @file_put_contents($logFile, '[' . date('c') . '] [RUN_ROUTE] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    };

    // Worker script path and php binary
    $php = PHP_BINARY ?: 'php';
    $script = realpath(__DIR__ . '/../app/workers/optimization_worker.php');
    if ($script === false) {
        $log("Worker script not found at expected path.");
        echo json_encode(['ok' => false, 'error' => 'Worker script not found']);
        return;
    }

    // Build command; return pid if possible
    $cmd = escapeshellcmd($php) . ' ' . escapeshellarg($script) . ' > /dev/null 2>&1 & echo $!';

    $attempts = [];
    $success = false;
    $pid = null;

    // Try exec()
    if (!$success && function_exists('exec')) {
        try {
            @exec($cmd, $output, $ret);
            $attempts[] = ['method' => 'exec', 'ret' => $ret, 'output' => $output];
            if (!empty($output) && is_array($output)) {
                $pidLine = trim(end($output));
                if ($pidLine !== '') {
                    $pid = $pidLine;
                    $success = true;
                }
            } elseif ($ret === 0) {
                $success = true;
            }
        } catch (Throwable $t) {
            $attempts[] = ['method' => 'exec', 'error' => $t->getMessage()];
        }
    }

    // Try shell_exec()
    if (!$success && function_exists('shell_exec')) {
        try {
            $out = @shell_exec($cmd);
            $attempts[] = ['method' => 'shell_exec', 'output' => $out];
            if (is_string($out) && trim($out) !== '') {
                $pid = trim($out);
                $success = true;
            } elseif ($out === null) {
                // not successful but attempted
            } else {
                $success = true;
            }
        } catch (Throwable $t) {
            $attempts[] = ['method' => 'shell_exec', 'error' => $t->getMessage()];
        }
    }

    // Try proc_open()
    if (!$success && function_exists('proc_open')) {
        try {
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = @proc_open($cmd, $descriptorspec, $pipes);
            if (is_resource($process)) {
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
        } catch (Throwable $t) {
            $attempts[] = ['method' => 'proc_open', 'error' => $t->getMessage()];
        }
    }

    if ($success) {
        $msg = 'Worker triggered' . ($pid ? " (pid: {$pid})" : '');
        $log($msg . ' attempts: ' . json_encode($attempts));
        echo json_encode(['ok' => true, 'pid' => $pid, 'attempts' => $attempts]);
        return;
    }

    // Nothing worked
    $log('Failed to trigger worker; attempts: ' . json_encode($attempts));
    echo json_encode(['ok' => false, 'attempts' => $attempts]);
});
