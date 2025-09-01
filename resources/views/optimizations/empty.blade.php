<?php
// File: resources/views/optimizations/empty.blade.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Optimization Jobs — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <div class="card shadow-sm p-4">
    <h2 class="mb-3">Optimization Jobs</h2>
    <p><?= htmlspecialchars($message ?? 'No optimization jobs available.', ENT_QUOTES, 'UTF-8') ?></p>

    <form action="<?= BASE_PATH ?>/optimizations/run" method="POST">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-primary">
        Run First Optimization
      </button>
    </form>
  </div>
</main>


</body>
</html>
