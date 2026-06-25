<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Logger;

class ForgotPasswordController extends Controller
{
    public function show(Request $request): void
    {
        $this->render('auth.forgot-password', ['title' => 'Forgot Password — CodeGurukul LMS']);
    }

    public function send(Request $request): void
    {
        $data = $this->validate($request, ['email' => 'required|email']);

        $user = $this->db->selectOne(
            "SELECT id FROM users WHERE email = ? AND deleted_at IS NULL",
            [strtolower(trim($data['email']))]
        );

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $this->db->query(
                "UPDATE users SET password_reset_token=?, password_reset_expires=DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id=?",
                [hash('sha256', $token), $user['id']]
            );

            $resetUrl = APP_URL . '/reset-password/' . $token;
            Logger::info('Password reset requested', ['user_id' => $user['id'], 'reset_url' => $resetUrl]);
            AuditLogger::log('password_reset_requested', 'auth', (string)$user['id']);
        }

        // Same message regardless of whether the email exists, to avoid account enumeration.
        $this->withFlash('success', 'If an account exists for that email, a password reset link has been sent.')
             ->redirect('/forgot-password');
    }

    public function showReset(Request $request, string $token): void
    {
        $user = $this->db->selectOne(
            "SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND deleted_at IS NULL",
            [hash('sha256', $token)]
        );

        if (!$user) {
            $this->withFlash('error', 'This password reset link is invalid or has expired.')->redirect('/forgot-password');
        }

        $this->render('auth.reset-password', ['title' => 'Reset Password — CodeGurukul LMS', 'token' => $token]);
    }

    public function reset(Request $request): void
    {
        $data = $this->validate($request, [
            'token'    => 'required',
            'password' => 'required|password_strength|confirmed',
        ]);

        $user = $this->db->selectOne(
            "SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND deleted_at IS NULL",
            [hash('sha256', $data['token'])]
        );

        if (!$user) {
            $this->withFlash('error', 'This password reset link is invalid or has expired.')->redirect('/forgot-password');
        }

        $newHash = Auth::hash($data['password']);
        $this->db->query(
            "UPDATE users SET password_hash=?, password_changed_at=NOW(), password_reset_token=NULL, password_reset_expires=NULL WHERE id=?",
            [$newHash, $user['id']]
        );
        $this->db->insert("INSERT INTO password_history (user_id,password_hash,created_at) VALUES (?,?,NOW())", [$user['id'], $newHash]);

        AuditLogger::log('password_reset_completed', 'auth', (string)$user['id']);
        $this->withFlash('success', 'Your password has been reset. Please sign in.')->redirect('/login');
    }
}
