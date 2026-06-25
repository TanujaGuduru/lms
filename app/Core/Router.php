<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewareGroups = [];
    private array $namedRoutes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, array|string $handler, string $name = ''): static
    {
        return $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, array|string $handler, string $name = ''): static
    {
        return $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, array|string $handler, string $name = ''): static
    {
        return $this->addRoute('PUT', $path, $handler, $name);
    }

    public function delete(string $path, array|string $handler, string $name = ''): static
    {
        return $this->addRoute('DELETE', $path, $handler, $name);
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($attributes['prefix'])) {
            $this->prefix = $previousPrefix . '/' . trim($attributes['prefix'], '/');
        }
        if (isset($attributes['middleware'])) {
            $this->groupMiddleware = array_merge(
                $previousMiddleware,
                (array)$attributes['middleware']
            );
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    private function addRoute(string $method, string $path, array|string $handler, string $name): static
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        $route = [
            'method'     => $method,
            'path'       => $fullPath,
            'pattern'    => $this->pathToPattern($fullPath),
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
            'name'       => $name,
        ];

        $this->routes[] = $route;

        if ($name) {
            $this->namedRoutes[$name] = $fullPath;
        }

        return $this;
    }

    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): mixed
    {
        $method = $request->method();
        $uri    = '/' . trim(parse_url($request->uri(), PHP_URL_PATH), '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && !($method === 'POST' && $request->input('_method') === $route['method'])) {
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
        if (defined('IS_API') && IS_API) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Route not found.', 'code' => 404]);
        } else {
            include BASE_PATH . '/resources/views/errors/404.php';
        }
        exit;
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            return '#';
        }
        $path = $this->namedRoutes[$name];
        foreach ($params as $value) {
            $path = preg_replace('/\([^)]+\)/', $value, $path, 1);
        }
        return $path;
    }
}
