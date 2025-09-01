<?php  
// File: database/migrations/20250820_0005_create_items.php

return function (PDO $db) {
    $sql = "
    CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(100) NOT NULL UNIQUE,
        barcode VARCHAR(191) DEFAULT NULL UNIQUE, -- ✅ optional barcode column for scanner lookup
        name VARCHAR(191) NOT NULL,
        description TEXT DEFAULT NULL,
        supplier_id INT DEFAULT NULL,
        warehouse_id INT DEFAULT NULL,
        unit VARCHAR(50) DEFAULT 'pcs',
        unit_price DECIMAL(12,2) DEFAULT 0.00,

        -- 🔢 Inventory control + planning fields
        total_stock INT NOT NULL DEFAULT 0, -- ✅ total stock across warehouses
        avg_daily_demand INT DEFAULT 0,     -- ✅ new field
        lead_time_days INT DEFAULT 0,       -- ✅ new field
        safety_stock INT DEFAULT 0,         -- ✅ already existed but keep here
        reorder_point INT DEFAULT 0,        -- ✅ already existed but keep here

        is_active TINYINT(1) NOT NULL DEFAULT 1, -- ✅ new column for item activation/deactivation

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        INDEX idx_items_supplier (supplier_id),
        INDEX idx_items_warehouse (warehouse_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql);

    // Ensure is_active exists for older tables
    try {
        $db->exec("ALTER TABLE items ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER reorder_point");
    } catch (Throwable $e) {
        // ignore if already exists
    }

    // Add supplier foreign key
    $sqlFk1 = "
    ALTER TABLE items
    ADD CONSTRAINT fk_items_supplier
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
    ";
    try {
        $db->exec($sqlFk1);
    } catch (Throwable $e) {
        // ignore if already exists
    }

    // Add warehouse foreign key
    $sqlFk2 = "
    ALTER TABLE items
    ADD CONSTRAINT fk_items_warehouse
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
    ";
    try {
        $db->exec($sqlFk2);
    } catch (Throwable $e) {
        // ignore if already exists
    }
};
