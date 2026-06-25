<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class SecurityController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('security.view');
        $this->render('super-admin.security.index', ['title' => 'Security Center']);
    }

    public function sessions(Request $request): void
    {
        $this->authorize('security.view');

        $page    = max(1, (int)$request->input('page', 1));
        $result  = $this->db->paginate(
            "SELECT us.*, CONCAT(u.first_name,' ',u.last_name) user_name, u.email, u.avatar
             FROM user_sessions us JOIN users u ON u.id=us.user_id
             WHERE us.is_active=1 ORDER BY us.last_activity DESC",
            [], $page, 25
        );

        $this->render('super-admin.security.sessions', [
            'title'    => 'Active Sessions',
            'sessions' => $result['data'],
            'meta'     => $result,
        ]);
    }

    public function revokeSession(Request $request, string $token): never
    {
        $this->authorize('security.update');

        $this->db->query("UPDATE user_sessions SET is_active=0 WHERE session_token=?", [$token]);
        AuditLogger::log('session_revoked', 'security', null, null, ['token' => substr($token, 0, 8) . '…']);

        $this->success(null, 'Session revoked.');
    }

    public function revokeAllSessions(Request $request): never
    {
        $this->authorize('security.update');

        $userId = (int)$request->input('user_id', 0);
        $current = $this->currentUser()['id'];

        if ($userId) {
            $this->db->query("UPDATE user_sessions SET is_active=0 WHERE user_id=? AND user_id != ?", [$userId, $current]);
        } else {
            $this->db->query("UPDATE user_sessions SET is_active=0 WHERE user_id != ?", [$current]);
        }

        AuditLogger::log('all_sessions_revoked', 'security', null, null, ['user_id' => $userId ?: 'all']);
        $this->success(null, 'Sessions revoked.');
    }

    public function ipRestrictions(Request $request): void
    {
        $this->authorize('security.view');

        $ips = $this->db->select("SELECT * FROM ip_restrictions ORDER BY created_at DESC");
        $this->render('super-admin.security.ip-restrictions', [
            'title' => 'IP Restrictions',
            'ips'   => $ips,
        ]);
    }

    public function addIpRule(Request $request): void
    {
        $this->authorize('security.update');

        $data = $this->validate($request, [
            'ip_address' => 'required|max:45',
            'type'       => 'required|in:whitelist,blacklist',
            'reason'     => 'max:255',
        ]);

        // Basic IP / CIDR validation
        if (!filter_var($data['ip_address'], FILTER_VALIDATE_IP) &&
            !preg_match('/^[\d.:\/]+$/', $data['ip_address'])) {
            $this->withFlash('error', 'Invalid IP address format.')->back();
        }

        $this->db->insert(
            "INSERT INTO ip_restrictions (ip_address,type,reason,is_active,created_by,created_at)
             VALUES (?,?,?,1,?,NOW())",
            [$data['ip_address'], $data['type'], $data['reason'] ?? '', $this->currentUser()['id']]
        );

        AuditLogger::log('ip_rule_added', 'security', null, null, $data);
        $this->withFlash('success', "IP rule added for {$data['ip_address']}.")->redirect('/super-admin/security/ip-restrictions');
    }

    public function removeIpRule(Request $request, int $id): void
    {
        $this->authorize('security.update');

        $rule = $this->db->selectOne("SELECT * FROM ip_restrictions WHERE id = ?", [$id]);
        if (!$rule) $this->withFlash('error', 'Rule not found.')->back();

        $this->db->query("DELETE FROM ip_restrictions WHERE id = ?", [$id]);
        AuditLogger::log('ip_rule_removed', 'security', (string)$id, $rule);

        if ((new Request())->isAjax()) $this->success(null, 'IP rule removed.');
        $this->withFlash('success', 'IP rule removed.')->redirect('/super-admin/security/ip-restrictions');
    }

    public function unlockAccount(Request $request, int $userId): never
    {
        $this->authorize('security.update');

        $this->db->query("UPDATE users SET locked_until=NULL, failed_login_attempts=0 WHERE id=?", [$userId]);
        AuditLogger::log('account_unlocked', 'security', (string)$userId);

        $this->success(null, 'Account unlocked.');
    }

    public function loginLogs(Request $request): void
    {
        $this->authorize('security.view');

        $page   = max(1, (int)$request->input('page', 1));
        $action = $request->input('filter', '');
        $where  = $action ? "WHERE al.action = '{$action}'" : "WHERE al.action IN ('login','login_failed')";

        $result = $this->db->paginate(
            "SELECT al.*, CONCAT(u.first_name,' ',u.last_name) user_name, u.avatar
             FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id
             {$where} ORDER BY al.created_at DESC",
            [], $page, 30
        );

        $this->render('super-admin.security.login-logs', [
            'title'  => 'Login Logs',
            'logs'   => $result['data'],
            'meta'   => $result,
            'filter' => $action,
        ]);
    }
}
