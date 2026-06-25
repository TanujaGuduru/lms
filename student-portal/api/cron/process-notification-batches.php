<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run every 30 minutes:
 *   php /home/yourusername/public_html/api/cron/process-notification-batches.php
 *
 * Drains `notification_queue` — docs/student-module/06-communication-engine.md
 * §4. App\Core\Notifier::send() writes a row here instead of sending
 * immediately for any trigger_event configured `batchable => true`
 * (config/notification_channels.php). Each run groups all still-pending
 * rows by `batch_key` when set (the family-level case §4 explicitly
 * separates from per-student batching — e.g. multiple children's
 * monthly_parent_report_ready rows sharing one parent's batch_key), or by
 * `user_id` otherwise, and combines each group into one consolidated send
 * via App\Core\Notifier::dispatchBatch() rather than firing each
 * independently. The "short window (a few hours)" 06 §4 describes is, in
 * this GoDaddy cron-tick reality (06 §1), simply "whatever's accumulated
 * since the last run" — there's no persistent queue worker to hold a row
 * open waiting for siblings that might arrive later.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Notifier;

$db = Database::getInstance();

$pending = $db->select("SELECT * FROM notification_queue WHERE status = 'pending' ORDER BY user_id, batch_key, id");

$groups = [];
foreach ($pending as $row) {
    $groupKey = $row['batch_key'] ?: "user:{$row['user_id']}";
    $groups[$groupKey]['user_id'] = (int) $row['user_id'];
    $groups[$groupKey]['rows'][] = $row;
}

$batchesSent = 0;
foreach ($groups as $group) {
    Notifier::dispatchBatch($group['user_id'], $group['rows']);
    $ids = array_column($group['rows'], 'id');
    $db->execute(
        'UPDATE notification_queue SET status = ?, sent_at = NOW() WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        array_merge(['sent'], $ids)
    );
    $batchesSent++;
}

echo count($pending) . " queued notification(s) drained into {$batchesSent} combined send(s).\n";
