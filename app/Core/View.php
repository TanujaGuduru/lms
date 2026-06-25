<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    private string $viewsPath;
    private array $sharedData = [];

    public function __construct()
    {
        $this->viewsPath = BASE_PATH . '/resources/views';
    }

    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    public function render(string $template, array $data = []): void
    {
        $filePath = $this->viewsPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!file_exists($filePath)) {
            throw new \RuntimeException("View not found: {$filePath}");
        }

        // Auto-detect layout from template path prefix; override via $data['layout']
        $layout = $data['layout'] ?? null;
        if ($layout === null && str_starts_with($template, 'super-admin.')) {
            $layout = 'super-admin';
        }
        unset($data['layout']);

        $data = array_merge($this->sharedData, $data, [
            'flashSuccess' => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
            'flashWarning' => Session::getFlash('warning'),
            'flashInfo'    => Session::getFlash('info'),
            'errors'       => Session::getFlash('errors') ?? [],
            'oldInput'     => Session::getFlash('old') ?? [],
            'currentUser'  => Auth::user(),
            'csrfToken'    => Session::csrfToken(),
        ]);

        if ($layout) {
            // $__contentFile is used by the layout via: include $__contentFile ?? '';
            $__contentFile = $filePath;
            extract($data, EXTR_SKIP);
            $layoutPath = $this->viewsPath . '/layouts/' . $layout . '.php';
            if (!file_exists($layoutPath)) {
                throw new \RuntimeException("Layout not found: {$layoutPath}");
            }
            include $layoutPath;
        } else {
            extract($data, EXTR_SKIP);
            include $filePath;
        }
    }

    public function partial(string $partial, array $data = []): void
    {
        $this->render("partials.{$partial}", $data);
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function old(string $key, string $default = ''): string
    {
        $old = Session::getFlash('old') ?? [];
        return static::e($old[$key] ?? $default);
    }

    public static function hasError(string $field): bool
    {
        $errors = Session::getFlash('errors') ?? [];
        return isset($errors[$field]);
    }

    public static function error(string $field): string
    {
        $errors = Session::getFlash('errors') ?? [];
        return static::e($errors[$field] ?? '');
    }

    public static function asset(string $path): string
    {
        return BASE_URL . '/assets/' . ltrim($path, '/');
    }

    public static function url(string $path = ''): string
    {
        return BASE_URL . '/' . ltrim($path, '/');
    }

    public static function active(string $path, string $class = 'active'): string
    {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return str_starts_with($current, $path) ? $class : '';
    }

    public static function formatDate(string|null $date, string $format = 'd M Y'): string
    {
        if (!$date) return '—';
        return date($format, strtotime($date));
    }

    public static function formatMoney(float $amount, string $currency = 'INR'): string
    {
        if ($currency === 'INR') {
            return '₹' . number_format($amount, 2);
        }
        return $currency . ' ' . number_format($amount, 2);
    }

    public static function timeAgo(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('d M Y', $timestamp);
    }

    public static function badge(string $status): string
    {
        $map = [
            'active'    => 'success',
            'inactive'  => 'secondary',
            'pending'   => 'warning',
            'suspended' => 'danger',
            'draft'     => 'secondary',
            'published' => 'success',
            'completed' => 'info',
            'cancelled' => 'danger',
            'paid'      => 'success',
            'failed'    => 'danger',
        ];
        $color = $map[strtolower($status)] ?? 'primary';
        $label = ucfirst(str_replace('_', ' ', $status));
        return "<span class=\"badge badge-soft-{$color}\">{$label}</span>";
    }
}
