<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;

class CsrfMiddleware
{
    private array $except = ['/api/', '/webhook/'];

    public function handle(Request $request): mixed
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return null;
        }

        foreach ($this->except as $pattern) {
            if (str_contains($request->uri(), $pattern)) {
                return null;
            }
        }

        $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-Token');

        if (!$token || !Session::verifyCsrf($token)) {
            if ($request->isAjax()) {
                http_response_code(419);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'CSRF token mismatch. Please refresh and try again.']);
                exit;
            }
            http_response_code(419);
            include BASE_PATH . '/resources/views/errors/419.php';
            exit;
        }

        return null;
    }
}
