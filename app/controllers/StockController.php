<?php 
// File: app/controllers/StockController.php

declare(strict_types=1);

class StockController extends Controller
{
    protected $auth;
    protected $stockMovement;
    protected $itemModel;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->requireRole(['Admin', 'Manager', 'Staff']); 
        $this->stockMovement = new StockMovement();
        $this->itemModel     = new Item();
    }

    // ----------------------
    // ➕ Stock Entry
    // ----------------------
    public function entryForm(): void
    {
        $supplierModel  = new Supplier();
        $warehouseModel = new Warehouse();

        $this->view('stock/movements/entry', [
            'title'      => 'Stock Entry',
            'items'      => $this->itemModel->all(),
            'suppliers'  => $supplierModel->all(),
            'warehouses' => $warehouseModel->all(),
        ]);
    }

    public function handleEntry(): void
    {
        try {
            verify_csrf();

            $itemId      = (int)($_POST['item_id'] ?? 0);
            $quantity    = (int)($_POST['quantity'] ?? 0);
            $warehouseId = (int)($_POST['warehouse_id'] ?? 0);

            $supplierId  = (int)($_POST['supplier_id'] ?? 0);
            $rawSupplier = trim($_POST['raw_supplier_name'] ?? '');

            if ($itemId <= 0 || $quantity <= 0 || $warehouseId <= 0) {
                flash('error', 'Invalid stock entry. Please select an item, warehouse, and valid quantity.');
                redirect('/stock-movements/entry');
            }

            if (!$supplierId && $rawSupplier !== '') {
                $supplierModel = new Supplier();
                $supplierId = $supplierModel->findOrCreateByName($rawSupplier);
            }

            $this->stockMovement->create([
                'item_id'           => $itemId,
                'batch_id'          => null,
                'warehouse_from_id' => null,
                'warehouse_to_id'   => $warehouseId,
                'supplier_id'       => $supplierId ?: null,
                'raw_supplier_name' => $rawSupplier !== '' ? $rawSupplier : null,
                'user_id'           => $_SESSION['user_id'] ?? null,
                'quantity'          => $quantity,
                'movement_type'     => 'entry',
                'reference'         => 'manual entry',
            ]);

            $this->itemModel->recalculateTotalStock($itemId);

            flash('success', 'Stock entry recorded successfully.');
            redirect('/stock-movements');

        } catch (\Throwable $e) {
            error_log("[StockEntry][ERROR] " . $e->getMessage());
            flash('error', 'Error while recording stock entry: ' . $e->getMessage());
            redirect('/stock-movements/entry');
        }
    }

    // ----------------------
    // ➖ Stock Exit
    // ----------------------
    public function exitForm(): void
    {
        $warehouseModel = new Warehouse();

        $this->view('stock/movements/exit', [
            'title'      => 'Stock Exit',
            'items'      => $this->itemModel->all(),
            'warehouses' => $warehouseModel->all(),
        ]);
    }

    public function handleExit(): void
    {
        try {
            verify_csrf();

            $itemId      = (int)($_POST['item_id'] ?? 0);
            $quantity    = (int)($_POST['quantity'] ?? 0);
            $warehouseId = (int)($_POST['warehouse_id'] ?? 0);

            if ($itemId <= 0 || $quantity <= 0 || $warehouseId <= 0) {
                flash('error', 'Invalid stock exit request.');
                redirect('/stock-movements/exit');
            }

            $success = $this->stockMovement->addExit(
                $itemId,
                $quantity,
                $_SESSION['user_id'] ?? null,
                'manual exit',
                $warehouseId
            );

            if (!$success) {
                flash('error', 'Not enough stock available in this warehouse.');
                redirect('/stock-movements/exit');
            }

            $this->itemModel->recalculateTotalStock($itemId);

            flash('success', 'Stock exit recorded successfully.');
            redirect('/stock-movements');

        } catch (\Throwable $e) {
            error_log("[StockExit][ERROR] " . $e->getMessage());
            flash('error', 'Error while recording stock exit: ' . $e->getMessage());
            redirect('/stock-movements/exit');
        }
    }

    // ----------------------
    // 🔄 Stock Transfer
    // ----------------------
    public function transferForm(): void
    {
        $warehouseModel = new Warehouse();

        $this->view('stock/movements/transfer', [
            'title'      => 'Stock Transfer',
            'items'      => $this->itemModel->all(),
            'warehouses' => $warehouseModel->all(),
        ]);
    }

    public function handleTransfer(): void
    {
        try {
            verify_csrf();

            $itemId   = (int)($_POST['item_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 0);
            $fromWh   = (int)($_POST['from_warehouse_id'] ?? 0);
            $toWh     = (int)($_POST['to_warehouse_id'] ?? 0);

            if ($itemId <= 0 || $quantity <= 0 || $fromWh <= 0 || $toWh <= 0 || $fromWh === $toWh) {
                flash('error', 'Invalid stock transfer.');
                redirect('/stock-movements/transfer');
            }

            $success = $this->stockMovement->addTransfer(
                $itemId,
                $quantity,
                $fromWh,
                $toWh,
                $_SESSION['user_id'] ?? null,
                'manual transfer'
            );

            if (!$success) {
                flash('error', 'Not enough stock available in source warehouse.');
                redirect('/stock-movements/transfer');
            }

            $this->itemModel->recalculateTotalStock($itemId);

            flash('success', 'Stock transfer recorded successfully.');
            redirect('/stock-movements');

        } catch (\Throwable $e) {
            error_log("[StockTransfer][ERROR] " . $e->getMessage());
            flash('error', 'Error while recording stock transfer: ' . $e->getMessage());
            redirect('/stock-movements/transfer');
        }
    }

    // ----------------------
    // 🛠 Stock Adjustment
    // ----------------------
    public function adjustmentForm(): void
    {
        $warehouseModel = new Warehouse();

        $this->view('stock/movements/adjustment', [
            'title'      => 'Stock Adjustment',
            'items'      => $this->itemModel->all(),
            'warehouses' => $warehouseModel->all(),
        ]);
    }

    // ✅ Independent Item Update
    public function handleItemUpdate(): void
    {
        try {
            verify_csrf();

            $itemId = (int)($_POST['item_id'] ?? 0);
            if ($itemId <= 0) {
                flash('error', 'Invalid item selection.');
                redirect('/stock-movements/adjustment');
            }

            $updates = [];
            $fields = [
                'sku'             => (string)($_POST['sku'] ?? ''),
                'name'            => (string)($_POST['name'] ?? ''),
                'unit_price'      => (float)($_POST['unit_price'] ?? 0),
                'avg_daily_demand'=> (int)($_POST['avg_daily_demand'] ?? 0),
                'lead_time_days'  => (int)($_POST['lead_time_days'] ?? 0),
                'safety_stock'    => (int)($_POST['safety_stock'] ?? 0),
                'reorder_point'   => (int)($_POST['reorder_point'] ?? 0),
            ];

            foreach ($fields as $key => $val) {
                if ($val !== '' && $val !== null) {
                    $updates[$key] = $val;
                }
            }

            $supplierId = $_POST['supplier_id'] ?? '';
            if (is_numeric($supplierId) && (int)$supplierId > 0) {
                $updates['supplier_id'] = (int)$supplierId;
            }

            if (!empty($updates)) {
                $this->itemModel->update($itemId, $updates);
                flash('success', 'Item details updated successfully.');
            } else {
                flash('info', 'No changes detected.');
            }

            redirect('/stock-movements/adjustment');

        } catch (\Throwable $e) {
            error_log("[ItemUpdate][ERROR] " . $e->getMessage());
            flash('error', 'Error while updating item: ' . $e->getMessage());
            redirect('/stock-movements/adjustment');
        }
    }

    // ✅ Independent Stock Adjustment
    public function handleAdjustment(): void
    {
        try {
            verify_csrf();

            $itemId      = (int)($_POST['item_id'] ?? 0);
            $quantity    = (int)($_POST['quantity'] ?? 0);
            $warehouseId = (int)($_POST['warehouse_id'] ?? 0);
            $reason      = trim((string)($_POST['reason'] ?? 'manual adjustment'));

            if ($itemId <= 0 || $quantity === 0 || $warehouseId <= 0) {
                flash('error', 'Invalid stock adjustment request.');
                redirect('/stock-movements/adjustment');
            }

            $this->stockMovement->create([
                'item_id'           => $itemId,
                'batch_id'          => null,
                'warehouse_from_id' => null,
                'warehouse_to_id'   => $warehouseId,
                'supplier_id'       => null,
                'raw_supplier_name' => null,
                'user_id'           => $_SESSION['user_id'] ?? null,
                'quantity'          => $quantity,
                'movement_type'     => 'adjustment',
                'reference'         => $reason,
            ]);

            $this->itemModel->recalculateTotalStock($itemId);

            flash('success', 'Stock adjustment recorded successfully.');
            redirect('/stock-movements');

        } catch (\Throwable $e) {
            error_log("[StockAdjustment][ERROR] " . $e->getMessage());
            flash('error', 'Error while recording stock adjustment: ' . $e->getMessage());
            redirect('/stock-movements/adjustment');
        }
    }

    // ----------------------
    // 📋 List all movements
    // ----------------------
    public function index(): void
    {
        $transactions = $this->stockMovement->allMovements();

        $this->view('stock/movements/index', [
            'title'        => 'Stock Movements',
            'transactions' => $transactions,
        ]);
    }
}
