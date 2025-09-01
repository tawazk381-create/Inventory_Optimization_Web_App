<?php
// File: resources/views/suppliers/edit.blade.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Supplier — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Edit Supplier</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <?php if (!empty($supplier)): ?>
  <form method="post" action="<?= BASE_PATH ?>/suppliers/update" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($supplier['id']) ?>">

    <div class="form-row">
      <div class="form-group col-md-6">
        <label for="name">Supplier Name</label>
        <input type="text" class="form-control" id="name" name="name"
               value="<?= e($supplier['name']) ?>" required>
      </div>
      <div class="form-group col-md-6">
        <label for="contact_name">Contact Name</label>
        <input type="text" class="form-control" id="contact_name" name="contact_name"
               value="<?= e($supplier['contact_name']) ?>">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group col-md-6">
        <label for="contact_email">Email</label>
        <input type="email" class="form-control" id="contact_email" name="contact_email"
               value="<?= e($supplier['contact_email']) ?>">
      </div>
      <div class="form-group col-md-6">
        <label for="phone">Phone</label>
        <input type="text" class="form-control" id="phone" name="phone"
               value="<?= e($supplier['phone']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="address">Address</label>
      <textarea class="form-control" id="address" name="address" rows="3"><?= e($supplier['address']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Update Supplier</button>
    <a href="<?= BASE_PATH ?>/suppliers/show?id=<?= (int)$supplier['id'] ?>" class="btn btn-secondary">Cancel</a>
  </form>
  <?php else: ?>
    <p class="text-danger">Supplier not found.</p>
    <a href="<?= BASE_PATH ?>/suppliers" class="btn btn-secondary">← Back to Suppliers</a>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

</body>
</html>
