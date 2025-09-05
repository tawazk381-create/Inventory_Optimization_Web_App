<?php  
// File: resources/views/layouts/main.php
// Variables: $title, $file (view path from Controller::view)

require_once __DIR__ . '/../../../app/core/Auth.php';
$auth = new Auth();

$isLoggedIn = $auth->check();
$roleName   = $_SESSION['role_name'] ?? '';
$username   = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title ?? 'Inventory App', ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #f5f6f8;
            --nav-bg: #333;
            --nav-fg: #fff;
            --link: #fff;
            --link-muted: #cfd3d8;
            --accent: #0d6efd;
        }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin:0; padding:0; background:var(--bg); color:#222; }
        header { background:var(--nav-bg); color:var(--nav-fg); }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:12px 0; }
        .brand { font-weight:700; letter-spacing:.2px; }
        nav { background:var(--nav-bg); }
        .nav-inner { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:8px 0; }
        .nav-left, .nav-right { display:flex; align-items:center; gap:16px; }
        .nav-left a, .nav-right a, .nav-right span { color:var(--link); text-decoration:none; font-size:14px; }
        .nav-left a:hover, .nav-right a:hover { color:#eaecef; }

        /* Dropdown */
        .dropdown { position: relative; }
        .dropdown-toggle::after { content: " ▼"; font-size: 10px; }
        .dropdown-menu {
            display: none; position: absolute; background: var(--nav-bg);
            padding: 8px 0; border-radius: 4px; min-width: 160px;
            top: 100%; left: 0; z-index: 1000;
        }
        .dropdown-menu a {
            display: block; padding: 6px 12px; color: var(--link); font-size: 14px;
        }
        .dropdown-menu a:hover { background: #444; }
        .dropdown:hover .dropdown-menu { display: block; }

        main { padding:24px 0; }
        .flash { padding:10px; margin-bottom:15px; border-radius:6px; }
        .flash.success { background:#d4edda; color:#155724; }
        .flash.danger  { background:#f8d7da; color:#721c24; }
        .flash.info    { background:#d1ecf1; color:#0c5460; }

        footer { background:#222; color:#bbb; padding:14px 0; margin-top:24px; }
        footer small { font-size:12px; }
    </style>
</head>
<body>
    <header>
        <div class="container topbar">
            <div class="brand">Inventory Optimization Web App</div>
        </div>
        <nav>
            <div class="container nav-inner">
                <div class="nav-left">
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= BASE_PATH ?>/dashboard">Dashboard</a>
                        <a href="<?= BASE_PATH ?>/items">Items</a>
                        <a href="<?= BASE_PATH ?>/warehouses">Warehouses</a>
                        <a href="<?= BASE_PATH ?>/suppliers">Suppliers</a>

                        <!-- Stock Movements dropdown -->
                        <div class="dropdown">
                            <a href="<?= BASE_PATH ?>/stock-movements" class="dropdown-toggle">Stock Movements</a>
                            <div class="dropdown-menu">
                                <a href="<?= BASE_PATH ?>/stock-movements/entry">Stock Entry</a>
                                <a href="<?= BASE_PATH ?>/stock-movements/exit">Stock Exit</a>
                                <a href="<?= BASE_PATH ?>/stock-movements/transfer">Stock Transfer</a>
                                <a href="<?= BASE_PATH ?>/stock-movements/adjustment">Stock Adjustment</a>
                            </div>
                        </div>

                        <?php if (in_array($roleName, ['Manager','Admin'], true)): ?>
                            <a href="<?= BASE_PATH ?>/reports">Reports</a>
                            <a href="<?= BASE_PATH ?>/optimizations/view">Optimizations</a>
                            <a href="<?= BASE_PATH ?>/classification">Classification</a>
                        <?php endif; ?>

                        <?php if ($roleName === 'Admin'): ?>
                            <a href="<?= BASE_PATH ?>/users/manage">User Management</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Only show Login when NOT logged in -->
                        <a href="<?= BASE_PATH ?>/login">Login</a>
                    <?php endif; ?>
                </div>

                <div class="nav-right">
                    <?php if ($isLoggedIn): ?>
                        <span style="color:var(--link-muted);">
                            <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                            (<?= htmlspecialchars($roleName ?: 'User', ENT_QUOTES, 'UTF-8') ?>)
                        </span>
                        <a href="<?= BASE_PATH ?>/logout" title="Logout">Logout</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container">
            <?php if ($msg = flash('success')): ?>
                <div class="flash success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($msg = flash('danger')): ?>
                <div class="flash danger"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif ($msg = flash('info')): ?>
                <div class="flash info"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php
            // Render the actual view requested by Controller::view()
            if (isset($file) && file_exists($file)) {
                require $file;
            } else {
                echo "<p>View file not found.</p>";
            }
            ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <small>&copy; <?= date('Y') ?> Inventory Optimization Web App</small>
        </div>
    </footer>

    <!-- ✅ CSRF-aware JavaScript for AJAX/Fetch -->
    <script>
    const CSRF_TOKEN = "<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>";

    // Wrapper for fetch() that auto-injects CSRF header
    async function csrfFetch(url, options = {}) {
        options.headers = options.headers || {};
        options.headers['X-CSRF-TOKEN'] = CSRF_TOKEN;

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        return fetch(url, options);
    }

    // jQuery global CSRF header if jQuery is loaded
    if (typeof jQuery !== 'undefined') {
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN }
        });
    }
    </script>
</body>
</html>
