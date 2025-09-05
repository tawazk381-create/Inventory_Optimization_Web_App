<?php
// File: resources/views/stock/movements/adjustment.blade.php

if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Normalize BASE_PATH so it never includes '/public'
$actionBase = rtrim(str_replace('/public', '', BASE_PATH), '/');

include __DIR__ . '/../../partials/nav.php';
include __DIR__ . '/../../partials/flash.php';
?>
<div class="container mt-4">
    <h1 class="mb-4">🛠 Stock Adjustment & Item Update</h1>

    <!-- ========================= -->
    <!-- Item Details Update Form  -->
    <!-- ========================= -->
    <form method="POST" action="<?= htmlspecialchars($actionBase . '/stock-movements/handle-item-update', ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>

        <!-- Item selector -->
        <div class="mb-3">
            <label for="item_id" class="form-label">Item</label>
            <select name="item_id" id="item_id" class="form-select" required>
                <option value="">-- Select Item --</option>
                <?php foreach ($items as $it): ?>
                    <option value="<?= (int)$it['id'] ?>"
                            data-sku="<?= htmlspecialchars($it['sku'], ENT_QUOTES, 'UTF-8') ?>"
                            data-name="<?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-supplier="<?= htmlspecialchars($it['supplier_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-unit_price="<?= htmlspecialchars($it['unit_price'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                            data-demand="<?= htmlspecialchars($it['avg_daily_demand'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                            data-lead="<?= htmlspecialchars($it['lead_time_days'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                            data-safety="<?= htmlspecialchars($it['safety_stock'] ?? 0, ENT_QUOTES, 'UTF-8') ?>"
                            data-reorder="<?= htmlspecialchars($it['reorder_point'] ?? 0, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($it['sku'] . ' — ' . $it['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <hr>
        <h4>📦 Item Details</h4>

        <!-- SKU -->
        <div class="mb-3">
            <label for="sku" class="form-label">SKU</label>
            <input type="text" name="sku" id="sku" class="form-control">
        </div>

        <!-- Name -->
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" name="name" id="name" class="form-control">
        </div>

        <!-- Supplier -->
        <div class="mb-3">
            <label for="supplier_id" class="form-label">Supplier</label>
            <select name="supplier_id" id="supplier_id" class="form-select">
                <option value="">-- Select Supplier --</option>
                <?php foreach ((new Supplier())->all() as $sup): ?>
                    <option value="<?= (int)$sup['id'] ?>"><?= htmlspecialchars($sup['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Unit Price -->
        <div class="mb-3">
            <label for="unit_price" class="form-label">Unit Price</label>
            <input type="number" step="0.01" name="unit_price" id="unit_price" class="form-control">
        </div>

        <!-- Avg Daily Demand -->
        <div class="mb-3">
            <label for="avg_daily_demand" class="form-label">Avg Daily Demand</label>
            <input type="number" name="avg_daily_demand" id="avg_daily_demand" class="form-control">
        </div>

        <!-- Lead Time -->
        <div class="mb-3">
            <label for="lead_time_days" class="form-label">Lead Time (days)</label>
            <input type="number" name="lead_time_days" id="lead_time_days" class="form-control">
        </div>

        <!-- Safety Stock -->
        <div class="mb-3">
            <label for="safety_stock" class="form-label">Safety Stock</label>
            <input type="number" name="safety_stock" id="safety_stock" class="form-control">
        </div>

        <!-- Reorder Point -->
        <div class="mb-3">
            <label for="reorder_point" class="form-label">Reorder Point</label>
            <input type="number" name="reorder_point" id="reorder_point" class="form-control">
        </div>

        <!-- ✅ Save Button for Item Details -->
        <div class="mb-4">
            <button type="submit" class="btn btn-primary">💾 Save Item Details</button>
        </div>
    </form>

    <hr>

    <!-- ========================= -->
    <!-- Stock Adjustment Form     -->
    <!-- ========================= -->
    <form method="POST" action="<?= htmlspecialchars($actionBase . '/stock-movements/handle-adjustment', ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>

        <h4>⚖️ Stock Adjustment</h4>

        <!-- Item selector (needed for adjustment too) -->
        <div class="mb-3">
            <label for="adjust_item_id" class="form-label">Item</label>
            <select name="item_id" id="adjust_item_id" class="form-select" required>
                <option value="">-- Select Item --</option>
                <?php foreach ($items as $it): ?>
                    <option value="<?= (int)$it['id'] ?>">
                        <?= htmlspecialchars($it['sku'] . ' — ' . $it['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Warehouse -->
        <div class="mb-3">
            <label for="warehouse_id" class="form-label">Warehouse</label>
            <select name="warehouse_id" id="warehouse_id" class="form-select" required>
                <option value="">-- Select Warehouse --</option>
                <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= (int)$wh['id'] ?>"><?= htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Quantity -->
        <div class="mb-3">
            <label for="quantity" class="form-label">Quantity Adjustment</label>
            <input type="number" name="quantity" id="quantity" class="form-control" required>
            <small class="form-text text-muted">Positive = add stock, Negative = reduce stock.</small>
        </div>

        <!-- Reason -->
        <div class="mb-3">
            <label for="reason" class="form-label">Reason</label>
            <input type="text" name="reason" id="reason" class="form-control"
                   placeholder="e.g. Damaged, Lost, Audit Correction" required>
        </div>

        <!-- Save Button -->
        <button type="submit" class="btn btn-warning">💾 Save Stock Adjustment</button>
        <a href="<?= htmlspecialchars($actionBase . '/stock-movements', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>

<script>
document.getElementById('item_id').addEventListener('change', function() {
    let opt = this.options[this.selectedIndex];
    if (!opt || !opt.dataset) return;

    document.getElementById('sku').value             = opt.dataset.sku || '';
    document.getElementById('name').value            = opt.dataset.name || '';
    document.getElementById('supplier_id').value     = opt.dataset.supplier || '';
    document.getElementById('unit_price').value      = opt.dataset.unit_price || '';
    document.getElementById('avg_daily_demand').value= opt.dataset.demand || '';
    document.getElementById('lead_time_days').value  = opt.dataset.lead || '';
    document.getElementById('safety_stock').value    = opt.dataset.safety || '';
    document.getElementById('reorder_point').value   = opt.dataset.reorder || '';
});
</script>
