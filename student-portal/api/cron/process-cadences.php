<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run every 15 minutes:
 *   php /home/yourusername/public_html/api/cron/process-cadences.php
 *
 * Generalized cadence evaluator — docs/student-module/06-communication-engine.md
 * §6. Evaluates every registered cadence in config/cadences.php each tick,
 * scanning for rows whose anchor-plus-offset has crossed `now`, and calling
 * App\Core\Notifier::send() for each one. Deliberately does NOT track "just
 * crossed vs. crossed a while ago" with a tight time window — it simply
 * checks `now() >= anchor + offset` every tick and relies entirely on
 * Notifier's own idempotency check (06 §2) to make repeated, at-least-once
 * cron execution safe; this also means a cron outage of any length is
 * caught up on the next run rather than silently missing a window.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Notifier;

$db = Database::getInstance();
$cadences = require __DIR__ . '/../config/cadences.php';

$sent = 0;
$checked = 0;

foreach ($cadences as $cadenceName => $cadence) {
    $rows = $db->select($cadence['anchor_query']);

    foreach ($rows as $row) {
        if (! $row['anchor_at']) {
            continue;
        }
        $anchorTimestamp = strtotime($row['anchor_at']);

        foreach ($cadence['offsets'] as [$offsetSeconds, $suffix]) {
            $checked++;
            if (time() < $anchorTimestamp + $offsetSeconds) {
                continue; // this offset hasn't crossed yet for this occurrence.
            }

            $triggerEvent = "{$cadence['trigger_event_prefix']}_{$suffix}:{$row['entity_id']}";
            $context = array_intersect_key($row, array_flip($cadence['context_columns']));

            Notifier::send((int) $row['recipient_id'], $triggerEvent, $context);
            $sent++;
        }
    }
}

echo "{$checked} cadence offset(s) checked across " . count($cadences) . " cadence definition(s); {$sent} Notifier::send() call(s) made (idempotency may have no-op'd some).\n";
