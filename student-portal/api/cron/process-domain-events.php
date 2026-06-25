<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run every 5 minutes:
 *   php /home/yourusername/public_html/api/cron/process-domain-events.php
 *
 * The Student app's half of the `domain_events` outbox — docs/student-module/06
 * §7. Polls `WHERE processed_by_student_app = 0`, applies each event's
 * effect, then flips only `processed_by_student_app` — never
 * `processed_by_admin`, so the Admin panel's own (separate, pre-existing
 * PHP codebase, not touched by this pass) consumer stays fully independent.
 *
 * Only `teacher.reassigned` is implemented — 06 §7's own worked example for
 * this exact mechanism. Other event types log a warning and are left
 * unprocessed (flag not flipped) rather than silently dropped, so a new
 * handler can be added later without losing events that arrived before it
 * existed. There is also no Admin-side code yet that *writes* a
 * `teacher.reassigned` row (that would be a change to the separate,
 * pre-existing Admin panel codebase, out of this pass's scope) — tested
 * here by inserting a row directly to simulate an Admin-originated event,
 * which is the correct way to test one half of an outbox integration point
 * without needing the other side's producer to exist yet.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Logger;
use App\Core\Notifier;

const BATCH_SIZE = 20;

$db = Database::getInstance();

$events = $db->select(
    'SELECT * FROM domain_events WHERE processed_by_student_app = 0 ORDER BY occurred_at LIMIT ' . BATCH_SIZE
);

$applied = 0;
foreach ($events as $event) {
    try {
        applyEvent($db, $event);
        $db->updateTable('domain_events', ['processed_by_student_app' => 1], 'id = ?', [$event['id']]);
        $applied++;
    } catch (\Throwable $e) {
        // Doesn't flip the flag — safe to retry next poll (06 §7's explicit
        // failure-handling rule), as long as applyEvent() stays idempotent
        // against being handed the same event twice. It is here: the only
        // effect is Notifier::send() calls, and Notifier's own idempotency
        // check (06 §2) makes a safe re-apply automatic.
        Logger::error('domain_events apply failed', ['event_id' => $event['id'], 'error' => $e->getMessage()]);
    }
}

echo "{$applied} of " . count($events) . " domain_events applied.\n";

function applyEvent(Database $db, array $event): void
{
    $payload = json_decode($event['payload'], true) ?: [];

    match ($event['event_type']) {
        'teacher.reassigned' => applyTeacherReassigned($db, (int) $event['aggregate_id'], $payload),
        // Throwing (rather than just logging) is deliberate — it's what
        // keeps applyEvent()'s caller from flipping processed_by_student_app
        // on an event type nothing actually handled yet.
        default => throw new \RuntimeException("Unhandled domain_event type: {$event['event_type']}"),
    };
}

function applyTeacherReassigned(Database $db, int $batchId, array $payload): void
{
    $newTeacherName = $payload['new_teacher_name'] ?? 'a new teacher';
    $students = $db->select('SELECT student_id FROM batch_students WHERE batch_id = ?', [$batchId]);

    foreach ($students as $row) {
        Notifier::send(
            (int) $row['student_id'],
            "batch_reassignment_notice:{$batchId}",
            ['new_teacher_name' => $newTeacherName]
        );
    }
}
