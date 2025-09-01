<?php
// File: database/migrations/20250820_0007_create_stock_movements.php

return function (PDO $db) {
    // ---------------------------
    // Create stock_movements table
    // ---------------------------
    $sql = "
    CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        batch_id INT DEFAULT NULL,
        warehouse_from_id INT DEFAULT NULL,
        warehouse_to_id INT DEFAULT NULL,
        supplier_id INT DEFAULT NULL,
        raw_supplier_name VARCHAR(255) DEFAULT NULL,
        quantity INT NOT NULL,
        movement_type ENUM('entry','exit','transfer','adjustment') NOT NULL,
        reference VARCHAR(191) DEFAULT NULL,
        user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_sm_item (item_id),
        INDEX idx_sm_batch (batch_id),
        INDEX idx_sm_from (warehouse_from_id),
        INDEX idx_sm_to (warehouse_to_id),
        INDEX idx_sm_supplier (supplier_id),
        INDEX idx_sm_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql);

    // ---------------------------
    // Add foreign keys individually (ignore if already exist)
    // ---------------------------
    $fks = [
        "ALTER TABLE stock_movements 
         ADD CONSTRAINT fk_sm_item 
         FOREIGN KEY (item_id) REFERENCES items(id) 
         ON DELETE CASCADE ON UPDATE CASCADE",

        "ALTER TABLE stock_movements 
         ADD CONSTRAINT fk_sm_batch 
         FOREIGN KEY (batch_id) REFERENCES batches(id) 
         ON DELETE SET NULL ON UPDATE CASCADE",

        "ALTER TABLE stock_movements 
         ADD CONSTRAINT fk_sm_from 
         FOREIGN KEY (warehouse_from_id) REFERENCES warehouses(id) 
         ON DELETE SET NULL ON UPDATE CASCADE",

        "ALTER TABLE stock_movements 
         ADD CONSTRAINT fk_sm_to 
         FOREIGN KEY (warehouse_to_id) REFERENCES warehouses(id) 
         ON DELETE SET NULL ON UPDATE CASCADE",

        "ALTER TABLE stock_movements 
         ADD CONSTRAINT fk_sm_supplier 
         FOREIGN KEY (supplier_id) REFERENCES suppliers(id) 
         ON DELETE SET NULL ON UPDATE CASCADE",

        "ALTER TABLE stock_movements 
         ADD CONSTRAINT fk_sm_user 
         FOREIGN KEY (user_id) REFERENCES users(id) 
         ON DELETE SET NULL ON UPDATE CASCADE"
    ];

    foreach ($fks as $fk) {
        try {
            $db->exec($fk);
        } catch (Throwable $e) {
            // ignore if already exists
        }
    }

    // ---------------------------
    // Ensure warehouses table has "status" column
    // ---------------------------
    try {
        $check = $db->query("SHOW COLUMNS FROM warehouses LIKE 'status'")->fetch();
        if (!$check) {
            $db->exec("
                ALTER TABLE warehouses 
                ADD COLUMN status ENUM('active','inactive') 
                NOT NULL DEFAULT 'active' 
                AFTER contact
            ");
        }
    } catch (Throwable $e) {
        // Ignore errors (column may already exist or table missing)
    }
};
