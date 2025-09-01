<?php 
// File: resources/views/items/index.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Items — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Items</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <!-- ✅ Button always points to correct plural route -->
  <a href="<?= BASE_PATH ?>/items/create" class="btn btn-success mb-3">➕ Add Item</a>

  <?php if (!empty($items)): ?>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>SKU</th>
        <th>Name</th>
        <th>Supplier</th>
        <th>Unit Price</th>
        <th>Total Stock</th>
        <th>Warehouses</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= e($item['sku']) ?></td>
        <td><?= e($item['name']) ?></td>
        <td><?= e($item['supplier_name'] ?? '-') ?></td>
        <td><?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
        <td>
          <?php 
            // ✅ use total_stock directly from DB
            $stock = (int)($item['total_stock'] ?? 0);
            if ($stock <= ($item['safety_stock'] ?? 0)) {
                echo '<span class="badge bg-danger">'. $stock .'</span>';
            } elseif ($stock <= ($item['reorder_point'] ?? 0)) {
                echo '<span class="badge bg-warning text-dark">'. $stock .'</span>';
            } else {
                echo '<span class="badge bg-success">'. $stock .'</span>';
            }
          ?>
        </td>
        <td>
          <?php if (!empty($item['warehouses'])): ?>
            <ul class="list-unstyled mb-0">
              <?php foreach ($item['warehouses'] as $wh): ?>
                <li><?= e($wh['name']) ?>: <strong><?= (int)($wh['stock'] ?? 0) ?></strong></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <span class="text-muted">-</span>
          <?php endif; ?>
        </td>
        <td class="d-flex gap-1">
          <!-- ✅ View button -->
          <a href="<?= BASE_PATH ?>/items/show?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-primary">View</a>

          <!-- ✅ Delete button (POST form with CSRF + confirm) -->
          <form action="<?= BASE_PATH ?>/items/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this item?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">🗑 Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p class="text-muted">No items found. Click <strong>Add Item</strong> to create your first item.</p>
  <?php endif; ?>

  <?php include __DIR__ . '/../partials/pagination.php'; ?>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

</body>
</html>
