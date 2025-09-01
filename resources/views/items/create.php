<?php 
// File: resources/views/items/create.php
// Interface for adding items (single rows or batch paste)
// Updated: safer JS embedding, robust CSRF handling for AJAX, improved fetch error handling.

// Escape helper
if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// Supplier dropdown
$supplierOptions = '<option value="">-- Choose Supplier --</option>';
$supplierMap = [];
if (!empty($suppliers)) {
    foreach ($suppliers as $s) {
        $supplierOptions .= '<option value="' . e($s['id']) . '">' . e($s['name']) . '</option>';
        $supplierMap[strtolower(trim($s['name']))] = $s['id'];
    }
}

// Warehouse dropdown
$warehouseOptions = '<option value="">-- Choose Warehouse --</option>';
$warehouseMap = [];
if (!empty($warehouses ?? [])) {
    foreach ($warehouses as $w) {
        $warehouseOptions .= '<option value="' . e($w['id']) . '">' . e($w['name']) . '</option>';
        $warehouseMap[strtolower(trim($w['name']))] = $w['id'];
    }
}
?>
<div class="card shadow-sm">
  <div class="card-body">
    <h4 class="card-title mb-3">Add Items</h4>

    <?php if ($msg = flash('error')): ?>
      <div class="alert alert-danger"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- Hidden CSRF -->
    <form id="csrf-form" method="post" action="<?= BASE_PATH ?>/items/store" class="d-none">
      <?= csrf_field() ?>
    </form>

    <!-- Table entry mode -->
    <div class="table-responsive mb-3">
      <table class="table table-bordered align-middle" id="items-table">
        <thead class="table-light">
          <tr>
            <th>SKU</th>
            <th>Name</th>
            <th>Supplier (choose or type new)</th>
            <th>Unit Price</th>
            <th>Total Stock (pcs)</th>
            <th>Warehouse</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="item-rows">
          <tr>
            <td><input type="text" class="form-control sku" required></td>
            <td><input type="text" class="form-control name" required></td>
            <td>
              <select class="form-control supplier_id"><?= $supplierOptions ?></select>
              <input type="text" class="form-control supplier_name mt-1" placeholder="Or type new supplier">
            </td>
            <td><input type="number" step="0.01" class="form-control unit_price" required></td>
            <td><input type="number" class="form-control total_stock" min="0"></td>
            <td>
              <select class="form-control warehouse_id" required><?= $warehouseOptions ?></select>
            </td>
            <td>
              <button type="button" class="btn btn-success btn-sm save-row">Add</button>
              <button type="button" class="btn btn-danger btn-sm delete-row">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mb-3">
      <button type="button" id="add-empty-row" class="btn btn-outline-primary">+ Add Empty Row</button>
      <a href="<?= BASE_PATH ?>/items" class="btn btn-secondary">Back to Items</a>
    </div>

    <!-- Bulk Paste -->
    <div class="card mt-4">
      <div class="card-header">📋 Bulk Paste (Excel/CSV)</div>
      <div class="card-body">
        <p class="text-muted">
          Paste rows from Excel/CSV.<br>
          Expected columns: <b>SKU, Name, Supplier, Unit Price, Total Stock, Warehouse, Safety Stock, Reorder Point</b>.<br>
          If supplier is not found, it will be auto-created. Warehouse must match an existing warehouse.
        </p>
        <form method="post" action="<?= BASE_PATH ?>/items/store">
          <?= csrf_field() ?>
          <div class="mb-3">
            <textarea name="batch" class="form-control" rows="8"
              placeholder="SKU,Name,Supplier,Unit Price,Total Stock,Warehouse,Safety Stock,Reorder Point"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Save Batch</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Read CSRF hidden input (if present)
  const csrfForm = document.getElementById('csrf-form');
  let csrfInput = null;
  if (csrfForm) csrfInput = csrfForm.querySelector('input[type="hidden"]');
  const csrfName  = csrfInput ? csrfInput.getAttribute('name') : null;
  const csrfValue = csrfInput ? csrfInput.value : null;

  const tbody = document.getElementById('item-rows');
  const addRowBtn = document.getElementById('add-empty-row');

  // Injected option HTML from server (JSON-encoded to be safe)
  const supplierOptionsHtml = <?= json_encode($supplierOptions) ?>;
  const warehouseOptionsHtml = <?= json_encode($warehouseOptions) ?>;

  // Small helper to escape text for inclusion into HTML attribute/value
  function escapeForValue(v){
    if (v === null || v === undefined) return '';
    return String(v)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/`/g, '&#96;');
  }

  function newEditableRow(values={}) {
    const tr = document.createElement('tr');

    // Build innerHTML using escaped values to avoid breaking attributes
    const sku = escapeForValue(values.sku || '');
    const name = escapeForValue(values.name || '');
    const supplierName = escapeForValue(values.supplier_name || '');
    const unitPrice = escapeForValue(values.unit_price || '');
    const totalStock = escapeForValue(values.total_stock || '');
    const supplierIdVal = values.supplier_id || '';
    const warehouseIdVal = values.warehouse_id || '';

    tr.innerHTML = `
      <td><input type="text" class="form-control sku" value="${sku}" required></td>
      <td><input type="text" class="form-control name" value="${name}" required></td>
      <td>
        <select class="form-control supplier_id">${supplierOptionsHtml}</select>
        <input type="text" class="form-control supplier_name mt-1" placeholder="Or type new supplier" value="${supplierName}">
      </td>
      <td><input type="number" step="0.01" class="form-control unit_price" value="${unitPrice}" required></td>
      <td><input type="number" class="form-control total_stock" value="${totalStock}" min="0"></td>
      <td>
        <select class="form-control warehouse_id" required>${warehouseOptionsHtml}</select>
      </td>
      <td>
        <button type="button" class="btn btn-success btn-sm save-row">Add</button>
        <button type="button" class="btn btn-danger btn-sm delete-row">Delete</button>
      </td>
    `;

    // Set selects AFTER inserting HTML to ensure values present
    if (supplierIdVal) {
      const sel = tr.querySelector('.supplier_id');
      if (sel) sel.value = supplierIdVal;
    }
    if (warehouseIdVal) {
      const selW = tr.querySelector('.warehouse_id');
      if (selW) selW.value = warehouseIdVal;
    }

    return tr;
  }

  // Ensure at least one empty row exists on load (the initial markup already has one)
  addRowBtn.addEventListener('click', () => tbody.appendChild(newEditableRow()));

  // Handle save / delete / edit clicks using event delegation
  tbody.addEventListener('click', function(e) {
    const row = e.target.closest('tr');
    if (!row) return;

    if (e.target.classList.contains('save-row')) saveRow(row, e.target);

    if (e.target.classList.contains('delete-row')) {
      row.remove();
    }

    if (e.target.classList.contains('edit-row')) {
      row.querySelectorAll('input, select').forEach(el => el.disabled = false);
      e.target.outerHTML =
        '<button type="button" class="btn btn-success btn-sm save-row">Save</button>';
    }
  });

  function saveRow(row, button) {
    const sku          = (row.querySelector('.sku')?.value || '').trim();
    const name         = (row.querySelector('.name')?.value || '').trim();
    const supplierId   = (row.querySelector('.supplier_id')?.value || '').trim();
    const supplierName = (row.querySelector('.supplier_name')?.value || '').trim();
    const unitPrice    = (row.querySelector('.unit_price')?.value || '').trim();
    const totalStock   = (row.querySelector('.total_stock')?.value || '').trim();
    const warehouseId  = (row.querySelector('.warehouse_id')?.value || '').trim();

    if (!sku || !name || !unitPrice) {
      alert('Please fill in SKU, Name, and Unit Price.');
      return;
    }
    if (!warehouseId) {
      alert('Please select a warehouse.');
      return;
    }

    // Disable button and give visual feedback
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = 'Saving...';

    const params = new URLSearchParams();
    // Only append CSRF token if we have it (prevents sending "null" as key)
    if (csrfName) params.append(csrfName, csrfValue || '');
    params.append('sku', sku);
    params.append('name', name);
    params.append('supplier_id', supplierId);
    params.append('supplier_name', supplierName);
    params.append('unit_price', unitPrice);
    params.append('total_stock', totalStock || '0'); // send total_stock instead of stock
    params.append('warehouse_id', warehouseId);
    params.append('__ajax', '1');

    fetch('<?= BASE_PATH ?>/items/store', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      },
      body: params.toString()
    })
    .then(response => {
      // attempt to parse JSON even on non-2xx so we can show server message
      return response.json().then(data => ({ ok: response.ok, data }));
    })
    .then(result => {
      const data = result.data || {};
      if (result.ok && data.status === 'success') {
        // mark row as saved / lock fields
        row.querySelectorAll('input, select').forEach(el => el.disabled = true);
        // replace actions cell content
        const actionsCell = row.querySelector('td:last-child');
        if (actionsCell) {
          actionsCell.innerHTML = `
            <span class="text-success">✔ Saved</span>
            <button type="button" class="btn btn-warning btn-sm edit-row">Edit</button>
          `;
        }
        // append a fresh empty row for convenience
        tbody.appendChild(newEditableRow());
      } else {
        // show server-provided message when available
        const msg = data && data.message ? data.message : 'Failed to save item.';
        alert(msg);
        button.disabled = false;
        button.innerHTML = originalText;
      }
    })
    .catch(err => {
      console.error('Save row error:', err);
      alert('Server error. Check your server logs for details.');
      button.disabled = false;
      button.innerHTML = originalText;
    });
  }
})();
</script>
