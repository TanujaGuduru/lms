<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request): mixed
    {
        (new AuthMiddleware())->handle($request);

        $user = Auth::user();
        $allowedRoles = ['super_admin', 'admin'];

        if (!in_array($user['role_slug'] ?? '', $allowedRoles)) {
            if ($request->isAjax()) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Access denied. Insufficient privileges.']);
                exit;
            }
            http_response_code(403);
            include BASE_PATH . '/resources/views/errors/403.php';
            exit;
        }

        return null;
    }
}
