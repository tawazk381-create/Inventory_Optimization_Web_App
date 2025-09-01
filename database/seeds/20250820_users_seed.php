<?php
// File: database/seeds/20250820_users_seed.php

return function(PDO $DB){
    $DB->beginTransaction();

    $adminEmail = 'admin@example.com';
    $adminName  = 'Admin User';
    $adminPass  = 'password123'; // change later
    $passHash   = password_hash($adminPass, PASSWORD_DEFAULT);

    // Ensure Admin role exists
    $roleStmt = $DB->prepare("SELECT id FROM roles WHERE name = :name LIMIT 1");
    $roleStmt->execute(['name'=>'Admin']);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$role) {
        // Insert Admin role if missing
        $insRole = $DB->prepare("INSERT INTO roles (name, description, created_at) VALUES ('Admin','Auto-created Admin role', NOW())");
        $insRole->execute();
        $roleId = (int)$DB->lastInsertId();
    } else {
        $roleId = (int)$role['id'];
    }

    // Insert admin user if missing
    $stmt = $DB->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email'=>$adminEmail]);

    if (!$stmt->fetch()) {
        $ins = $DB->prepare("
            INSERT INTO users (name,email,password_hash,role_id,is_active,created_at)
            VALUES (:name,:email,:ph,:rid,1,NOW())
        ");
        $ins->execute([
            'name'  => $adminName,
            'email' => $adminEmail,
            'ph'    => $passHash,
            'rid'   => $roleId
        ]);
    }

    $DB->commit();
};
