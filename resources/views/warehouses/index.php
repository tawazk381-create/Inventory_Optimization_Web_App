<?php
// File: resources/views/warehouses/index.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Warehouses — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Warehouses</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <!-- ✅ Fixed: pluralized route -->
  <a href="<?= BASE_PATH ?>/warehouses/create" class="btn btn-success mb-3">➕ Add Warehouse</a>

  <table class="table table-striped">
    <thead>
      <tr>
        <th>Name</th>
        <th>Location</th>
        <th>Contact</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($warehouses ?? []) as $w): ?>
      <tr>
        <td><?= htmlspecialchars($w['name']) ?></td>
        <td><?= htmlspecialchars($w['location'] ?? '-') ?></td>
        <td><?= htmlspecialchars($w['contact'] ?? '-') ?></td>
        <td>
          <!-- ✅ Fixed: pluralized route -->
          <a href="<?= BASE_PATH ?>/warehouses/show?id=<?= (int)$w['id'] ?>" class="btn btn-sm btn-primary">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php include __DIR__ . '/../partials/pagination.php'; ?>
</main>

</body>
</html>
