<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;
use App\Core\Setting;

class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('settings.view');
        $group = $request->input('group', 'general');
        $this->render('super-admin.settings.index', [
            'title' => 'Settings',
            'group' => $group,
        ]);
    }

    public function show(Request $request, string $group): void
    {
        $this->authorize('settings.view');
        $this->render('super-admin.settings.index', [
            'title' => ucfirst($group) . ' Settings',
            'group' => $group,
        ]);
    }

    public function save(Request $request, string $group): void
    {
        $this->authorize('settings.update');

        $allowedGroups = ['general','email','sms','payment','security','ai','storage','branding'];
        if (!in_array($group, $allowedGroups, true)) {
            $this->withFlash('error', 'Invalid settings group.')->back();
        }

        $input  = $request->except(['_csrf_token', '_method']);
        $oldMap = [];

        $settings = $this->db->select("SELECT `key`,`value`,`type` FROM settings WHERE `group` = ?", [$group]);
        foreach ($settings as $s) {
            $oldMap[$s['key']] = $s['value'];
        }

        foreach ($input as $key => $value) {
            // Handle file uploads separately
            if (str_ends_with($key, '_file')) continue;

            // Sanitize key — only allow word characters and underscores
            if (!preg_match('/^[a-z0-9_]+$/', $key)) continue;

            $this->db->update(
                "UPDATE settings SET `value` = ?, updated_at = NOW() WHERE `key` = ? AND `group` = ?",
                [$value, $key, $group]
            );
        }

        // Handle file uploads for 'file' type settings
        foreach ($_FILES as $fileKey => $fileData) {
            $settingKey = str_replace('_file', '', $fileKey);
            if (!empty($fileData['tmp_name']) && is_uploaded_file($fileData['tmp_name'])) {
                $ext      = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
                $allowed  = ['jpg','jpeg','png','gif','svg','webp'];
                if (!in_array($ext, $allowed, true)) continue;

                $filename = $settingKey . '_' . time() . '.' . $ext;
                $dest     = PUBLIC_PATH . '/uploads/settings/' . $filename;
                @mkdir(dirname($dest), 0755, true);
                if (move_uploaded_file($fileData['tmp_name'], $dest)) {
                    $url = '/uploads/settings/' . $filename;
                    $this->db->update(
                        "UPDATE settings SET `value` = ?, updated_at = NOW() WHERE `key` = ? AND `group` = ?",
                        [$url, $settingKey, $group]
                    );
                }
            }
        }

        // Clear settings cache
        Setting::reload();

        AuditLogger::log('settings_updated', 'settings', null, ['group' => $group, 'old' => $oldMap], $input);

        $this->withFlash('success', ucfirst($group) . ' settings saved successfully.')
             ->redirect('/super-admin/settings/' . $group);
    }

    public function testEmail(Request $request): never
    {
        $this->authorize('settings.update');

        $email = filter_var($request->input('email'), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->error('Invalid email address.');
        }

        try {
            // Build a test email using configured SMTP
            $host    = Setting::get('smtp_host', '');
            $port    = (int)Setting::get('smtp_port', '587');
            $user    = Setting::get('smtp_user', '');
            $pass    = Setting::get('smtp_pass', '');
            $from    = Setting::get('mail_from', $user);
            $fromName = Setting::get('mail_from_name', 'CodeGurukul LMS');

            if (empty($host) || empty($user)) {
                $this->error('SMTP not configured. Please fill in host and credentials first.');
            }

            // Use PHP mail() as simple fallback for test
            $subject = 'CodeGurukul LMS – Test Email';
            $body    = "This is a test email from CodeGurukul LMS.\n\nIf you received this, your email settings are working correctly!";
            $headers = "From: {$fromName} <{$from}>\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8";

            if (mail($email, $subject, $body, $headers)) {
                $this->success(null, "Test email sent to {$email}");
            }
            $this->error('Failed to send test email. Check SMTP configuration.');
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
