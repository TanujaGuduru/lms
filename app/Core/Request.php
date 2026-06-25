<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $routeParams = [];

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $data = array_merge($_GET, $_POST);

        if ($this->isJson()) {
            $jsonBody = json_decode(file_get_contents('php://input'), true) ?? [];
            $data = array_merge($data, $jsonBody);
        }

        return $data[$key] ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input($key, $default);
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->input($key, $default);
    }

    public function all(): array
    {
        if ($this->isJson()) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return array_merge($_GET, $_POST);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->all()[$key]) && $this->all()[$key] !== '';
    }

    public function file(string $key): array|null
    {
        return $_FILES[$key] ?? null;
    }

    public function isJson(): bool
    {
        return str_contains($this->header('Content-Type', ''), 'application/json');
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key])[0];
                if (filter_var(trim($ip), FILTER_VALIDATE_IP)) {
                    return trim($ip);
                }
            }
        }
        return '127.0.0.1';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
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

    public function validate(array $rules): array
    {
        return (new Validator($this->all()))->validate($rules);
    }

    public function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function isPost(): bool  { return $this->method() === 'POST'; }
    public function isGet(): bool   { return $this->method() === 'GET'; }
    public function isPut(): bool   { return $this->method() === 'PUT'; }
    public function isDelete(): bool { return $this->method() === 'DELETE'; }
}
