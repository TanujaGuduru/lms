<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Short-lived signed URLs for files stored on local disk
 * (storage/app/...), not S3 — this platform runs entirely on GoDaddy shared
 * hosting for the long term, by deliberate choice, with no external cloud
 * storage of any kind. The "signing" here doesn't authenticate against any
 * third-party service; it's just an HMAC over the file path + expiry using
 * this app's own local secret, exactly enough to make the resulting URL
 * short-lived and unguessable without re-implementing per-file ACL checks
 * on every request.
 */
class FileStorage
{
    public static function root(): string
    {
        return BASE_PATH . '/storage/app';
    }

    public static function absolutePath(string $relativePath): string
    {
        return self::root() . '/' . ltrim($relativePath, '/');
    }

    public static function signedUrl(string $relativePath, int $expiresInSeconds = 300): string
    {
        $appConfig = require BASE_PATH . '/config/app.php';
        $expiresAt = time() + $expiresInSeconds;
        $signature = self::sign($relativePath, $expiresAt, $appConfig['key']);
        $token = base64_encode($relativePath) . '.' . $expiresAt . '.' . $signature;

        return rtrim($appConfig['url'], '/') . '/v1/files/serve?token=' . urlencode($token);
    }

    /**
     * Returns the validated relative path, or false if the token is
     * malformed, expired, or its signature doesn't match.
     */
    public static function validate(string $token): string|false
    {
        $appConfig = require BASE_PATH . '/config/app.php';
        $parts = explode('.', $token, 3);
        if (count($parts) !== 3) {
            return false;
        }

        [$encodedPath, $expiresAt, $signature] = $parts;
        $relativePath = base64_decode($encodedPath, true);
        if ($relativePath === false || ! ctype_digit($expiresAt)) {
            return false;
        }

        if ((int) $expiresAt < time()) {
            return false;
        }

        $expectedSignature = self::sign($relativePath, (int) $expiresAt, $appConfig['key']);
        if (! hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // Defence against ../ traversal even though the path component
        // itself is signed (an attacker who never had a valid signed token
        // for a traversal path can't get one — this is a second, redundant
        // layer of protection on top of that, not the only one).
        $resolved = realpath(self::absolutePath($relativePath));
        if ($resolved === false || ! str_starts_with($resolved, realpath(self::root()))) {
            return false;
        }

        return $relativePath;
    }

    private static function sign(string $relativePath, int $expiresAt, string $appKey): string
    {
        return hash_hmac('sha256', "{$relativePath}:{$expiresAt}", $appKey);
    }
}
