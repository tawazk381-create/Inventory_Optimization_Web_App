<?php
// File: database/migrations/run_php_migrations.php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

echo "== Running migrations ==\n";

// Get all migration files
$migrationFiles = glob(__DIR__ . '/*.php');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $name = basename($file);

    // Skip runner itself
    if ($name === 'run_php_migrations.php') {
        continue;
    }

    // ❌ Skip optimizations migration
    if ($name === '20250820_0008_create_optimizations.php') {
        echo "Skipping migration: $name (excluded)\n";
        continue;
    }

    echo "Running migration: $name ...\n";

    try {
        // Each migration file should return a callable
        $migrate = require $file;

        if (is_callable($migrate)) {
            $migrate($DB);
        } else {
            throw new Exception("Migration file $name did not return a callable.");
        }

        echo "  => OK\n";
    } catch (Throwable $e) {
        echo "  => ERROR: " . $e->getMessage() . "\n";
    }
}
