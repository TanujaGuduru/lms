<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $routeParams = [];
    private ?array $jsonBody = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    private function jsonBody(): array
    {
        if ($this->jsonBody === null) {
            $this->jsonBody = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        }
        return $this->jsonBody;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $data = array_merge($_GET, $_POST, $this->isJson() ? $this->jsonBody() : []);
        return $data[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->isJson()) {
            return array_merge($_GET, $this->jsonBody());
        }
        return array_merge($_GET, $_POST);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        $all = $this->all();
        return isset($all[$key]) && $all[$key] !== '';
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type'), 'application/json');
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (! empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        return '127.0.0.1';
    }

    public function header(string $name, string $default = ''): string
    {
        // Content-Type and Content-Length are the one well-documented
        // exception to the HTTP_-prefix convention PHP's CGI/Apache SAPI
        // uses for every other header — they're exposed as $_SERVER['CONTENT_TYPE']/
        // ['CONTENT_LENGTH'] directly, never with an HTTP_ prefix. PHP's own
        // built-in dev server (php -S) happens to populate both forms, which
        // masked this everywhere this app was tested before a real Apache
        // deployment — on Apache, only the unprefixed form actually exists.
        $normalized = strtoupper(str_replace('-', '_', $name));
        if ($normalized === 'CONTENT_TYPE' || $normalized === 'CONTENT_LENGTH') {
            return $_SERVER[$normalized] ?? $default;
        }

        $key = 'HTTP_' . $normalized;
        return $_SERVER[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(int $index): ?string
    {
        return $this->routeParams[$index] ?? null;
    }
}
