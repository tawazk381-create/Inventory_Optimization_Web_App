<?php
// File: resources/views/warehouses/show.php
// Single warehouse detail view (standalone)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Warehouse Details — Inventory Optimization</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<main class="container mt-4">
    <h2>🏭 Warehouse: <?= e($warehouse['name'] ?? '-') ?></h2>

    <?php include __DIR__ . '/../partials/flash.php'; ?>

    <div class="card mb-3">
        <div class="card-body">
            <p><strong>📍 Location:</strong> <?= e($warehouse['location'] ?? '-') ?></p>
            <p><strong>📞 Contact:</strong> <?= e($warehouse['contact'] ?? '-') ?></p>
        </div>
    </div>

    <div class="d-flex gap-2">
        <!-- Edit -->
        <a href="<?= BASE_PATH ?>/warehouses/edit?id=<?= e($warehouse['id']) ?>" class="btn btn-primary">
            ✏️ Edit
        </a>

        <!-- Delete -->
        <form action="<?= BASE_PATH ?>/warehouses/delete" method="POST"
              onsubmit="return confirm('Are you sure you want to delete this warehouse?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($warehouse['id']) ?>">
            <button type="submit" class="btn btn-danger">🗑️ Delete</button>
        </form>

        <!-- Back -->
        <a href="<?= BASE_PATH ?>/warehouses" class="btn btn-secondary">⬅ Back to Warehouses</a>
    </div>
</main>

</body>
</html>
