<?php
// File: database/migrations/20250820_0006_create_batches.php
return function (PDO $db) {
    $sql = "
    CREATE TABLE IF NOT EXISTS batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        warehouse_id INT NOT NULL,
        batch_no VARCHAR(191) DEFAULT NULL,
        quantity INT NOT NULL DEFAULT 0,
        received_at DATETIME DEFAULT NULL,
        expiry_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_batches_item (item_id),
        INDEX idx_batches_warehouse (warehouse_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql);

    $sqlFk = "
    ALTER TABLE batches
    ADD CONSTRAINT fk_batches_item
      FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fk_batches_warehouse
      FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE ON UPDATE CASCADE;
    ";
    try {
        $db->exec($sqlFk);
    } catch (Throwable $e) {
        // ignore if exists
    }
};
