<?php

declare(strict_types=1);

// Minimal bootstrap for cron/*.php scripts — bootstrap/app.php is HTTP-only
// (router, CORS, security headers), none of which apply to a CLI cron run.
// GoDaddy cPanel's Cron Jobs feature runs `php /path/to/cron/whatever.php`
// directly, with no web request involved at all.

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $class = str_replace('App' . DIRECTORY_SEPARATOR, '', $class);
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (! isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$appConfig = require BASE_PATH . '/config/app.php';
date_default_timezone_set($appConfig['timezone']);

set_exception_handler(function (\Throwable $e): void {
    \App\Core\Logger::critical('[cron] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
});
