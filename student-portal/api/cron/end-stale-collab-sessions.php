<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run every 5 minutes:
 *   php /home/yourusername/public_html/api/cron/end-stale-collab-sessions.php
 *
 * docs/student-module/02d's "5-minutes-no-presence auto-end" rule was
 * designed to fire from a server-side listener on Pusher/Ably presence
 * events. With no such service anywhere in this build, this periodic
 * sweep is the honest equivalent: any active session where every
 * participant has left (or never came back) more than 5 minutes ago gets
 * auto-ended, same as CollabSessionController::end() would do explicitly.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;

$db = Database::getInstance();

$staleSessions = $db->select(
    "SELECT cs.id FROM collab_sessions cs
     WHERE cs.status = 'active'
       AND NOT EXISTS (SELECT 1 FROM collab_participants cp WHERE cp.collab_session_id = cs.id AND cp.left_at IS NULL)
       AND (SELECT MAX(COALESCE(cp2.left_at, cp2.joined_at)) FROM collab_participants cp2 WHERE cp2.collab_session_id = cs.id)
           <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
);

foreach ($staleSessions as $session) {
    $db->updateTable('collab_sessions', ['status' => 'ended', 'ended_at' => date('Y-m-d H:i:s')], 'id = ?', [$session['id']]);
}

echo count($staleSessions) . " stale collab session(s) auto-ended.\n";
