<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

class AuthMiddleware
{
    public function handle(Request $request): ?array
    {
        if (! Auth::userFromToken($request->bearerToken())) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => ['reason' => ['unauthenticated']],
            ]);
            exit;
        }

        return null;
    }
}
