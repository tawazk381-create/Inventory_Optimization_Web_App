<?php    
// File: app/models/StockMovement.php

class StockMovement extends Model
{
    protected ?string $table = 'stock_movements';

    /**
     * Record a stock movement (with supplier + warehouse tracking).
     * Always keeps items.total_stock in sync by recalculating from movements.
     */
    public function create(array $data): int
    {
        global $DB;

        $sql = "
            INSERT INTO {$this->table}
                (item_id, batch_id, warehouse_from_id, warehouse_to_id,
                 supplier_id, raw_supplier_name,
                 user_id, quantity, movement_type, reference, created_at)
            VALUES
                (:item_id, :batch_id, :warehouse_from_id, :warehouse_to_id,
                 :supplier_id, :raw_supplier_name,
                 :user_id, :quantity, :movement_type, :reference, NOW())
        ";

        $defaults = [
            'batch_id'          => null,
            'warehouse_from_id' => null,
            'warehouse_to_id'   => null,
            'supplier_id'       => null,
            'raw_supplier_name' => null,
            'user_id'           => null,
            'quantity'          => 0,
            'movement_type'     => null,
            'reference'         => null,
        ];
        $data = array_merge($defaults, $data);

        // Normalize foreign keys
        foreach (['warehouse_from_id', 'warehouse_to_id', 'supplier_id', 'batch_id', 'user_id'] as $fk) {
            if (empty($data[$fk]) || (int)$data[$fk] <= 0) {
                $data[$fk] = null;
            }
        }

        // Coerce quantity to int (allow negative values for signed adjustments)
        $data['quantity'] = (int)$data['quantity'];

        $this->execute($sql, $data);
        $id = (int)$this->lastInsertId();

        // ✅ Always recalc total stock to prevent discrepancies
        $itemModel = new Item();
        $itemModel->recalculateTotalStock((int)$data['item_id']);

        return $id;
    }

    /**
     * Get current stock of an item in a specific warehouse (or across all if null).
     */
    public function getStock(int $itemId, ?int $warehouseId = null): int
    {
        global $DB;

        $params = ['item_id' => $itemId];

        $sql = "
            SELECT COALESCE(SUM(
                CASE 
                    WHEN sm.movement_type = 'entry'     AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                    WHEN sm.movement_type = 'exit'      AND sm.warehouse_from_id = w.id THEN -sm.quantity
                    WHEN sm.movement_type = 'transfer'  AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                    WHEN sm.movement_type = 'transfer'  AND sm.warehouse_from_id = w.id THEN -sm.quantity
                    WHEN sm.movement_type = 'adjustment' AND sm.warehouse_to_id  = w.id THEN  sm.quantity
                    WHEN sm.movement_type = 'adjustment' AND sm.warehouse_from_id = w.id THEN -sm.quantity
                    ELSE 0
                END
            ),0) AS stock
            FROM warehouses w
            LEFT JOIN {$this->table} sm
                   ON sm.item_id = :item_id
                  AND (sm.warehouse_from_id = w.id OR sm.warehouse_to_id = w.id)
        ";

        if ($warehouseId !== null) {
            $sql .= " WHERE w.id = :wid";
            $params['wid'] = $warehouseId;
        }

        $stmt = $DB->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($row['stock'] ?? 0);
    }

    /** Convenience: total stock for an item across all warehouses */
    public function getItemStock(int $itemId): int
    {
        return $this->getStock($itemId, null);
    }

    /**
     * Stock per warehouse for an item.
     * Returns: [ [id, name, stock], ... ]
     */
    public function getItemStockByWarehouse(int $itemId): array
    {
        global $DB;

        $stmt = $DB->prepare("
            SELECT
                w.id,
                w.name,
                COALESCE(SUM(
                    CASE 
                        WHEN sm.movement_type = 'entry'     AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                        WHEN sm.movement_type = 'exit'      AND sm.warehouse_from_id = w.id THEN -sm.quantity
                        WHEN sm.movement_type = 'transfer'  AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                        WHEN sm.movement_type = 'transfer'  AND sm.warehouse_from_id = w.id THEN -sm.quantity
                        WHEN sm.movement_type = 'adjustment' AND sm.warehouse_to_id  = w.id THEN  sm.quantity
                        WHEN sm.movement_type = 'adjustment' AND sm.warehouse_from_id = w.id THEN -sm.quantity
                        ELSE 0
                    END
                ),0) AS stock
            FROM warehouses w
            LEFT JOIN {$this->table} sm
              ON sm.item_id = :item_id
             AND (sm.warehouse_from_id = w.id OR sm.warehouse_to_id = w.id)
            GROUP BY w.id, w.name
            ORDER BY w.name ASC
        ");
        $stmt->execute(['item_id' => $itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** ➕ Add stock entry */
    public function addEntry(
        int $itemId,
        int $quantity,
        ?int $userId = null,
        string $reference = null,
        ?int $warehouseId = null,
        ?int $supplierId = null,
        ?string $rawSupplierName = null
    ): void {
        if ($warehouseId === null || $warehouseId <= 0) {
            throw new InvalidArgumentException("Warehouse must be specified for stock entry.");
        }

        $this->create([
            'item_id'           => $itemId,
            'warehouse_to_id'   => $warehouseId,
            'supplier_id'       => $supplierId,
            'raw_supplier_name' => $rawSupplierName,
            'user_id'           => $userId,
            'quantity'          => (int)$quantity,
            'movement_type'     => 'entry',
            'reference'         => $reference,
        ]);
    }

    /** ➖ Add stock exit */
    public function addExit(
        int $itemId,
        int $quantity,
        ?int $userId = null,
        string $reference = null,
        ?int $warehouseId = null
    ): bool {
        if ($warehouseId === null || $warehouseId <= 0) {
            throw new InvalidArgumentException("Warehouse must be specified for stock exit.");
        }

        $quantity = (int)$quantity;
        $stock = $this->getStock($itemId, $warehouseId);
        if ($stock < $quantity) {
            return false; // ❌ insufficient stock
        }

        $this->create([
            'item_id'           => $itemId,
            'warehouse_from_id' => $warehouseId,
            'user_id'           => $userId,
            'quantity'          => $quantity,
            'movement_type'     => 'exit',
            'reference'         => $reference,
        ]);

        return true;
    }

    /** 🔄 Add stock transfer */
    public function addTransfer(
        int $itemId,
        int $quantity,
        int $fromWarehouse,
        int $toWarehouse,
        ?int $userId = null,
        string $reference = null
    ): bool {
        if ($fromWarehouse <= 0 || $toWarehouse <= 0) {
            throw new InvalidArgumentException("Both source and destination warehouses must be valid.");
        }
        if ($fromWarehouse === $toWarehouse) {
            throw new InvalidArgumentException("Source and destination warehouses must be different.");
        }

        $quantity = (int)$quantity;
        $stock = $this->getStock($itemId, $fromWarehouse);
        if ($stock < $quantity) {
            return false; // ❌ not enough in source
        }

        // Outgoing
        $this->create([
            'item_id'           => $itemId,
            'warehouse_from_id' => $fromWarehouse,
            'user_id'           => $userId,
            'quantity'          => $quantity,
            'movement_type'     => 'transfer',
            'reference'         => $reference,
        ]);

        // Incoming
        $this->create([
            'item_id'           => $itemId,
            'warehouse_to_id'   => $toWarehouse,
            'user_id'           => $userId,
            'quantity'          => $quantity,
            'movement_type'     => 'transfer',
            'reference'         => $reference,
        ]);

        return true;
    }

    /**
     * 🛠 Add stock adjustment (+/-)
     */
    public function addAdjustment(
        int $itemId,
        int $delta,
        ?int $userId = null,
        string $reason = null,
        ?int $warehouseId = null
    ): void {
        if ($warehouseId === null || $warehouseId <= 0) {
            throw new InvalidArgumentException("Warehouse must be specified for stock adjustment.");
        }

        $this->create([
            'item_id'           => $itemId,
            'warehouse_to_id'   => $warehouseId,
            'user_id'           => $userId,
            'quantity'          => (int)$delta,
            'movement_type'     => 'adjustment',
            'reference'         => $reason ?: 'manual adjustment',
        ]);
    }

    /** 📋 Get all stock movements (with item, supplier & warehouse names) */
    public function allMovements(): array
    {
        global $DB;

        $stmt = $DB->query("
            SELECT sm.id,
                   i.name AS item_name,
                   sm.quantity,
                   sm.movement_type,
                   sm.reference,
                   wf.name AS warehouse_from,
                   wt.name AS warehouse_to,
                   sm.supplier_id,
                   sm.raw_supplier_name,
                   s.name AS supplier_name,
                   sm.created_at
            FROM {$this->table} sm
            JOIN items i ON sm.item_id = i.id
            LEFT JOIN suppliers s ON sm.supplier_id = s.id
            LEFT JOIN warehouses wf ON sm.warehouse_from_id = wf.id
            LEFT JOIN warehouses wt ON sm.warehouse_to_id = wt.id
            ORDER BY sm.created_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
