<?php
// File: database/seeds/20250820_suppliers_seed.php
return function(PDO $DB){
    $DB->beginTransaction();
    $suppliers = [
        ['Acme Supplies','John Doe','sales@acme.example','+27100000001','1 Acme Road'],
        ['Global Fasteners','Jane Smith','info@globalfast.example','+27100000002','2 Fastener Lane']
    ];
    $stmt = $DB->prepare("INSERT IGNORE INTO suppliers (name, contact_name, contact_email, phone, address, created_at) VALUES (:name,:cn,:ce,:ph,:ad,NOW())");
    foreach ($suppliers as $s) {
        $stmt->execute([
            'name'=>$s[0],
            'cn'=>$s[1],
            'ce'=>$s[2],
            'ph'=>$s[3],
            'ad'=>$s[4]
        ]);
    }
    $DB->commit();
};
