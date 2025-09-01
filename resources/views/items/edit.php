<?php
// File: resources/views/items/edit.php

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<h1><?= e($title ?? 'Edit Item') ?></h1>

<!-- Edit Item Form -->
<form action="<?= base_path('/items/update') ?>" method="POST" class="mb-4">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= e($item['id'] ?? '') ?>">

  <div class="form-row">
    <div class="form-group col-md-4">
      <label for="sku">SKU</label>
      <input type="text" id="sku" name="sku" class="form-control"
             value="<?= e($item['sku'] ?? '') ?>" required>
    </div>
    <div class="form-group col-md-8">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" class="form-control"
             value="<?= e($item['name'] ?? '') ?>" required>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group col-md-3">
      <label for="unit_price">Unit Price</label>
      <input type="number" step="0.01" id="unit_price" name="unit_price"
             class="form-control"
             value="<?= e($item['unit_price'] ?? '') ?>">
    </div>
    <div class="form-group col-md-3">
      <label for="total_stock">Total Stock (pcs)</label>
      <!-- ✅ new field bound to total_stock column -->
      <input type="number" id="total_stock" name="total_stock" class="form-control"
             value="<?= e($item['total_stock'] ?? 0) ?>" min="0">
    </div>
    <div class="form-group col-md-3">
      <label for="safety_stock">Safety Stock</label>
      <input type="number" id="safety_stock" name="safety_stock" class="form-control"
             value="<?= e($item['safety_stock'] ?? '') ?>">
    </div>
    <div class="form-group col-md-3">
      <label for="reorder_point">Reorder Point</label>
      <input type="number" id="reorder_point" name="reorder_point" class="form-control"
             value="<?= e($item['reorder_point'] ?? '') ?>">
    </div>
  </div>

  <!-- Supplier -->
  <div class="form-group">
    <label for="supplier_id">Supplier</label>
    <select id="supplier_id" name="supplier_id" class="form-control">
      <option value="">-- Select Supplier --</option>
      <?php foreach (($suppliers ?? []) as $supplier): ?>
        <option value="<?= e($supplier['id']) ?>"
          <?= ($item['supplier_id'] ?? null) == $supplier['id'] ? 'selected' : '' ?>>
          <?= e($supplier['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="text" id="supplier_name" name="supplier_name"
           class="form-control mt-2"
           placeholder="Or type new supplier"
           value="<?= e($item['supplier_name'] ?? '') ?>">
  </div>

  <!-- Warehouse -->
  <div class="form-group">
    <label for="warehouse_id">Warehouse</label>
    <select id="warehouse_id" name="warehouse_id" class="form-control" required>
      <option value="">-- Select Warehouse --</option>
      <?php foreach (($warehouses ?? []) as $wh): ?>
        <option value="<?= e($wh['id']) ?>"
          <?= ($item['warehouse_id'] ?? null) == $wh['id'] ? 'selected' : '' ?>>
          <?= e($wh['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label for="description">Description</label>
    <textarea id="description" name="description" class="form-control" rows="3"><?= e($item['description'] ?? '') ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary">Save</button>
  <a href="<?= base_path('/items') ?>" class="btn btn-secondary">Cancel</a>
</form>

<!-- Record Stock Movement -->
<h2>Record Stock Movement</h2>
<form action="<?= base_path('/stock-movements/handle-entry') ?>" method="POST" class="mb-4">
  <?= csrf_field() ?>
  <input type="hidden" name="item_id" value="<?= e($item['id'] ?? '') ?>">

  <div class="form-row">
    <div class="form-group col-md-4">
      <label for="quantity">Quantity</label>
      <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
    </div>
    <div class="form-group col-md-4">
      <label for="source_type">Source Type</label>
      <select id="source_type" name="source_type" class="form-control" required>
        <option value="">-- Select --</option>
        <option value="supplier">Supplier</option>
        <option value="warehouse">Warehouse</option>
      </select>
    </div>
    <div class="form-group col-md-4" id="source_supplier" style="display:none;">
      <label for="source_id_supplier">Supplier</label>
      <select id="source_id_supplier" name="source_id" class="form-control">
        <option value="">-- Select Supplier --</option>
        <?php foreach (($suppliers ?? []) as $supplier): ?>
          <option value="<?= e($supplier['id']) ?>"><?= e($supplier['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group" id="source_warehouse" style="display:none;">
    <label for="source_id_warehouse">Warehouse</label>
    <select id="source_id_warehouse" name="source_id" class="form-control">
      <option value="">-- Select Warehouse --</option>
      <?php foreach (($warehouses ?? []) as $wh): ?>
        <option value="<?= e($wh['id']) ?>"><?= e($wh['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <button type="submit" class="btn btn-success">Record Movement</button>
</form>

<script>
  const sourceTypeSelect = document.getElementById('source_type');
  const supplierDiv = document.getElementById('source_supplier');
  const warehouseDiv = document.getElementById('source_warehouse');

  if (sourceTypeSelect) {
    sourceTypeSelect.addEventListener('change', function () {
      supplierDiv.style.display = 'none';
      warehouseDiv.style.display = 'none';

      if (this.value === 'supplier') {
        supplierDiv.style.display = 'block';
      } else if (this.value === 'warehouse') {
        warehouseDiv.style.display = 'block';
      }
    });
  }
</script>
