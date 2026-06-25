<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Calendar Integration — docs/student-module/04i-apis-quizzes-replay-offline.md.
 * **`GET /calendar/connect/{provider}` and `POST /calendar/connections/{id}/reconnect`
 * are not implemented in this pass.** Both need a real OAuth2 app
 * registration with Google/Microsoft (and a CalDAV client for Apple) —
 * actual third-party developer credentials this build doesn't have, not
 * infrastructure to hand-roll away. Unlike the AI Gateway (a single
 * provider, a plain API key, no setup beyond pasting it into `.env`), three
 * separate OAuth integrations is a real, separate undertaking that needs
 * the user to first go register apps with three different providers —
 * deferred and documented rather than half-built with placeholder client
 * IDs that couldn't function anyway.
 *
 * What *is* real here: `providers()` reads actual `calendar_connections`
 * rows (whatever a real OAuth flow would eventually populate), and
 * `disconnect()` is a plain row deletion — neither needs live OAuth
 * credentials to be correct.
 */
class CalendarController extends Controller
{
    private const PROVIDERS = ['google', 'outlook', 'apple'];

    public function providers(Request $request): void
    {
        $userId = (int) $this->currentUser()['id'];

        $connections = $this->db->select(
            'SELECT provider, is_active FROM calendar_connections WHERE user_id = ?',
            [$userId]
        );
        $byProvider = [];
        foreach ($connections as $c) {
            $byProvider[$c['provider']] = $c;
        }

        $this->success(array_map(fn (string $provider) => [
            'provider' => $provider,
            'connected' => isset($byProvider[$provider]),
            'needs_reconnect' => isset($byProvider[$provider]) && ! $byProvider[$provider]['is_active'],
        ], self::PROVIDERS));
    }

    public function disconnect(Request $request, string $id): void
    {
        $userId = (int) $this->currentUser()['id'];
        $connection = $this->db->fetchOne('SELECT id FROM calendar_connections WHERE id = ? AND user_id = ?', [$id, $userId]);

        if (! $connection) {
            $this->fail('No such connection.', ['reason' => ['not_found']], 404);
        }

        $this->db->delete('calendar_connections', 'id = ?', [$id]);
        $this->success(true);
    }
}
