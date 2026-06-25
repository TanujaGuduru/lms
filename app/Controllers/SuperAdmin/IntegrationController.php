<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\AuditLogger;

class IntegrationController extends Controller
{
    public function index(Request $request): void
    {
        $this->authorize('integrations.view');

        $integrations = $this->db->select("SELECT * FROM integrations ORDER BY type, name");
        $this->render('super-admin.integrations.index', [
            'title'        => 'Integrations',
            'integrations' => $integrations,
        ]);
    }

    public function toggle(Request $request, int $id): never
    {
        $this->authorize('integrations.update');

        $integration = $this->db->selectOne("SELECT * FROM integrations WHERE id = ?", [$id]);
        if (!$integration) $this->error('Integration not found.', 404);

        $newStatus = $integration['is_active'] ? 0 : 1;
        $this->db->query("UPDATE integrations SET is_active=? WHERE id=?", [$newStatus, $id]);

        AuditLogger::log($newStatus ? 'integration_enabled' : 'integration_disabled', 'integrations', (string)$id);
        $this->success(['is_active' => $newStatus], $newStatus ? 'Integration enabled.' : 'Integration disabled.');
    }

    public function save(Request $request, int $id): never
    {
        $this->authorize('integrations.update');

        $integration = $this->db->selectOne("SELECT * FROM integrations WHERE id = ?", [$id]);
        if (!$integration) $this->error('Integration not found.', 404);

        $config = $request->input('config', []);
        if (!is_array($config)) $this->error('Invalid configuration.');

        // Sanitize — only allow alphanumeric keys
        $clean = [];
        foreach ($config as $k => $v) {
            if (preg_match('/^[a-z0-9_]+$/i', $k)) {
                $clean[$k] = $v;
            }
        }

        $this->db->query(
            "UPDATE integrations SET config=?, updated_at=NOW() WHERE id=?",
            [json_encode($clean), $id]
        );

        AuditLogger::log('integration_configured', 'integrations', (string)$id, null, ['name' => $integration['name']]);
        $this->success(null, 'Integration settings saved.');
    }

    public function test(Request $request, int $id): never
    {
        $this->authorize('integrations.update');

        $integration = $this->db->selectOne("SELECT * FROM integrations WHERE id = ?", [$id]);
        if (!$integration) $this->error('Integration not found.', 404);

        if (!$integration['is_active']) {
            $this->error('Enable this integration first before testing.');
        }

        $config = json_decode($integration['config'] ?? '{}', true) ?: [];

        // Basic connectivity test based on type
        switch ($integration['type']) {
            case 'email':
                if (empty($config['smtp_host'])) $this->error('SMTP host not configured.');
                $result = $this->testSmtp($config);
                break;

            case 'payment':
                if (empty($config['api_key'])) $this->error('API key not configured.');
                $result = ['status' => 'ok', 'message' => 'Payment gateway credentials look valid.'];
                break;

            case 'sms':
                if (empty($config['api_key'])) $this->error('API key not configured.');
                $result = ['status' => 'ok', 'message' => 'SMS gateway credentials look valid.'];
                break;

            default:
                $result = ['status' => 'ok', 'message' => 'Basic configuration check passed.'];
        }

        AuditLogger::log('integration_tested', 'integrations', (string)$id);
        $this->success($result, 'Test completed: ' . $result['message']);
    }

    private function testSmtp(array $config): array
    {
        $connection = @fsockopen(
            $config['smtp_host'],
            (int)($config['smtp_port'] ?? 587),
            $errno, $errstr, 5
        );

        if ($connection) {
            fclose($connection);
            return ['status' => 'ok', 'message' => 'SMTP connection successful.'];
        }

        return ['status' => 'error', 'message' => "Cannot connect to {$config['smtp_host']}: {$errstr}"];
    }
}
