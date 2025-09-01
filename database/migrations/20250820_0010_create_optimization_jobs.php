<?php 
// File: database/migrations/20250820_0010_create_optimization_jobs.php

return function (PDO $db) {
    // Create table if it does not exist
    $sql = "
        CREATE TABLE IF NOT EXISTS optimization_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            horizon_days INT NOT NULL,
            service_level DECIMAL(5,2) NOT NULL,
            status ENUM('pending','running','complete','failed') NOT NULL DEFAULT 'pending',
            items_total INT NOT NULL DEFAULT 0,
            items_processed INT NOT NULL DEFAULT 0,
            results JSON DEFAULT NULL, -- store summary only (counts, errors, etc.)
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            started_at DATETIME DEFAULT NULL,  -- Added started_at column
            INDEX idx_jobs_user (user_id),
            INDEX idx_jobs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql);

    // Ensure columns exist if table was created earlier without them
    try {
        $db->exec("ALTER TABLE optimization_jobs ADD COLUMN items_total INT NOT NULL DEFAULT 0 AFTER status");
    } catch (Throwable $e) {
        // ignore if already exists
    }

    try {
        $db->exec("ALTER TABLE optimization_jobs ADD COLUMN items_processed INT NOT NULL DEFAULT 0 AFTER items_total");
    } catch (Throwable $e) {
        // ignore if already exists
    }

    // Add foreign key to users
    $sqlFk = "
        ALTER TABLE optimization_jobs
        ADD CONSTRAINT fk_jobs_user FOREIGN KEY (user_id) 
            REFERENCES users(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE;
    ";
    try {
        $db->exec($sqlFk);
    } catch (Throwable $e) {
        // ignore if already exists
    }
};
