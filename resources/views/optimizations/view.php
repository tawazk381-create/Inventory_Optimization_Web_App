<?php  
// File: resources/views/optimizations/view.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Expects $job and $results from the controller */
$jobId   = (int)($job['id'] ?? 0);
$status  = $job['status'] ?? 'unknown';
$results = $results ?? [];

// ✅ Normalize BASE_PATH so it never includes '/public'
$actionBase = rtrim(str_replace('/public', '', BASE_PATH), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Optimization Job #<?= $jobId ?> — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= htmlspecialchars($actionBase . '/assets/css/app.css', ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
  <style>
    .progress { height: 20px; }
    .progress-bar { font-size: 12px; line-height: 20px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Optimization Job #<?= $jobId ?></h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <div id="statusCard" class="card mb-3">
    <div class="card-body">
      <p id="statusText">Status: <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></p>
      <div id="progressWrapper" style="<?= ($status === 'complete' || $status === 'failed') ? 'display:none;' : '' ?>">
        <div id="progressCounts" class="mb-1 text-muted"></div>
        <div class="progress">
          <div id="progressBar" class="progress-bar bg-info" role="progressbar"
               style="width:0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
            0%
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="resultsContainer" style="<?= ($status === 'complete' && !empty($results)) ? '' : 'display:none;' ?>">
    <h3>Results</h3>
    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>Item ID</th>
          <th>Item Name</th>
          <th>EOQ</th>
          <th>Reorder Point</th>
          <th>Safety Stock</th>
        </tr>
      </thead>
      <tbody id="resultsTableBody">
        <?php if (!empty($results)): ?>
          <?php foreach ($results as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['item_id'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['eoq'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['reorder_point'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($r['safety_stock'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="mt-3">
      <a href="<?= htmlspecialchars($actionBase . '/optimizations/download-report?job=' . $jobId, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
        ⬇ Download CSV Report
      </a>
    </div>
  </div>

  <div class="mt-3">
    <a href="<?= htmlspecialchars($actionBase . '/optimizations', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">← Back to Jobs</a>
  </div>
</main>

<?php include __DIR__ . '/../partials/footer.php'; ?>

<script>
(function() {
    const jobId = <?= $jobId ?>;
    const statusEl = document.getElementById('statusText');
    const progressWrapper = document.getElementById('progressWrapper');
    const progressCounts = document.getElementById('progressCounts');
    const progressBar = document.getElementById('progressBar');
    const resultsContainer = document.getElementById('resultsContainer');
    const resultsTableBody = document.getElementById('resultsTableBody');

    function fetchJob() {
        fetch('<?= $actionBase ?>/optimizations/job-json?job=' + jobId)
          .then(r => r.json())
          .then(j => {
              if (!j || !j.status) {
                  statusEl.textContent = 'Unable to fetch job status.';
                  setTimeout(fetchJob, 5000);
                  return;
              }

              statusEl.textContent = 'Status: ' + j.status;

              if (typeof j.items_total !== 'undefined' && typeof j.items_processed !== 'undefined') {
                  const total = parseInt(j.items_total, 10) || 0;
                  const processed = parseInt(j.items_processed, 10) || 0;
                  const percent = parseInt(j.progress_percent, 10) || 0;

                  if (total > 0) {
                      progressWrapper.style.display = 'block';
                      progressCounts.textContent = processed + ' / ' + total + ' items';
                      progressBar.style.width = percent + '%';
                      progressBar.setAttribute('aria-valuenow', percent);
                      progressBar.textContent = percent + '%';
                  }
              }

              if (j.status === 'complete' && j.results) {
                  progressWrapper.style.display = 'none';
                  resultsContainer.style.display = 'block';
                  resultsTableBody.innerHTML = "";

                  j.results.forEach(r => {
                      const tr = document.createElement('tr');
                      tr.innerHTML = `
                        <td>${r.item_id}</td>
                        <td>${r.item_name ?? ''}</td>
                        <td>${r.eoq}</td>
                        <td>${r.reorder_point}</td>
                        <td>${r.safety_stock}</td>
                      `;
                      resultsTableBody.appendChild(tr);
                  });
              } else if (j.status === 'failed') {
                  statusEl.textContent += ' — failed';
                  progressWrapper.style.display = 'none';
              } else {
                  setTimeout(fetchJob, 2500);
              }
          })
          .catch(() => setTimeout(fetchJob, 5000));
    }
    fetchJob();
})();
</script>

</body>
</html>
