<?php
// File: app/models/Role.php
declare(strict_types=1);

class Role extends Model
{
    /** @var string|null */
    protected ?string $table = 'roles';

    /**
     * Get all roles ordered by name.
     */
    public function all(): array
    {
        return $this->find("SELECT * FROM roles ORDER BY name ASC");
    }

    /**
     * Find a role by its name.
     */
    public function findByName(string $name): ?array
    {
        return $this->first(
            "SELECT * FROM roles WHERE name = :name LIMIT 1",
            ['name' => $name]
        );
    }

    /**
     * Create a new role and return its ID.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO roles (name, description, created_at) 
                VALUES (:name, :description, NOW())";
        $this->execute($sql, $data);
        return (int)$this->lastInsertId();
    }
}
