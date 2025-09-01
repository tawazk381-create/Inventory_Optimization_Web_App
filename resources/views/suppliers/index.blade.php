<?php
// File: resources/views/suppliers/index.blade.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Suppliers — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Suppliers</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <a href="<?= BASE_PATH ?>/suppliers/create" class="btn btn-success mb-3">Add Supplier</a>

  <table class="table table-striped">
    <thead>
      <tr>
        <th>Name</th>
        <th>Contact</th>
        <th>Email</th>
        <th>Phone</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($suppliers)): ?>
        <?php foreach ($suppliers as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['contact_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($s['contact_email'] ?? '-') ?></td>
          <td><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
          <td>
            <a href="<?= BASE_PATH ?>/suppliers/show?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-primary">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5" class="text-muted text-center">No suppliers found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php include __DIR__ . '/../partials/pagination.php'; ?>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

</body>
</html>
