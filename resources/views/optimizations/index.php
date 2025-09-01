<?php   
// File: resources/views/optimizations/index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($title ?? 'Optimization Jobs', ENT_QUOTES, 'UTF-8') ?> — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
  <style>
    .progress { height: 20px; margin-bottom: 0; }
    .progress-bar { font-size: 12px; line-height: 20px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Optimization Jobs</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <!-- Run a new optimization job -->
  <form method="POST" action="<?= BASE_PATH ?>/optimizations/run" class="mb-3">
    <?= csrf_field() ?>
    <div class="form-row align-items-end">
      <div class="form-group col-md-3">
        <label for="horizon_days">Horizon (days)</label>
        <input type="number" name="horizon_days" id="horizon_days" class="form-control"
               value="90" min="1" required>
      </div>
      <div class="form-group col-md-3">
        <label for="service_level">Service Level</label>
        <input type="number" step="0.01" name="service_level" id="service_level" class="form-control"
               value="0.95" min="0" max="1" required>
      </div>
      <div class="form-group col-md-3">
        <button type="submit" class="btn btn-primary">Run Optimization</button>
      </div>
    </div>
  </form>

  <?php if (!empty($jobs)): ?>
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Horizon (days)</th>
          <th>Service Level</th>
          <th>Status</th>
          <th>Created At</th>
          <th>Completed At</th>
          <th>Items Progress</th>
          <th>Summary</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($jobs as $job): ?>
        <tr>
          <td><?= (int)$job['id'] ?></td>
          <td><?= htmlspecialchars($job['user_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($job['horizon_days'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($job['service_level'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php 
              $status = $job['status'] ?? 'unknown';
              $badgeClass = 'secondary';
              if ($status === 'pending') $badgeClass = 'warning';
              elseif ($status === 'processing') $badgeClass = 'info';
              elseif ($status === 'complete') $badgeClass = 'success';
              elseif ($status === 'failed') $badgeClass = 'danger';
            ?>
            <span class="badge badge-<?= $badgeClass ?>">
              <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
            </span>
          </td>
          <td><?= htmlspecialchars($job['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($job['completed_at'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <?php
              $processed = (int)($job['items_processed'] ?? 0);
              $total = (int)($job['items_total'] ?? 0);
              $percent = $total > 0 ? (int)round(($processed / $total) * 100) : 0;
            ?>
            <?php if ($total > 0): ?>
              <div><?= $processed ?> / <?= $total ?> items</div>
              <div class="progress">
                <div class="progress-bar bg-info" role="progressbar"
                     style="width: <?= $percent ?>%;" 
                     aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100">
                  <?= $percent ?>%
                </div>
              </div>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td>
            <?php if (($job['status'] ?? '') === 'complete'): ?>
                <?= (int)($job['items_processed'] ?? 0) ?> items optimized
            <?php else: ?>
                —
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= BASE_PATH ?>/optimizations/view?job=<?= (int)$job['id'] ?>" 
               class="btn btn-sm btn-info">View</a>
            <?php if (($job['status'] ?? '') === 'complete'): ?>
              <!-- ✅ fixed download route -->
              <a href="<?= BASE_PATH ?>/optimizations/download-report?job=<?= (int)$job['id'] ?>&format=csv" 
                 class="btn btn-sm btn-secondary">Download CSV</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-info">
      <?= htmlspecialchars($message ?? 'No optimization jobs available.', ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php include __DIR__ . '/../partials/pagination.php'; ?>
</main>

</body>
</html>
