<?php
// File: resources/views/dashboard/index.php
// Variables: $user (logged-in user details)
?>
<h2>Dashboard - <?= htmlspecialchars($_SESSION['role_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></h2>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out;
    }
    .card:hover {
        transform: translateY(-5px);
    }
    .icon {
        font-size: 40px;
        margin-bottom: 10px;
    }
    .card h3 {
        margin: 10px 0 5px;
    }
    .card p {
        font-size: 0.9em;
        color: #555;
    }
    .card a {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 15px;
        border-radius: 6px;
        background: #007BFF;
        color: #fff;
        text-decoration: none;
        transition: background 0.2s;
    }
    .card a:hover {
        background: #0056b3;
    }
</style>

<div class="dashboard-grid">
    <div class="card">
        <div class="icon">📦</div>
        <h3>Items</h3>
        <p>View and manage items</p>
        <a href="<?= BASE_PATH ?>/items">Go</a>
    </div>

    <div class="card">
        <div class="icon">➕</div>
        <h3>Stock Entry</h3>
        <p>Record new stock arrivals</p>
        <a href="<?= BASE_PATH ?>/stock-movements/entry">Go</a>
    </div>

    <div class="card">
        <div class="icon">📤</div>
        <h3>Stock Exit</h3>
        <p>Record outgoing stock</p>
        <a href="<?= BASE_PATH ?>/stock-movements/exit">Go</a>
    </div>

    <div class="card">
        <div class="icon">🔄</div>
        <h3>Stock Transfer</h3>
        <p>Move stock between warehouses</p>
        <a href="<?= BASE_PATH ?>/stock-movements/transfer">Go</a>
    </div>

    <div class="card">
        <div class="icon">⚖️</div>
        <h3>Stock Adjustment</h3>
        <p>Correct stock discrepancies</p>
        <a href="<?= BASE_PATH ?>/stock-movements/adjustment">Go</a>
    </div>

    <div class="card">
        <div class="icon">🚚</div>
        <h3>Suppliers</h3>
        <p>Manage supplier information</p>
        <a href="<?= BASE_PATH ?>/suppliers">Go</a>
    </div>

    <div class="card">
        <div class="icon">🏭</div>
        <h3>Warehouses</h3>
        <p>Manage warehouse locations</p>
        <a href="<?= BASE_PATH ?>/warehouses">Go</a>
    </div>

    <?php if (in_array($_SESSION['role_name'] ?? '', ['Manager','Admin'], true)): ?>
        <div class="card">
            <div class="icon">📊</div>
            <h3>Reports</h3>
            <p>View analytics and reports</p>
            <a href="<?= BASE_PATH ?>/reports">Go</a>
        </div>

        <div class="card">
            <div class="icon">⚙️</div>
            <h3>Optimizations</h3>
            <p>Run and view optimization results</p>
            <a href="<?= BASE_PATH ?>/optimizations/view">Go</a>
        </div>

        <div class="card">
            <div class="icon">📂</div>
            <h3>Classification</h3>
            <p>Manage item classifications</p>
            <a href="<?= BASE_PATH ?>/classification">Go</a>
        </div>
    <?php endif; ?>

    <?php if (($_SESSION['role_name'] ?? '') === 'Admin'): ?>
        <div class="card">
            <div class="icon">👥</div>
            <h3>User Management</h3>
            <p>Register and manage system users</p>
            <!-- ✅ FIXED: point to /users/manage instead of /users/register -->
            <a href="<?= BASE_PATH ?>/users/manage">Go</a>
        </div>
    <?php endif; ?>
</div>
