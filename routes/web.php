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
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if ($uri !== '/dashboard') {
            redirect('/dashboard');
        } else {
            (new DashboardController())->index();
        }
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
