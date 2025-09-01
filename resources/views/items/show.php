<?php  
// File: resources/views/items/show.php 
// Item detail view (read-only, no editing)

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// ✅ Ensure stock info is available
$stockMovement   = new StockMovement();
$currentStock    = $item['current_stock'] ?? $stockMovement->getItemStock((int)$item['id']);
$warehouseStocks = $item['warehouses'] ?? $stockMovement->getItemStockByWarehouse((int)$item['id']);
?>

<div class="row">
  <div class="col-md-8">
    <h3>Item Details</h3>

    <!-- ✅ Read-only details table -->
    <table class="table table-sm">
      <tr>
        <th>SKU</th>
        <td><?= e($item['sku'] ?? '-') ?></td>
      </tr>
      <tr>
        <th>Name</th>
        <td><?= e($item['name'] ?? '-') ?></td>
      </tr>
      <tr>
        <th>Supplier</th>
        <td><?= e($item['supplier_name'] ?? '-') ?></td>
      </tr>
      <tr>
        <th>Unit Price</th>
        <td><?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
      </tr>
      <tr>
        <th>Total Stock (All Warehouses)</th>
        <td><?= (int)($currentStock ?? 0) ?></td>
      </tr>
      <tr>
        <th>Avg Daily Demand</th>
        <td><?= (int)($item['avg_daily_demand'] ?? 0) ?></td>
      </tr>
      <tr>
        <th>Lead Time (days)</th>
        <td><?= (int)($item['lead_time_days'] ?? 0) ?></td>
      </tr>
      <tr>
        <th>Safety Stock</th>
        <td><?= (int)($item['safety_stock'] ?? 0) ?></td>
      </tr>
      <tr>
        <th>Reorder Point</th>
        <td><?= (int)($item['reorder_point'] ?? 0) ?></td>
      </tr>
    </table>

    <!-- ✅ Only Stock Adjustment button -->
    <div class="d-flex justify-content-end">
      <a href="<?= BASE_PATH ?>/stock-movements/adjustment?id=<?= (int)($item['id'] ?? 0) ?>" 
         class="btn btn-primary">
        ➡ Proceed to Stock Adjustment
      </a>
    </div>

    <!-- ✅ Per-warehouse stock breakdown -->
    <h5 class="mt-4">Stock by Warehouse</h5>
    <?php if (!empty($warehouseStocks)): ?>
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th>Warehouse</th>
            <th>Stock</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($warehouseStocks as $wh): ?>
            <tr>
              <td><?= e($wh['name']) ?></td>
              <td><?= (int)($wh['stock'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-muted">No warehouse stock recorded.</p>
    <?php endif; ?>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-body">
        <h6>Current Stock</h6>
        <p class="display-4"><?= (int)($currentStock ?? 0) ?></p>
        <p class="mb-0"><strong>Reorder Point:</strong> <?= e($item['reorder_point'] ?? 0) ?></p>
      </div>
    </div>
  </div>
</div>
