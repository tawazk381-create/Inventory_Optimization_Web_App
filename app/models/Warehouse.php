<?php
// File: app/models/Warehouse.php
declare(strict_types=1);

/**
 * Warehouse model with stock helpers.
 * Provides CRUD operations and stock queries per warehouse.
 */
class Warehouse
{
    protected $table = 'warehouses';

    protected function getPDO(): PDO
    {
        if (property_exists($this, 'db') && $this->db instanceof PDO) {
            return $this->db;
        }
        global $DB;
        if (isset($DB) && $DB instanceof PDO) {
            return $DB;
        }
        throw new RuntimeException('PDO instance not found ($this->db or $DB).');
    }

    /** Count all warehouses */
    public function countAll(): int
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM {$this->table}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    /** Paginate warehouses */
    public function paginate(int $limit, int $offset): array
    {
        $pdo = $this->getPDO();
        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC LIMIT :l OFFSET :o";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return all warehouses (only active if status column exists)
     */
    public function all(): array
    {
        $pdo = $this->getPDO();

        $hasStatus = (bool)$pdo->query("SHOW COLUMNS FROM {$this->table} LIKE 'status'")->fetch();

        if ($hasStatus) {
            $stmt = $pdo->query("SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY name ASC");
        } else {
            $stmt = $pdo->query("SELECT * FROM {$this->table} ORDER BY name ASC");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Find warehouse by ID */
    public function findById(int $id): ?array
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** 🔹 Find warehouse by name (case-insensitive) */
    public function findByName(string $name): ?array
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM {$this->table} WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Create new warehouse */
    public function create(array $data): int
    {
        $pdo = $this->getPDO();
        $sql = "INSERT INTO {$this->table} (name, location, contact, status, created_at)
                VALUES (:name, :location, :contact, :status, NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name'     => $data['name'] ?? '',
            'location' => $data['location'] ?? null,
            'contact'  => $data['contact'] ?? null,
            'status'   => $data['status'] ?? 'active'
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** Update warehouse */
    public function update(int $id, array $data): bool
    {
        $pdo = $this->getPDO();
        $sql = "UPDATE {$this->table} SET
                name = :name,
                location = :location,
                contact = :contact,
                status = :status
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name'     => $data['name'] ?? '',
            'location' => $data['location'] ?? null,
            'contact'  => $data['contact'] ?? null,
            'status'   => $data['status'] ?? 'active',
            'id'       => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Delete warehouse */
    public function delete(int $id): bool
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get current stock of an item in a specific warehouse.
     */
    public function getItemStock(int $warehouseId, int $itemId): int
    {
        $pdo = $this->getPDO();
        $sql = "
            SELECT 
              COALESCE(SUM(CASE 
                WHEN movement_type = 'in' AND warehouse_to_id = :wid THEN quantity
                WHEN movement_type = 'out' AND warehouse_from_id = :wid THEN -quantity
                WHEN movement_type = 'transfer' AND warehouse_to_id = :wid THEN quantity
                WHEN movement_type = 'transfer' AND warehouse_from_id = :wid THEN -quantity
                WHEN movement_type = 'adjustment' AND warehouse_from_id = :wid THEN quantity
                ELSE 0 END),0) AS stock
            FROM stock_movements
            WHERE item_id = :iid
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['wid' => $warehouseId, 'iid' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['stock'] ?? 0);
    }

    /**
     * Get all warehouses with stock for a specific item.
     */
    public function getStockByItem(int $itemId): array
    {
        $pdo = $this->getPDO();
        $warehouses = $this->all();
        foreach ($warehouses as &$wh) {
            $wh['stock'] = $this->getItemStock((int)$wh['id'], $itemId);
        }
        return $warehouses;
    }
}
