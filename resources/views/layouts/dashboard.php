<?php
// File: resources/views/layouts/dashboard.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-4">Dashboard</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <div class="row g-3">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5>Total Items</h5>
          <p class="fs-4"><?= (int)($stats['total_items'] ?? 0) ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5>Total Stock</h5>
          <p class="fs-4"><?= (int)($stats['total_stock'] ?? 0) ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body">
          <h5>Low Stock Items</h5>
          <p class="fs-4"><?= (int)($stats['low_stock_items'] ?? 0) ?></p>
        </div>
      </div>
    </div>
  </div>

  <h2 class="mt-5">Warehouses</h2>
  <ul>
    <?php foreach (($warehouses ?? []) as $w): ?>
      <li><?= htmlspecialchars($w['name']) ?></li>
    <?php endforeach; ?>
  </ul>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

</body>
</html>
