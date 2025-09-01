<?php
// File: app/models/Supplier.php
declare(strict_types=1);

class Supplier
{
    protected $table = 'suppliers';

    /** Get PDO instance (from $this->db if available, else global $DB) */
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

    /** Count all suppliers */
    public function countAll(): int
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->query("SELECT COUNT(*) AS c FROM {$this->table}");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['c'] ?? 0);
    }

    /** Paginate suppliers */
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

    /** Return all suppliers (non-paginated) */
    public function all(): array
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->query("SELECT * FROM {$this->table} ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** ✅ Alias to findById so controller works */
    public function find(int $id): ?array
    {
        return $this->findById($id);
    }

    /** Find supplier by ID */
    public function findById(int $id): ?array
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** ✅ Find supplier by Name */
    public function findByName(string $name): ?array
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("SELECT * FROM {$this->table} WHERE name = :name LIMIT 1");
        $stmt->execute(['name' => trim($name)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Create a supplier */
    public function create(array $data): int
    {
        $pdo = $this->getPDO();
        $sql = "INSERT INTO {$this->table} (name, contact_name, contact_email, phone, address, created_at)
                VALUES (:name, :contact_name, :contact_email, :phone, :address, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name'          => $data['name'] ?? '',
            'contact_name'  => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'address'       => $data['address'] ?? null
        ]);
        return (int)$pdo->lastInsertId();
    }

    /** ✅ Find or Create supplier by name */
    public function findOrCreateByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("Supplier name cannot be empty");
        }

        $existing = $this->findByName($name);
        if ($existing) {
            return (int)$existing['id'];
        }

        return $this->create(['name' => $name]);
    }

    /** Update supplier */
    public function update(int $id, array $data): bool
    {
        $pdo = $this->getPDO();
        $sql = "UPDATE {$this->table} SET
                name = :name,
                contact_name = :contact_name,
                contact_email = :contact_email,
                phone = :phone,
                address = :address
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'name'          => $data['name'] ?? '',
            'contact_name'  => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'phone'         => $data['phone'] ?? null,
            'address'       => $data['address'] ?? null,
            'id'            => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Delete supplier */
    public function delete(int $id): bool
    {
        $pdo = $this->getPDO();
        $stmt = $pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
