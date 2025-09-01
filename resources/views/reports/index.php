<?php 
// File: resources/views/reports/index.php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports — Inventory Optimization</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="<?= BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
  <script src="<?= BASE_PATH ?>/assets/js/reports.js"></script>
</head>
<body>

<?php include __DIR__ . '/../partials/nav.php'; ?>

<main class="container mt-4">
  <h1 class="mb-3">Reports</h1>
  <?php include __DIR__ . '/../partials/flash.php'; ?>

  <!-- 🔹 Summary Section -->
  <section class="mb-4">
    <h2>Inventory Summary</h2>
    <p><strong>Total Inventory Value:</strong> $<?= number_format($totalValue, 2) ?></p>

    <?php if (!empty($lowStock)): ?>
      <h3>⚠️ Low Stock Items</h3>
      <table class="table table-sm table-bordered">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Current Stock</th>
            <th>Safety Stock</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lowStock as $item): ?>
            <tr>
              <td><?= htmlspecialchars($item['sku']) ?></td>
              <td><?= htmlspecialchars($item['name']) ?></td>
              <td><?= (int)$item['current_stock'] ?></td>
              <td><?= (int)$item['safety_stock'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>✅ No items are currently below safety stock.</p>
    <?php endif; ?>
  </section>

  <!-- 🔹 Optimization Results Section -->
  <section class="mb-4">
    <h2>Latest Optimization Results</h2>

    <?php if (!empty($optimizationResults)): ?>
      <table class="table table-sm table-striped table-bordered">
        <thead>
          <tr>
            <th>Item ID</th>
            <th>SKU</th>
            <th>Name</th>
            <th>EOQ</th>
            <th>Reorder Point</th>
            <th>Safety Stock</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($optimizationResults as $res): ?>
            <tr>
              <td><?= (int)$res['item_id'] ?></td>
              <td><?= htmlspecialchars($res['sku']) ?></td>
              <td><?= htmlspecialchars($res['name']) ?></td>
              <td><?= (int)$res['eoq'] ?></td>
              <td><?= (int)$res['reorder_point'] ?></td>
              <td><?= (int)$res['safety_stock'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No optimization results available yet.</p>
    <?php endif; ?>
  </section>

  <!-- 🔹 Stock Movements Section -->
  <section class="mb-4">
    <h2>Recent Stock Movements (Last 7 Days)</h2>

    <?php if (!empty($movements)): ?>
      <table class="table table-sm table-striped table-bordered">
        <thead>
          <tr>
            <th>Date</th>
            <th>SKU</th>
            <th>Item</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Reference</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $mv): ?>
            <tr>
              <td><?= htmlspecialchars($mv['created_at']) ?></td>
              <td><?= htmlspecialchars($mv['sku']) ?></td>
              <td><?= htmlspecialchars($mv['name']) ?></td>
              <td><?= htmlspecialchars($mv['movement_type']) ?></td>
              <td><?= (int)$mv['quantity'] ?></td>
              <td><?= htmlspecialchars($mv['reference'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>No stock movements recorded in the last 7 days.</p>
    <?php endif; ?>
  </section>

  <!-- 🔹 Manual Fetch & Download Section -->
  <section>
    <h2>Download Optimization Report</h2>
    <p>Enter Job ID to fetch optimization results:</p>
    <input type="text" id="jobIdInput" placeholder="Enter Job ID">
    <button id="downloadCsvButton" disabled>Download CSV Report</button>
    <button id="downloadJsonButton" disabled>Download JSON Report</button>

    <table id="resultsTable" class="table table-sm mt-3"></table>
  </section>
</main>

</body>
</html>
