<?php

// Same physical `codegurukul` MySQL database as the Admin panel — extended,
// not replaced. On GoDaddy this is the cPanel-provisioned MySQL 8 instance;
// identical connection shape to the Admin panel's own config/database.php.
return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
    'database' => $_ENV['DB_DATABASE'] ?? 'codegurukul',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
