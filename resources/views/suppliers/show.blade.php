<?php
// File: resources/views/suppliers/show.blade.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Supplier — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Supplier Details</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <div class="row">
    <div class="col-md-8">
      <h3><?= e($supplier['name'] ?? '-') ?></h3>
      <p><strong>Contact:</strong> <?= e($supplier['contact_name'] ?? '-') ?></p>
      <p><strong>Email:</strong> <?= e($supplier['contact_email'] ?? '-') ?></p>
      <p><strong>Phone:</strong> <?= e($supplier['phone'] ?? '-') ?></p>
      <p><strong>Address:</strong><br><?= nl2br(e($supplier['address'] ?? '-')) ?></p>

      <hr>
      <h5>Edit Supplier</h5>
      <form method="post" action="<?= BASE_PATH ?>/suppliers/update">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($supplier['id']) ?>">

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Name</label>
            <input name="name" required class="form-control" value="<?= e($supplier['name']) ?>">
          </div>
          <div class="form-group col-md-6">
            <label>Contact name</label>
            <input name="contact_name" class="form-control" value="<?= e($supplier['contact_name']) ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Email</label>
            <input name="contact_email" type="email" class="form-control" value="<?= e($supplier['contact_email']) ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Phone</label>
            <input name="phone" class="form-control" value="<?= e($supplier['phone']) ?>">
          </div>
          <div class="form-group col-md-2 d-flex align-items-end">
            <button class="btn btn-primary btn-block">Update</button>
          </div>
        </div>

        <div class="form-group">
          <label>Address</label>
          <textarea name="address" class="form-control"><?= e($supplier['address']) ?></textarea>
        </div>
      </form>
    </div>

    <div class="col-md-4">
      <h6>KPIs</h6>
      <?php if (!empty($kpi)): ?>
        <ul class="list-unstyled">
          <li><strong>Total orders:</strong> <?= e($kpi['total_orders'] ?? 0) ?></li>
          <li><strong>Avg order age (days):</strong> <?= e($kpi['avg_order_age_days'] ?? '-') ?></li>
        </ul>
      <?php else: ?>
        <p class="text-muted">No KPI data.</p>
      <?php endif; ?>
    </div>
  </div>

  <hr>
  <a href="<?= BASE_PATH ?>/suppliers" class="btn btn-secondary">← Back to Suppliers</a>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

</body>
</html>
