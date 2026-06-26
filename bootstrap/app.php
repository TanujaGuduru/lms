<?php

declare(strict_types=1);

use App\Core\Session;
use App\Core\Setting;
use App\Core\Router;

// ── Load environment variables ────────────────────────────────────────────────
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ── Application constants ─────────────────────────────────────────────────────
$appConfig = require BASE_PATH . '/config/app.php';

define('APP_NAME',  $appConfig['name']);
define('APP_URL',   rtrim($appConfig['url'], '/'));
define('APP_ENV',   $appConfig['env']);
define('APP_DEBUG', $appConfig['debug']);
define('APP_KEY',   $appConfig['key']);
define('BASE_URL',  APP_URL);

// ── PHP error reporting ───────────────────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    \App\Core\Logger::error("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
    return false;
});

set_exception_handler(function (\Throwable $e): void {
    \App\Core\Logger::critical($e->getMessage(), ['trace' => $e->getTraceAsString()]);

    if (APP_DEBUG) {
        echo '<pre style="background:#1e1e2e;color:#cba6f7;padding:2rem;font-size:13px">';
        echo '<strong style="color:#f38ba8">' . get_class($e) . '</strong>: ';
        echo htmlspecialchars($e->getMessage()) . "\n\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        http_response_code(500);
        include BASE_PATH . '/resources/views/errors/500.php';
    }
    exit(1);
});

// ── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set($appConfig['timezone']);

// ── Session ───────────────────────────────────────────────────────────────────
Session::start();

// ── Settings cache ────────────────────────────────────────────────────────────
try {
    Setting::loadAll();
} catch (\Throwable) {
    // Silently fail if DB not connected yet
}

// ── Maintenance mode ─────────────────────────────────────────────────────────
if (Setting::isMaintenanceMode()) {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!str_starts_with($uri, '/super-admin') && !str_starts_with($uri, '/api')) {
        http_response_code(503);
        include BASE_PATH . '/resources/views/errors/maintenance.php';
        exit;
    }
}

// ── Security headers ──────────────────────────────────────────────────────────
if (APP_ENV !== 'development') {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self' https: data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.datatables.net; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:;");
}

// ── Router ────────────────────────────────────────────────────────────────────
$router = new Router();
require BASE_PATH . '/routes/web.php';
require BASE_PATH . '/routes/api.php';

return $router;
