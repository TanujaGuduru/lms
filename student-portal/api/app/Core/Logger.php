<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    private static function path(): string
    {
        return BASE_PATH . '/storage/logs';
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $file = static::path() . "/{$date}.log";
        $contextStr = ! empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$time}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        if (! is_dir(static::path())) {
            mkdir(static::path(), 0755, true);
        }

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void { static::write('DEBUG', $message, $context); }
    public static function info(string $message, array $context = []): void { static::write('INFO', $message, $context); }
    public static function warning(string $message, array $context = []): void { static::write('WARNING', $message, $context); }
    public static function error(string $message, array $context = []): void { static::write('ERROR', $message, $context); }
    public static function critical(string $message, array $context = []): void { static::write('CRITICAL', $message, $context); }
}
