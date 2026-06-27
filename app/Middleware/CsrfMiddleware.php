<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;

class CsrfMiddleware
{
    // /api/ used to be exempted wholesale, which meant the protected
    // /api/v1/* group (routes/api.php) had no CSRF check at all - it relies
    // on the same ambient session cookie as the rest of this app, so it
    // needs the same protection. /webhook/ stays exempted: those calls
    // come from external services authenticated by their own signature
    // scheme, not a browser session, so a same-site CSRF token is never
    // going to be present for them in the first place.
    private array $except = ['/webhook/'];

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
