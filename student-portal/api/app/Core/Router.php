<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, array|string $handler): static
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array|string $handler): static
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array|string $handler): static
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, array|string $handler): static
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, array|string $handler): static
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->prefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }
        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge($previousMiddleware, (array) $attributes['middleware']);
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function addRoute(string $method, string $path, array|string $handler): static
    {
        $fullPath = '/' . trim($this->prefix . '/' . ltrim($path, '/'), '/');

        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->pathToPattern($fullPath),
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
        ];

        return $this;
    }

    private function pathToPattern(string $path): string
    {
        return '#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $path) . '$#';
    }

    public function dispatch(Request $request): mixed
    {
        $method = $request->method();
        $uri = '/' . trim((string) parse_url($request->uri(), PHP_URL_PATH), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);
                $request->setRouteParams($matches);

                foreach ($route['middleware'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $result = $middleware->handle($request);
                    if ($result !== null) {
                        return $result;
                    }
                }

                return $this->callHandler($route['handler'], $request, $matches);
            }
        }

        return $this->notFound();
    }

    private function callHandler(array|string $handler, Request $request, array $params): mixed
    {
        if (is_string($handler)) {
            [$controllerClass, $method] = explode('@', $handler);
        } else {
            [$controllerClass, $method] = $handler;
        }

        $fullClass = "App\\Controllers\\{$controllerClass}";
        $controller = new $fullClass();
        return $controller->$method($request, ...$params);
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Route not found.', 'errors' => ['reason' => ['not_found']]]);
        exit;
    }
}
