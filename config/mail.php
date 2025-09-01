<?php // File: config/mail.php

return [
    'from' => getenv('MAIL_FROM_ADDRESS') ?: getenv('MAIL_FROM') ?: 'no-reply@example.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: getenv('MAIL_FROM_NAME') ?: 'Inventory App',
    'transport' => getenv('MAIL_TRANSPORT') ?: 'smtp',
    'smtp' => [
        'host' => getenv('MAIL_HOST') ?: 'smtp.example.com',
        'port' => getenv('MAIL_PORT') ?: 587,
        'user' => getenv('MAIL_USERNAME') ?: getenv('MAIL_USER') ?: '',
        'pass' => getenv('MAIL_PASSWORD') ?: getenv('MAIL_PASS') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: null,
    ],
];
