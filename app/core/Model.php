<?php
// File: app/core/Model.php

class Model
{
    /** @var PDO */
    protected PDO $db;

    /** @var string|null Table name (to be set in subclasses) */
    protected ?string $table = null;

    public function __construct()
    {
        global $DB;
        if (!$DB instanceof PDO) {
            throw new RuntimeException("Database connection not initialized");
        }
        $this->db = $DB;
    }

    /**
     * Run a query and return all rows.
     */
    protected function find(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Run a query and return the first row or null.
     */
    protected function first(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Execute a query (INSERT, UPDATE, DELETE).
     * Returns affected row count.
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get the last inserted ID from the connection.
     */
    protected function lastInsertId(): string
    {
        return $this->db->lastInsertId();
    }
}
