<!-- File: resources/views/stock/movements/entry.blade.php -->
<h2>New Stock Entry</h2>

<form action="<?= BASE_PATH ?>/stock-movements/handle-entry" method="POST" class="needs-validation" novalidate>
    <?= csrf_field() ?>

    <!-- Item -->
    <div class="form-group">
        <label for="item_id">Item:</label>
        <div class="input-group">
            <select name="item_id" id="item_id" class="form-control" required>
                <option value="">-- Select Item --</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= $item['id']; ?>">
                        <?= htmlspecialchars($item['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary" id="scanBarcodeBtn">
                📷 Scan Barcode
            </button>
        </div>
        <small class="form-text text-muted">Select an item manually or scan its barcode.</small>
    </div>

    <!-- Quantity -->
    <div class="form-group">
        <label for="quantity">Quantity:</label>
        <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
    </div>

    <!-- Supplier (optional) -->
    <div class="form-group">
        <label for="supplier_id">Supplier:</label>
        <select name="supplier_id" id="supplier_id" class="form-control">
            <option value="">-- Select Existing Supplier --</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= $supplier['id']; ?>">
                    <?= htmlspecialchars($supplier['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <small class="form-text text-muted mt-2">Or enter a new supplier name:</small>
        <input type="text" name="raw_supplier_name" id="raw_supplier_name" class="form-control" placeholder="New Supplier Name">
    </div>

    <!-- Warehouse (required) -->
    <div class="form-group">
        <label for="warehouse_id">Destination Warehouse:</label>
        <select name="warehouse_id" id="warehouse_id" class="form-control" required>
            <option value="">-- Select Warehouse --</option>
            <?php foreach ($warehouses as $wh): ?>
                <option value="<?= $wh['id']; ?>">
                    <?= htmlspecialchars($wh['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn btn-primary mt-3">Save Entry</button>
</form>

<script src="<?= BASE_PATH ?>/assets/js/barcode-scanner.js"></script>
<script>
    // Toggle barcode scanner
    const scanBtn = document.getElementById('scanBarcodeBtn');
    scanBtn.addEventListener('click', function () {
        alert("Please connect your wireless barcode scanner or allow camera access.");
        startBarcodeScanner((code) => {
            // Auto-fill item dropdown if barcode matches
            let itemSelect = document.getElementById('item_id');
            let found = false;
            for (let option of itemSelect.options) {
                if (option.text.toLowerCase().includes(code.toLowerCase()) || option.value == code) {
                    option.selected = true;
                    found = true;
                    break;
                }
            }
            if (!found) {
                alert("Scanned code: " + code + " (no matching item found in dropdown)");
            }
        });
    });
</script>
