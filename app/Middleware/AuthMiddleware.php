<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(Request $request): mixed
    {
        if (!Auth::check()) {
            if ($request->isAjax()) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthenticated.', 'redirect' => '/login']);
                exit;
            }
            Session::flash('error', 'Please log in to continue.');
            Session::flash('intended_url', $request->uri());
            header('Location: /login');
            exit;
        }
        return null;
    }
}
