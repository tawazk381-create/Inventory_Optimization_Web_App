<?php
// File: resources/views/stock/movements/exit.blade.php

// ✅ Normalize BASE_PATH so it never includes '/public'
$actionBase = rtrim(str_replace('/public', '', BASE_PATH), '/');
?>
<h2>Stock Exit</h2>

<form action="<?= htmlspecialchars($actionBase . '/stock-movements/handle-exit', ENT_QUOTES, 'UTF-8') ?>" 
      method="POST" 
      class="needs-validation" 
      novalidate>
    <?= csrf_field() ?>

    <!-- Item -->
    <div class="form-group">
        <label for="item_id">Item:</label>
        <select name="item_id" id="item_id" class="form-control" required>
            <option value="">-- Select Item --</option>
            <?php foreach ($items as $item): ?>
                <option value="<?= (int)$item['id']; ?>">
                    <?= htmlspecialchars($item['sku'] . ' - ' . $item['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Warehouse -->
    <div class="form-group">
        <label for="warehouse_id">Warehouse:</label>
        <select name="warehouse_id" id="warehouse_id" class="form-control" required>
            <option value="">-- Select Warehouse --</option>
            <?php foreach ($warehouses as $warehouse): ?>
                <option value="<?= (int)$warehouse['id']; ?>">
                    <?= htmlspecialchars($warehouse['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Quantity -->
    <div class="form-group">
        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
        <small id="stock-hint" class="form-text text-muted"></small>
    </div>

    <button type="submit" class="btn btn-danger mt-3">Save Exit</button>
</form>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const itemSelect = document.getElementById("item_id");
    const whSelect   = document.getElementById("warehouse_id");
    const hint       = document.getElementById("stock-hint");

    async function updateHint() {
        const itemId = itemSelect.value;
        const whId   = whSelect.value;
        if (!itemId || !whId) {
            hint.textContent = "";
            return;
        }
        try {
            // 🔄 Call an endpoint that returns stock for item+warehouse
            const resp = await fetch("<?= htmlspecialchars($actionBase, ENT_QUOTES, 'UTF-8') ?>/api/stock/check?item_id=" + itemId + "&warehouse_id=" + whId);
            const data = await resp.json();
            if (data && typeof data.stock !== "undefined") {
                hint.textContent = `Available stock in this warehouse: ${data.stock}`;
            } else {
                hint.textContent = "";
            }
        } catch (e) {
            hint.textContent = "";
        }
    }

    itemSelect.addEventListener("change", updateHint);
    whSelect.addEventListener("change", updateHint);
});
</script>
