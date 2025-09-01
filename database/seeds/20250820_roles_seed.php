<?php
// File: database/seeds/20250820_roles_seed.php

return function(PDO $DB){
    $DB->beginTransaction();

    $roles = [
        ['name'=>'Admin','description'=>'Administrator - full access'],
        ['name'=>'Manager','description'=>'Manager - moderate access'],
        ['name'=>'Staff','description'=>'Staff - limited access']
    ];

    $stmt = $DB->prepare("
        INSERT IGNORE INTO roles (name, description, created_at)
        VALUES (:name, :description, NOW())
    ");

    foreach ($roles as $r) {
        $stmt->execute([
            'name'        => $r['name'],
            'description' => $r['description']
        ]);
    }

    $DB->commit();
};
