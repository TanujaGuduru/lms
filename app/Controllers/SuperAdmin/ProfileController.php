<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\AuditLogger;
use App\Models\User;

class ProfileController extends Controller
{
    public function index(Request $request): void
    {
        $user     = $this->currentUser();
        $activity = $this->db->select(
            "SELECT * FROM audit_logs WHERE user_id=? ORDER BY created_at DESC LIMIT 10",
            [$user['id']]
        );
        $sessions = $this->db->select(
            "SELECT * FROM user_sessions WHERE user_id=? AND is_active=1 ORDER BY last_activity DESC",
            [$user['id']]
        );

        $this->render('super-admin.profile.index', [
            'title'    => 'My Profile',
            'user'     => $user,
            'activity' => $activity,
            'sessions' => $sessions,
        ]);
    }

    public function update(Request $request): void
    {
        $user = $this->currentUser();

        $data = $this->validate($request, [
            'first_name' => 'required|min:2|max:80',
            'last_name'  => 'required|min:2|max:80',
            'phone'      => 'phone',
            'bio'        => 'max:500',
        ]);

        $this->db->query(
            "UPDATE users SET first_name=?,last_name=?,phone=?,bio=?,updated_at=NOW() WHERE id=?",
            [$data['first_name'],$data['last_name'],$data['phone']??'',$data['bio']??'',$user['id']]
        );

        // Clear auth cache so next request picks up new name
        Auth::clearCache();
        AuditLogger::log('profile_updated', 'profile', (string)$user['id']);

        $this->withFlash('success', 'Profile updated.')->redirect('/super-admin/profile');
    }

    public function changePassword(Request $request): void
    {
        $user = $this->currentUser();

        $data = $this->validate($request, [
            'current_password' => 'required',
            'new_password'     => 'required|password_strength|confirmed',
        ]);

        $stored = $this->db->selectOne("SELECT password_hash FROM users WHERE id=?", [$user['id']]);
        if (!password_verify($data['current_password'], $stored['password_hash'] ?? '')) {
            $this->withFlash('error', 'Current password is incorrect.')->back();
        }

        // Check password history (last 5)
        $history = $this->db->select(
            "SELECT password_hash FROM password_history WHERE user_id=? ORDER BY created_at DESC LIMIT 5",
            [$user['id']]
        );
        foreach ($history as $h) {
            if (password_verify($data['new_password'], $h['password_hash'])) {
                $this->withFlash('error', 'You cannot reuse a recent password.')->back();
            }
        }

        $newHash = Auth::hash($data['new_password']);
        $this->db->query("UPDATE users SET password_hash=?,password_changed_at=NOW() WHERE id=?", [$newHash, $user['id']]);
        $this->db->insert("INSERT INTO password_history (user_id,password_hash,created_at) VALUES (?,?,NOW())", [$user['id'], $newHash]);

        AuditLogger::log('password_changed', 'profile', (string)$user['id']);
        $this->withFlash('success', 'Password changed. Please log in again.')
             ->redirect('/logout');
    }

    public function updateAvatar(Request $request): void
    {
        $user = $this->currentUser();

        if (empty($_FILES['avatar']['tmp_name']) || !is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            $this->withFlash('error', 'No file selected.')->back();
        }

        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
            $this->withFlash('error', 'Invalid file type. Use JPG, PNG, or WebP.')->back();
        }

        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
            $this->withFlash('error', 'File too large. Max 2MB.')->back();
        }

        $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
        $dest     = PUBLIC_PATH . '/uploads/avatars/' . $filename;
        @mkdir(dirname($dest), 0755, true);

        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $this->withFlash('error', 'Upload failed.')->back();
        }

        // Delete old avatar if custom
        if (!empty($user['avatar']) && str_starts_with($user['avatar'], '/uploads/')) {
            @unlink(PUBLIC_PATH . $user['avatar']);
        }

        $this->db->query("UPDATE users SET avatar=? WHERE id=?", ['/uploads/avatars/' . $filename, $user['id']]);
        Auth::clearCache();

        $this->withFlash('success', 'Avatar updated.')->redirect('/super-admin/profile');
    }

    public function enable2FA(Request $request): never
    {
        $user   = $this->currentUser();
        $secret = base64_encode(random_bytes(20));

        $this->db->query("UPDATE users SET two_factor_secret=? WHERE id=?", [$secret, $user['id']]);
        AuditLogger::log('2fa_enabled', 'profile', (string)$user['id']);

        $this->success(['secret' => $secret], '2FA enabled.');
    }

    public function disable2FA(Request $request): never
    {
        $user = $this->currentUser();
        $this->db->query("UPDATE users SET two_factor_secret=NULL, two_factor_enabled=0 WHERE id=?", [$user['id']]);
        AuditLogger::log('2fa_disabled', 'profile', (string)$user['id']);
        $this->success(null, '2FA disabled.');
    }
}
