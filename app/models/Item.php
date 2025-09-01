<?php     
// File: app/models/Item.php

declare(strict_types=1);

class Item extends Model
{
    protected ?string $table = 'items';

    /**
     * Get all items with supplier info and total stock.
     */
    public function all(): array
    {
        $sql = "
            SELECT i.*,
                   COALESCE(s.name, '') AS supplier_name,
                   i.total_stock AS current_stock
            FROM {$this->table} i
            LEFT JOIN suppliers s ON s.id = i.supplier_id
            ORDER BY i.sku ASC
        ";

        $items = $this->find($sql);

        foreach ($items as &$item) {
            $item['warehouses'] = $this->getStockByWarehouses((int)$item['id']);
        }

        return $items;
    }

    /**
     * Find item by ID.
     */
    public function findById(int $id): ?array
    {
        $sql = "
            SELECT i.*,
                   COALESCE(s.name, '') AS supplier_name,
                   i.total_stock AS current_stock
            FROM {$this->table} i
            LEFT JOIN suppliers s ON s.id = i.supplier_id
            WHERE i.id = :id
            LIMIT 1
        ";

        $item = $this->first($sql, ['id' => $id]);
        if ($item) {
            $item['warehouses'] = $this->getStockByWarehouses($id);
        }

        return $item ?: null;
    }

    /**
     * Find item by barcode (or SKU).
     */
    public function findByBarcode(string $barcode): ?array
    {
        $sql = "
            SELECT i.*,
                   COALESCE(s.name, '') AS supplier_name,
                   i.total_stock AS current_stock
            FROM {$this->table} i
            LEFT JOIN suppliers s ON s.id = i.supplier_id
            WHERE i.sku = :barcode
               OR (i.barcode IS NOT NULL AND i.barcode = :barcode)
            LIMIT 1
        ";

        $item = $this->first($sql, ['barcode' => $barcode]);
        if ($item) {
            $item['warehouses'] = $this->getStockByWarehouses((int)$item['id']);
        }

        return $item ?: null;
    }

    /**
     * Get stock breakdown per warehouse.
     */
    public function getStockByWarehouses(int $itemId): array
    {
        global $DB;

        $sql = "
            SELECT w.id, w.name,
                   COALESCE(SUM(
                       CASE
                           WHEN sm.movement_type = 'entry'      AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                           WHEN sm.movement_type = 'exit'       AND sm.warehouse_from_id = w.id THEN -sm.quantity
                           WHEN sm.movement_type = 'transfer'   AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                           WHEN sm.movement_type = 'transfer'   AND sm.warehouse_from_id = w.id THEN -sm.quantity
                           WHEN sm.movement_type = 'adjustment' AND sm.warehouse_to_id   = w.id THEN  sm.quantity
                           WHEN sm.movement_type = 'adjustment' AND sm.warehouse_from_id = w.id THEN -sm.quantity
                           ELSE 0
                       END
                   ),0) AS stock
            FROM warehouses w
            LEFT JOIN stock_movements sm
                   ON sm.item_id = :item_id
                  AND (sm.warehouse_from_id = w.id OR sm.warehouse_to_id = w.id)
            GROUP BY w.id, w.name
            ORDER BY w.name ASC
        ";

        $stmt = $DB->prepare($sql);
        $stmt->execute(['item_id' => $itemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * ✅ Recalculate and update the total stock for an item across all warehouses.
     */
    public function recalculateTotalStock(int $itemId): bool
    {
        if ($itemId <= 0) {
            return false;
        }

        global $DB;

        $sql = "
            SELECT COALESCE(SUM(
                       CASE
                           WHEN sm.movement_type = 'entry'      THEN  sm.quantity
                           WHEN sm.movement_type = 'exit'       THEN -sm.quantity
                           WHEN sm.movement_type = 'transfer'   AND sm.warehouse_to_id   IS NOT NULL THEN  sm.quantity
                           WHEN sm.movement_type = 'transfer'   AND sm.warehouse_from_id IS NOT NULL THEN -sm.quantity
                           WHEN sm.movement_type = 'adjustment' THEN  sm.quantity
                           ELSE 0
                       END
                   ),0) AS total
            FROM stock_movements sm
            WHERE sm.item_id = :item_id
        ";

        $stmt = $DB->prepare($sql);
        $stmt->execute(['item_id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($row['total'] ?? 0);

        $updateSql = "UPDATE {$this->table} SET total_stock = :total, updated_at = NOW() WHERE id = :id";
        return $this->execute($updateSql, ['total' => $total, 'id' => $itemId]) > 0;
    }

    public function existsBySku(string $sku, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE sku = :sku";
        $params = ['sku' => $sku];

        if ($excludeId !== null) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }

        $row = $this->first($sql, $params);
        return !empty($row);
    }

    /**
     * Create a single item. Returns ID or false if duplicate.
     */
    public function create(array $data)
    {
        if ($this->existsBySku($data['sku'])) {
            return false;
        }

        $sql = "
            INSERT INTO {$this->table}
                (sku, barcode, name, supplier_id, unit_price, description,
                 avg_daily_demand, lead_time_days,
                 safety_stock, reorder_point, total_stock,
                 created_at, updated_at)
            VALUES
                (:sku, :barcode, :name, :supplier_id, :unit_price, :description,
                 :avg_daily_demand, :lead_time_days,
                 :safety_stock, :reorder_point, :total_stock,
                 NOW(), NOW())
        ";

        $supplierId = !empty($data['supplier_id']) && (int)$data['supplier_id'] > 0 ? (int)$data['supplier_id'] : null;

        $this->execute($sql, [
            'sku'             => $data['sku'],
            'barcode'         => $data['barcode'] ?? null,
            'name'            => $data['name'],
            'supplier_id'     => $supplierId,
            'unit_price'      => $data['unit_price'] ?? 0,
            'description'     => $data['description'] ?? '',
            'avg_daily_demand'=> $data['avg_daily_demand'] ?? 0,
            'lead_time_days'  => $data['lead_time_days'] ?? 0,
            'safety_stock'    => $data['safety_stock'] ?? 0,
            'reorder_point'   => $data['reorder_point'] ?? 0,
            'total_stock'     => $data['total_stock'] ?? 0,
        ]);

        $newId = (int)$this->lastInsertId();

        // Initial stock logic unchanged...
        $initialStock = isset($data['total_stock']) ? (int)$data['total_stock'] : 0;
        $warehouseId = isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0;

        if ($newId > 0 && $initialStock > 0 && $warehouseId > 0) {
            try {
                $stockMovement = new StockMovement();
                $userId = null;
                if (class_exists('Auth')) {
                    try {
                        $auth = new Auth();
                        $u = $auth->user();
                        $userId = isset($u['id']) ? (int)$u['id'] : null;
                    } catch (\Throwable $t) {
                        $userId = null;
                    }
                }

                $supplierForMovement = $supplierId;
                $rawSupplierName = $data['raw_supplier_name'] ?? null;

                $stockMovement->addEntry(
                    $newId,
                    $initialStock,
                    $userId,
                    'Initial stock (created with item)',
                    $warehouseId,
                    $supplierForMovement,
                    $rawSupplierName
                );
            } catch (\Throwable $e) {
                error_log('Failed to create initial stock movement for item ' . $newId . ': ' . $e->getMessage());
            }
        }

        return $newId;
    }

    public function updateById(int $id, array $data)
    {
        if ($this->existsBySku($data['sku'], $id)) {
            return false;
        }

        $sql = "
            UPDATE {$this->table}
            SET sku = :sku,
                barcode = :barcode,
                name = :name,
                supplier_id = :supplier_id,
                unit_price = :unit_price,
                description = :description,
                avg_daily_demand = :avg_daily_demand,
                lead_time_days   = :lead_time_days,
                safety_stock = :safety_stock,
                reorder_point = :reorder_point,
                total_stock = :total_stock,
                updated_at = NOW()
            WHERE id = :id
        ";

        $supplierId = !empty($data['supplier_id']) && (int)$data['supplier_id'] > 0 ? (int)$data['supplier_id'] : null;

        return $this->execute($sql, [
            'id'              => $id,
            'sku'             => $data['sku'],
            'barcode'         => $data['barcode'] ?? null,
            'name'            => $data['name'],
            'supplier_id'     => $supplierId,
            'unit_price'      => $data['unit_price'] ?? 0,
            'description'     => $data['description'] ?? '',
            'avg_daily_demand'=> $data['avg_daily_demand'] ?? 0,
            'lead_time_days'  => $data['lead_time_days'] ?? 0,
            'safety_stock'    => $data['safety_stock'] ?? 0,
            'reorder_point'   => $data['reorder_point'] ?? 0,
            'total_stock'     => max(0, $data['total_stock'] ?? 0),
        ]);
    }

    public function update(int $id, array $fields): bool
    {
        if ($id <= 0 || empty($fields)) {
            return false;
        }

        $allowed = [
            'sku', 'name', 'supplier_id', 'unit_price',
            'avg_daily_demand', 'lead_time_days',
            'safety_stock', 'reorder_point'
        ];

        $setParts = [];
        $params   = ['id' => $id];

        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) {
                continue;
            }
            $setParts[] = "{$k} = :{$k}";
            $params[$k] = $v;
        }

        if (empty($setParts)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . ", updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, $params) > 0;
    }

    public function deleteById(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE id = :id LIMIT 1";
        return $this->execute($sql, ['id' => $id]) > 0;
    }
}
