<?php

return [
    'host'      => $_ENV['DB_HOST']      ?? 'localhost',
    'port'      => (int)($_ENV['DB_PORT'] ?? 3306),
    'database'  => $_ENV['DB_DATABASE']  ?? 'codegurukul',
    'username'  => $_ENV['DB_USERNAME']  ?? 'root',
    'password'  => $_ENV['DB_PASSWORD']  ?? '',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];
