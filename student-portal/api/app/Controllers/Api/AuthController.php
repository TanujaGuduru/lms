<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;

/**
 * Auth & Account — docs/student-module/04a-apis-conventions-enrollment-billing.md.
 */
class AuthController extends Controller
{
    public function login(Request $request): void
    {
        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if (! $email || ! $password) {
            $this->fail('Email and password are required.', [
                'email' => $email ? [] : ['required'],
                'password' => $password ? [] : ['required'],
            ]);
        }

        $user = Auth::attempt($email, $password);

        // Generic failure message regardless of which check fails — never
        // reveals whether the email exists, same account-enumeration defense
        // as the Admin panel's login (04a).
        if (! $user) {
            $this->fail('These credentials do not match our records.', [], 401);
        }

        if ($user['status'] === 'pending') {
            $this->fail('Account is pending parental consent.', ['reason' => ['pending_consent']], 403);
        }

        if ($user['status'] !== 'active') {
            $this->fail('Account is not active.', ['reason' => ['account_' . $user['status']]], 403);
        }

        $token = Auth::issueToken((int) $user['id']);
        $this->db->updateTable('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);

        $this->success([
            'token' => $token,
            'user' => $this->presentUser($user),
        ]);
    }

    public function me(Request $request): void
    {
        $user = $this->currentUser();
        $this->success($this->presentUser($user));
    }

    public function logout(Request $request): void
    {
        $token = $request->bearerToken();
        if ($token) {
            Auth::revokeToken($token);
        }
        $this->success(null);
    }

    /**
     * docs/student-module/04a: "Rotate token before expiry" — a logged-in
     * client trades its current token for a fresh one without re-entering a
     * password, so a long-lived session never has to fully re-authenticate.
     */
    public function refresh(Request $request): void
    {
        $user = $this->currentUser();
        $oldToken = $request->bearerToken();

        $newToken = Auth::issueToken((int) $user['id']);
        if ($oldToken) {
            Auth::revokeToken($oldToken);
        }

        $this->success(['token' => $newToken]);
    }

    public function forgotPassword(Request $request): void
    {
        $email = trim((string) $request->input('email', ''));
        if (! $email) {
            $this->fail('Email is required.', ['email' => ['required']]);
        }

        $user = $this->db->fetchOne('SELECT id, email FROM users WHERE email = ? AND deleted_at IS NULL', [strtolower($email)]);

        // Always the same response whether or not the account exists — same
        // account-enumeration defense as login (04a).
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $this->db->updateTable('users', [
                'password_reset_token' => hash('sha256', $token),
                'password_reset_expires' => date('Y-m-d H:i:s', strtotime('+60 minutes')),
            ], 'id = ?', [$user['id']]);

            // No SMTP configured in this pass — logged instead of actually
            // emailed; swap for a real mailer call once one exists.
            Logger::info('Password reset requested', ['email' => $user['email'], 'token' => $token]);
        }

        $this->success(['message' => 'If that email exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): void
    {
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');

        if (! $token || ! $password) {
            $this->fail('Token and new password are required.', [
                'token' => $token ? [] : ['required'],
                'password' => $password ? [] : ['required'],
            ]);
        }

        if (strlen($password) < 8) {
            $this->fail('Password does not meet requirements.', ['password' => ['min:8']]);
        }

        $user = $this->db->fetchOne(
            'SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() AND deleted_at IS NULL',
            [hash('sha256', $token)]
        );

        if (! $user) {
            $this->fail('This reset link is invalid or has expired.', ['token' => ['invalid_or_expired']], 422);
        }

        $this->db->updateTable('users', [
            'password_hash' => Auth::hash($password),
            'password_reset_token' => null,
            'password_reset_expires' => null,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$user['id']]);

        // A password reset invalidates every existing session — a token that
        // leaked alongside the old password shouldn't survive the reset meant
        // to recover from it.
        $this->db->execute('DELETE FROM api_tokens WHERE user_id = ?', [$user['id']]);

        $this->success(['message' => 'Password updated. Please sign in again.']);
    }

    /**
     * Shapes the response per 04a: role, name, and (for a parent) every
     * linked child with that specific link's own visibility booleans.
     */
    private function presentUser(array $user): array
    {
        $shaped = [
            'id' => (int) $user['id'],
            'role' => $user['role_slug'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
        ];

        if ($user['role_slug'] === 'parent') {
            $shaped['linked_students'] = array_map(
                fn (array $row) => [
                    'student_id' => (int) $row['student_id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'can_view_billing' => (bool) $row['can_view_billing'],
                    'can_view_recordings' => (bool) $row['can_view_recordings'],
                    'can_view_attendance' => (bool) $row['can_view_attendance'],
                    'can_book_ptm' => (bool) $row['can_book_ptm'],
                ],
                Auth::linkedStudents((int) $user['id'])
            );
        }

        // IMPLEMENTATION GAP-FILL: no endpoint anywhere let the frontend
        // discover its own current enrollment_id/course_id — every other
        // endpoint that needs one (ProgressController, EnrollmentController)
        // requires the caller to already have it, with no "give me mine"
        // entry point. /auth/me is the one call every page already makes
        // on load, so surfacing it here means every page gets it for free
        // rather than adding a second round-trip. Only the most recent
        // active enrollment — same "one course at a time" assumption
        // App\Controllers\Api\BatchController::current() already makes.
        if ($user['role_slug'] === 'student') {
            $enrollment = $this->db->fetchOne(
                "SELECT e.id, e.course_id, c.title AS course_title FROM enrollments e
                 JOIN courses c ON c.id = e.course_id
                 WHERE e.user_id = ? AND e.status = 'active' ORDER BY e.enrolled_at DESC LIMIT 1",
                [$user['id']]
            );
            $shaped['current_enrollment'] = $enrollment ? [
                'enrollment_id' => (int) $enrollment['id'],
                'course_id' => (int) $enrollment['course_id'],
                'course_title' => $enrollment['course_title'],
            ] : null;
        }

        return $shaped;
    }
}
