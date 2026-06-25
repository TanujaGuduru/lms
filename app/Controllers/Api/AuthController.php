<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

class AuthController
{
    public function login(Request $request): void
    {
        $email    = trim($request->post('email', ''));
        $password = $request->post('password', '');

        if (!$email || !$password) {
            $this->json(['success'=>false,'message'=>'Email and password are required'], 422);
            return;
        }

        $db   = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT u.*, r.slug as role_slug FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.deleted_at IS NULL LIMIT 1",
            [$email]
        );

        if (!$user || !Auth::verify($password, $user['password_hash'])) {
            $this->json(['success'=>false,'message'=>'Invalid credentials'], 401);
            return;
        }

        if ($user['status'] !== 'active') {
            $this->json(['success'=>false,'message'=>'Account is not active'], 403);
            return;
        }

        // Generate API token
        $token = bin2hex(random_bytes(32));
        $db->execute(
            "INSERT INTO api_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())",
            [$user['id'], hash('sha256', $token)]
        );

        $db->execute("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);

        $this->json([
            'success' => true,
            'token'   => $token,
            'expires_in' => 2592000,
            'user'    => [
                'id'         => $user['id'],
                'name'       => $user['first_name'] . ' ' . $user['last_name'],
                'email'      => $user['email'],
                'role'       => $user['role_slug'],
                'avatar'     => $user['avatar'],
            ],
        ]);
    }

    public function logout(Request $request): void
    {
        $token = $this->getBearerToken();
        if ($token) {
            Database::getInstance()->execute(
                "DELETE FROM api_tokens WHERE token = ?",
                [hash('sha256', $token)]
            );
        }
        $this->json(['success'=>true,'message'=>'Logged out']);
    }

    public function me(Request $request): void
    {
        $user = $this->tokenUser();
        if (!$user) { $this->json(['success'=>false,'message'=>'Unauthenticated'], 401); return; }

        $this->json([
            'success' => true,
            'data'    => [
                'id'         => $user['id'],
                'name'       => $user['first_name'] . ' ' . $user['last_name'],
                'email'      => $user['email'],
                'phone'      => $user['phone'],
                'role'       => $user['role_slug'],
                'avatar'     => $user['avatar'],
                'created_at' => $user['created_at'],
            ],
        ]);
    }

    public function refresh(Request $request): void
    {
        $token = $this->getBearerToken();
        if (!$token) { $this->json(['success'=>false,'message'=>'No token provided'], 401); return; }

        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT * FROM api_tokens WHERE token = ? AND expires_at > NOW()", [hash('sha256', $token)]);
        if (!$row) { $this->json(['success'=>false,'message'=>'Token invalid or expired'], 401); return; }

        $newToken = bin2hex(random_bytes(32));
        $db->execute(
            "UPDATE api_tokens SET token = ?, expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?",
            [hash('sha256', $newToken), $row['id']]
        );

        $this->json(['success'=>true,'token'=>$newToken,'expires_in'=>2592000]);
    }

    private function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    private function tokenUser(): ?array
    {
        $token = $this->getBearerToken();
        if (!$token) return null;

        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT u.*, r.slug as role_slug
             FROM api_tokens t
             JOIN users u ON u.id = t.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE t.token = ? AND t.expires_at > NOW() AND u.deleted_at IS NULL",
            [hash('sha256', $token)]
        );
        return $row ?: null;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
