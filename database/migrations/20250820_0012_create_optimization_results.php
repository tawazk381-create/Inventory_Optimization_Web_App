<?php
// File: database/migrations/20250820_0012_create_optimization_results.php

return function (PDO $db) {
   $sql = "
CREATE TABLE IF NOT EXISTS optimization_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    item_id INT NOT NULL,
    eoq DECIMAL(12,2) DEFAULT NULL,
    reorder_point INT DEFAULT NULL,
    safety_stock INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,  -- Add this line
    INDEX idx_result_job (job_id),
    INDEX idx_result_item (item_id),
    UNIQUE KEY uq_job_item (job_id, item_id),
    CONSTRAINT fk_result_job FOREIGN KEY (job_id)
        REFERENCES optimization_jobs(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_result_item FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

    $db->exec($sql);
};
