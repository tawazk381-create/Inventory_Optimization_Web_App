<?php
// File: scripts/hash_existing_passwords.php

require_once __DIR__ . '/../config/database.php';

global $DB;

$stmt = $DB->query("SELECT id, email, password_hash FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$users) {
    echo "No users found in the database.\n";
    exit;
}

$updated = 0;

foreach ($users as $user) {
    $hash = $user['password_hash'];

    if (substr($hash, 0, 4) !== '$2y$') {
        $newHash = password_hash($hash, PASSWORD_DEFAULT);
        $update = $DB->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $update->execute(['hash' => $newHash, 'id' => $user['id']]);

        echo "✅ Updated user {$user['email']} (ID {$user['id']})\n";
        $updated++;
    } else {
        echo "ℹ️ Skipped user {$user['email']} (already hashed)\n";
    }
}

echo "\nDone. Updated {$updated} user(s).\n";
