<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $token): void
    {
        $user = $this->db->selectOne(
            "SELECT id FROM users WHERE email_verification_token = ? AND deleted_at IS NULL",
            [$token]
        );

        if (!$user) {
            $this->withFlash('error', 'This verification link is invalid or has already been used.')->redirect('/login');
        }

        $this->db->query(
            "UPDATE users SET email_verified_at=NOW(), email_verification_token=NULL WHERE id=?",
            [$user['id']]
        );

        AuditLogger::log('email_verified', 'auth', (string)$user['id']);
        $this->withFlash('success', 'Your email has been verified. Please sign in.')->redirect('/login');
    }
}
