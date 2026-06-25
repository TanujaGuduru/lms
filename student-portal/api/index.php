<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

// ── Autoloader ───────────────────────────────────────────────────────────────
spl_autoload_register(function (string $class): void {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    $class = str_replace('App' . DIRECTORY_SEPARATOR, '', $class);
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ── Bootstrap ────────────────────────────────────────────────────────────────
/** @var \App\Core\Router $router */
$router = require BASE_PATH . '/bootstrap/app.php';
$request = new \App\Core\Request();

// ── Dispatch ─────────────────────────────────────────────────────────────────
$router->dispatch($request);
