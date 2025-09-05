<?php  
// File: app/controllers/ItemController.php
// Updated: implement robust store() handling for both single AJAX row saves and batch paste,
// with CSRF verification, supplier/warehouse resolution, useful JSON responses for AJAX,
// and friendly flash+redirect for non-AJAX usage. 
// ✅ Fixed redirect paths (removed /public issue).

class ItemController extends Controller
{
    protected $auth;
    protected $itemModel;
    protected $supplierModel;
    protected $warehouseModel;
    protected string $base;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->itemModel = new Item();
        $this->supplierModel = new Supplier();
        $this->warehouseModel = new Warehouse();

        // ✅ Normalize BASE_PATH (strip /public)
        $this->base = rtrim(str_replace('/public', '', BASE_PATH), '/');

        if (!$this->auth->check()) {
            if (php_sapi_name() !== 'cli') {
                $this->redirect($this->base . '/login');
                exit;
            }
        }
    }

    /** List all items */
    public function index()
    {
        $items = $this->itemModel->all();
        $this->view('items/index', [
            'title' => 'Items',
            'items' => $items,
            'user'  => $this->auth->user()
        ]);
    }

    /** Show details of a single item */
    public function show()
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo "Missing item id";
            return;
        }

        $item = $this->itemModel->findById($id);
        if (!$item) {
            http_response_code(404);
            echo "Item not found";
            return;
        }

        $this->view('items/show', [
            'item'  => $item,
            'title' => 'Item Details'
        ]);
    }

    /** Show create form */
    public function create()
    {
        $suppliers  = $this->supplierModel->all();
        $warehouses = $this->warehouseModel->all();

        $this->view('items/create', [
            'title'      => 'Add Item',
            'suppliers'  => $suppliers,
            'warehouses' => $warehouses
        ]);
    }

    /** Handle item creation (single + batch paste mode) */
    public function store()
    {
        try {
            // Verify CSRF - will throw/exit if invalid
            verify_csrf();

            // If a batch textarea was posted, handle batch import
            if (isset($_POST['batch']) && trim($_POST['batch']) !== '') {
                $batchText = trim((string)$_POST['batch']);
                $lines = preg_split("/\r\n|\n|\r/", $batchText);
                $created = [];
                $errors = [];
                $lineNo = 0;

                foreach ($lines as $rawLine) {
                    $lineNo++;
                    $line = trim($rawLine);
                    if ($line === '') continue;

                    $cells = str_getcsv($line);
                    if (count($cells) === 1 && strpos($cells[0], "\t") !== false) {
                        $cells = explode("\t", $cells[0]);
                    }

                    for ($i = 0; $i < 8; $i++) {
                        $cells[$i] = isset($cells[$i]) ? trim($cells[$i]) : '';
                    }

                    $firstLower = strtolower($cells[0]);
                    if (in_array($firstLower, ['sku', 'product', 'item', 'code', 'id'])) {
                        continue; // skip header row
                    }

                    $sku              = $cells[0];
                    $name             = $cells[1];
                    $supplierName     = $cells[2];
                    $unitPrice        = $cells[3] !== '' ? (float)$cells[3] : 0.0;
                    $totalStock       = $cells[4] !== '' ? (int)$cells[4] : 0;
                    $warehouseNameOrId= $cells[5];
                    $safetyStock      = $cells[6] !== '' ? (int)$cells[6] : 0;
                    $reorderPoint     = $cells[7] !== '' ? (int)$cells[7] : 0;

                    if ($sku === '' || $name === '') {
                        $errors[] = "Line {$lineNo}: Missing SKU or Name.";
                        continue;
                    }

                    // Supplier resolution
                    $finalSupplierId = null;
                    if ($supplierName !== '') {
                        if (method_exists($this->supplierModel, 'findByName')) {
                            $existing = $this->supplierModel->findByName($supplierName);
                        } else {
                            $existing = method_exists($this->supplierModel, 'find') ?
                                $this->supplierModel->find(['name' => $supplierName]) : null;
                        }
                        if ($existing) {
                            $finalSupplierId = (int)$existing['id'];
                        } else {
                            if (method_exists($this->supplierModel, 'create')) {
                                $sid = $this->supplierModel->create(['name' => $supplierName]);
                                $finalSupplierId = $sid ? (int)$sid : null;
                            }
                        }
                    }

                    // Warehouse resolution
                    $finalWarehouseId = null;
                    if ($warehouseNameOrId !== '') {
                        if (ctype_digit((string)$warehouseNameOrId)) {
                            $finalWarehouseId = (int)$warehouseNameOrId;
                        } else {
                            if (method_exists($this->warehouseModel, 'findByName')) {
                                $w = $this->warehouseModel->findByName($warehouseNameOrId);
                            } else {
                                $w = method_exists($this->warehouseModel, 'find') ?
                                    $this->warehouseModel->find(['name' => $warehouseNameOrId]) : null;
                            }
                            if ($w) $finalWarehouseId = (int)$w['id'];
                        }
                    }

                    if (!$finalWarehouseId) {
                        $errors[] = "Line {$lineNo}: Warehouse '{$warehouseNameOrId}' not found.";
                        continue;
                    }

                    $itemData = [
                        'sku'           => $sku,
                        'name'          => $name,
                        'supplier_id'   => $finalSupplierId,
                        'unit_price'    => $unitPrice,
                        'total_stock'   => $totalStock,
                        'warehouse_id'  => $finalWarehouseId,
                        'safety_stock'  => $safetyStock,
                        'reorder_point' => $reorderPoint
                    ];

                    try {
                        if (!method_exists($this->itemModel, 'create')) {
                            throw new \RuntimeException('Item model does not support create().');
                        }
                        $createdId = $this->itemModel->create($itemData);
                        if ($createdId === false || $createdId === null) {
                            $errors[] = "Line {$lineNo}: Failed to create item (duplicate SKU or invalid data).";
                        } else {
                            $created[] = ['line' => $lineNo, 'id' => $createdId, 'sku' => $sku];
                        }
                    } catch (\Throwable $ie) {
                        error_log("Items batch create error on line {$lineNo}: " . $ie->getMessage());
                        $errors[] = "Line {$lineNo}: Server error while creating item.";
                    }
                }

                if ($this->wantsJson()) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'status'        => empty($errors) ? 'success' : 'partial',
                        'created_count' => count($created),
                        'created'       => $created,
                        'errors'        => $errors
                    ]);
                    exit;
                } else {
                    if (!empty($errors)) {
                        flash('error', 'Some rows failed to import. See server log or errors.');
                        flash('batch_import_summary', ['created' => $created, 'errors' => $errors]);
                    } else {
                        flash('success', 'Batch import completed. ' . count($created) . ' items created.');
                    }
                    redirect($this->base . '/items'); // ✅ fixed
                }
            }

            // Single-item create
            $sku         = isset($_POST['sku']) ? trim((string)$_POST['sku']) : '';
            $name        = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
            $supplierId  = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
            $supplierName= isset($_POST['supplier_name']) ? trim((string)$_POST['supplier_name']) : '';
            $unitPrice   = isset($_POST['unit_price']) ? (float)$_POST['unit_price'] : 0.0;
            $totalStock  = isset($_POST['total_stock']) ? (int)$_POST['total_stock'] : 0;
            $warehouseId = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;

            if ($sku === '' || $name === '' || $unitPrice <= 0) {
                return $this->respondError('Please provide SKU, Name and Unit Price.', 422);
            }
            if (!$warehouseId) {
                return $this->respondError('Please select a valid warehouse.', 422);
            }

            $finalSupplierId = null;
            if ($supplierId > 0) {
                $finalSupplierId = $supplierId;
            } elseif ($supplierName !== '') {
                if (method_exists($this->supplierModel, 'findByName')) {
                    $existing = $this->supplierModel->findByName($supplierName);
                } else {
                    $existing = method_exists($this->supplierModel, 'find') ?
                        $this->supplierModel->find(['name' => $supplierName]) : null;
                }
                if ($existing) {
                    $finalSupplierId = (int)$existing['id'];
                } else {
                    if (method_exists($this->supplierModel, 'create')) {
                        $sid = $this->supplierModel->create(['name' => $supplierName]);
                        $finalSupplierId = $sid ? (int)$sid : null;
                    }
                }
            }

            $warehouseOk = false;
            if ($warehouseId > 0) {
                if (method_exists($this->warehouseModel, 'findById')) {
                    $w = $this->warehouseModel->findById($warehouseId);
                    $warehouseOk = (bool)$w;
                } else {
                    $warehouseOk = true;
                }
            }
            if (!$warehouseOk) {
                return $this->respondError('Selected warehouse not found.', 422);
            }

            $createData = [
                'sku'         => $sku,
                'name'        => $name,
                'supplier_id' => $finalSupplierId,
                'unit_price'  => $unitPrice,
                'total_stock' => $totalStock,
                'warehouse_id'=> $warehouseId
            ];

            if (!method_exists($this->itemModel, 'create')) {
                throw new \RuntimeException('Item model does not support create().');
            }

            $newId = $this->itemModel->create($createData);

            if ($newId === false || $newId === null) {
                return $this->respondError('Failed to create item (SKU might already exist).', 409);
            }

            return $this->respondSuccess('Item created successfully.', ['id' => $newId]);

        } catch (\Throwable $e) {
            error_log('ItemController::store error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->handleException($e);
        }
    }

    /** Show edit form */
    public function edit()
    {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo "Missing item id";
            return;
        }

        $item = $this->itemModel->findById($id);
        if (!$item) {
            http_response_code(404);
            echo "Item not found";
            return;
        }

        $suppliers  = $this->supplierModel->all();
        $warehouses = $this->warehouseModel->all();

        $this->view('items/edit', [
            'title'      => 'Edit Item',
            'item'       => $item,
            'suppliers'  => $suppliers,
            'warehouses' => $warehouses
        ]);
    }

    /** Handle update */
    public function update()
    {
        try {
            verify_csrf();

            $id             = (int)($_POST['id'] ?? 0);
            $sku            = trim($_POST['sku'] ?? '');
            $name           = trim($_POST['name'] ?? '');
            $supplierId     = (int)($_POST['supplier_id'] ?? 0);
            $supplierName   = trim($_POST['supplier_name'] ?? '');
            $unitPrice      = (float)($_POST['unit_price'] ?? 0);
            $avgDailyDemand = (int)($_POST['avg_daily_demand'] ?? 0);
            $leadTimeDays   = (int)($_POST['lead_time_days'] ?? 0);
            $safetyStock    = (int)($_POST['safety_stock'] ?? 0);
            $reorderPoint   = (int)($_POST['reorder_point'] ?? 0);
            $totalStock     = isset($_POST['total_stock']) ? (int)$_POST['total_stock'] : null;

            if (!$id || $sku === '' || $name === '') {
                return $this->respondError('Invalid item data.');
            }

            $finalSupplierId = null;
            if ($supplierId > 0) {
                $finalSupplierId = $supplierId;
            } elseif ($supplierName !== '') {
                $existing = $this->supplierModel->findByName($supplierName);
                $finalSupplierId = $existing ? (int)$existing['id']
                    : (int)$this->supplierModel->create(['name' => $supplierName]);
            }

            $updateData = [
                'sku'             => $sku,
                'name'            => $name,
                'supplier_id'     => $finalSupplierId,
                'unit_price'      => $unitPrice,
                'avg_daily_demand'=> $avgDailyDemand,
                'lead_time_days'  => $leadTimeDays,
                'safety_stock'    => $safetyStock,
                'reorder_point'   => $reorderPoint
            ];

            if ($totalStock !== null) {
                $updateData['total_stock'] = $totalStock;
            }

            $result = $this->itemModel->updateById($id, $updateData);

            if ($result === false) {
                return $this->respondError('SKU already exists. Please choose a different SKU.');
            }

            return $this->respondSuccess('Item updated successfully.', ['id' => $id]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    /** Handle delete */
    public function delete()
    {
        try {
            verify_csrf();

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                return $this->respondError('Missing item id.');
            }

            $this->itemModel->deleteById($id);

            return $this->respondSuccess('Item deleted successfully.', ['id' => $id]);

        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }

    // ----------------- Helpers -----------------

    private function wantsJson(): bool
    {
        if (isset($_POST['__ajax']) && $_POST['__ajax'] === '1') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        if (!empty($_SERVER['HTTP_ACCEPT']) &&
            stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
        return false;
    }

    private function respondSuccess(string $message, $payload = null)
    {
        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => $message, 'data' => $payload]);
            exit;
        } else {
            flash('success', $message);
            redirect($this->base . '/items'); // ✅ fixed
        }
    }

    private function respondError(string $message, int $code = 400)
    {
        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            http_response_code($code);
            echo json_encode(['status' => 'error', 'message' => $message]);
            exit;
        } else {
            flash('error', $message);
            redirect($this->base . '/items'); // ✅ fixed
        }
    }

    private function handleException(\Throwable $e)
    {
        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        } else {
            echo "<h2>Server Error</h2><pre>".$e->getMessage()."</pre>";
            exit;
        }
    }
}
