<?php

// GoDaddy's own cPanel mail service (part of the hosting package already
// purchased — not a third-party cloud add-on) — same MAIL_* naming
// convention as the Admin panel's .env.example, so the two apps can point
// at the same mailbox/credentials without inventing a second naming scheme.
return [
    'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls', // 'tls' (STARTTLS), 'ssl' (implicit TLS), or 'none'
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@codegurukul.in',
    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'CodeGurukul',
    'timeout_seconds' => (int) ($_ENV['MAIL_TIMEOUT_SECONDS'] ?? 10),
];
