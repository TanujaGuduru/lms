<?php

declare(strict_types=1);

use App\Core\Router;

// ── Load environment variables ──────────────────────────────────────────────
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

// ── Application constants ───────────────────────────────────────────────────
$appConfig = require BASE_PATH . '/config/app.php';

define('APP_ENV', $appConfig['env']);
define('APP_DEBUG', $appConfig['debug']);

// Fail loudly rather than silently signing file-download URLs with an empty
// secret — an empty APP_KEY would make every FileStorage signature
// trivially forgeable (anyone could compute hash_hmac(..., '') themselves).
if (! $appConfig['key']) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server misconfigured: APP_KEY is not set.']);
    exit;
}

// ── PHP error reporting ─────────────────────────────────────────────────────
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

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => APP_DEBUG ? $e->getMessage() : 'Internal server error.',
    ]);
    exit;
});

date_default_timezone_set($appConfig['timezone']);

// ── CORS — only matters for local dev where the frontend runs on a different
// port; in production both are served from the same domain (see README.md),
// so this header is harmless but unnecessary there.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && (APP_ENV === 'development' || $origin === rtrim($appConfig['frontend_url'], '/'))) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Security headers ─────────────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ── Router ───────────────────────────────────────────────────────────────────
$router = new Router();
require BASE_PATH . '/routes/api.php';

return $router;
