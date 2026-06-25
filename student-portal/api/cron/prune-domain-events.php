<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run once a day, e.g. 5:00 AM:
 *   php /home/yourusername/public_html/api/cron/prune-domain-events.php
 *
 * docs/student-module/07-scaling-strategy.md §5: "`domain_events` is the
 * one exception to 'never delete a ledger row' — once *both*
 * processed_by_admin and processed_by_student_app are true and a retention
 * window has passed, a row has done its entire job (handing an event from
 * one runtime to the other) and has no ongoing audit value the way a
 * financial or XP ledger does." Every other ledger in this build is
 * append-only forever (see cron/archive-ledger-content.php for how those
 * stay bounded instead — strip the body, never delete the row); this is
 * the deliberate, sole exception, not an oversight or a shortcut applied
 * to ledgers generally.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;

const RETENTION_DAYS = 30;

$db = Database::getInstance();
$cutoff = date('Y-m-d H:i:s', strtotime('-' . RETENTION_DAYS . ' days'));

$deleted = $db->delete(
    'domain_events',
    'processed_by_admin = 1 AND processed_by_student_app = 1 AND occurred_at < ?',
    [$cutoff]
);

echo "{$deleted} fully-processed domain_events row(s) older than " . RETENTION_DAYS . " days pruned.\n";
