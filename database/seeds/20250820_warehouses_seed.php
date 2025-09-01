<?php
// File: database/seeds/20250820_warehouses_seed.php
return function(PDO $DB){
    $DB->beginTransaction();
    $warehouses = [
        ['Main Warehouse','Cape Town, ZA','warehouse1@example.com'],
        ['Secondary Warehouse','Johannesburg, ZA','warehouse2@example.com']
    ];
    $stmt = $DB->prepare("INSERT IGNORE INTO warehouses (name, location, contact, created_at) VALUES (:name,:loc,:contact,NOW())");
    foreach ($warehouses as $w) {
        $stmt->execute(['name'=>$w[0],'loc'=>$w[1],'contact'=>$w[2]]);
    }
    $DB->commit();
};
