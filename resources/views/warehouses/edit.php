<?php
// File: resources/views/warehouses/edit.php
// Edit warehouse form (standalone)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Warehouse — Inventory Optimization</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<main class="container mt-4">
    <h2 class="mb-3">✏️ Edit Warehouse</h2>

    <?php include __DIR__ . '/../partials/flash.php'; ?>

    <form method="POST" action="<?= BASE_PATH ?>/warehouses/update">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($warehouse['id']) ?>">

        <!-- Name -->
        <div class="mb-3">
            <label for="name" class="form-label">Warehouse Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" 
                   value="<?= e($warehouse['name']) ?>" required>
        </div>

        <!-- Location -->
        <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <input type="text" name="location" id="location" class="form-control" 
                   value="<?= e($warehouse['location']) ?>">
        </div>

        <!-- Contact -->
        <div class="mb-3">
            <label for="contact" class="form-label">Contact</label>
            <input type="text" name="contact" id="contact" class="form-control" 
                   value="<?= e($warehouse['contact']) ?>">
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">💾 Update Warehouse</button>
            <a href="<?= BASE_PATH ?>/warehouses/show?id=<?= e($warehouse['id']) ?>" class="btn btn-secondary">⬅ Cancel</a>
        </div>
    </form>
</main>

</body>
</html>
