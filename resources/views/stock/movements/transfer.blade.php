<?php
// File: resources/views/stock/movements/transfer.blade.php

if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Normalize BASE_PATH so it never includes '/public'
$actionBase = rtrim(str_replace('/public', '', BASE_PATH), '/');

include __DIR__ . '/../../partials/nav.php';
include __DIR__ . '/../../partials/flash.php';
?>
<div class="container mt-4">
    <h1 class="mb-4">🔄 Stock Transfer</h1>

    <form method="POST" action="<?= htmlspecialchars($actionBase . '/stock-movements/handle-transfer', ENT_QUOTES, 'UTF-8') ?>">
        <?= csrf_field() ?>

        <!-- Item -->
        <div class="mb-3">
            <label for="item_id" class="form-label">Item</label>
            <select name="item_id" id="item_id" class="form-select" required>
                <option value="">-- Select Item --</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= (int)$item['id'] ?>">
                        <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Quantity -->
        <div class="mb-3">
            <label for="quantity" class="form-label">Quantity</label>
            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
        </div>

        <!-- From Warehouse -->
        <div class="mb-3">
            <label for="from_warehouse_id" class="form-label">From Warehouse</label>
            <select name="from_warehouse_id" id="from_warehouse_id" class="form-select" required>
                <option value="">-- Select Source --</option>
                <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= (int)$wh['id'] ?>">
                        <?= htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- To Warehouse -->
        <div class="mb-3">
            <label for="to_warehouse_id" class="form-label">To Warehouse</label>
            <select name="to_warehouse_id" id="to_warehouse_id" class="form-select" required>
                <option value="">-- Select Destination --</option>
                <?php foreach ($warehouses as $wh): ?>
                    <option value="<?= (int)$wh['id'] ?>">
                        <?= htmlspecialchars($wh['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Submit Transfer</button>
        <a href="<?= htmlspecialchars($actionBase . '/stock-movements', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../../partials/footer.php'; ?>
