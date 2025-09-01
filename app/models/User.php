<?php
// File: app/models/User.php

declare(strict_types=1);

class User extends Model
{
    protected ?string $table = 'users';

    /**
     * Find a user by email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->first(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1", 
            ['email' => $email]
        );
    }

    /**
     * Create a new user with secure password hashing
     */
    public function create(array $data): int
    {
        if (empty($data['password'])) {
            throw new InvalidArgumentException("Password is required.");
        }

        // Hash the password securely
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $sql = "
            INSERT INTO {$this->table} 
                (name, email, password_hash, role_id, created_at) 
            VALUES 
                (:name, :email, :password_hash, :role_id, NOW())
        ";

        $params = [
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password_hash' => $passwordHash,
            'role_id'       => $data['role_id'] ?? null,
        ];

        $this->execute($sql, $params);
        return (int)$this->lastInsertId();
    }

    /**
     * Update user password securely
     */
    public function updatePassword(int $userId, string $newPassword): void
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE {$this->table} 
                   SET password_hash = :password_hash, updated_at = NOW() 
                 WHERE id = :id";

        $this->execute($sql, [
            'password_hash' => $passwordHash,
            'id'            => $userId
        ]);
    }

    /**
     * Get all users with their roles
     */
    public function getAllWithRoles(): array
    {
        $sql = "
            SELECT u.*, r.name AS role_name
            FROM {$this->table} u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ";
        return $this->find($sql);
    }

    /**
     * Delete a user by ID
     */
    public function delete(int $userId): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id LIMIT 1";
        $affected = $this->execute($sql, ['id' => $userId]);
        return $affected > 0;
    }
}
