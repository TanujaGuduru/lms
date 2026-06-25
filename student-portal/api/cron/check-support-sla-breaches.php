<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run every 30 minutes:
 *   php /home/yourusername/public_html/api/cron/check-support-sla-breaches.php
 *
 * docs/student-module/08-infrastructure-devops.md §3: support-ticket SLA
 * breaches were "already specified at the application level as 'alerts a
 * supervisor'" (03h/04g) but nothing in this codebase ever actually
 * computed a breach or sent that alert — `support_categories.sla_hours`
 * existed and was readable, but nothing read it against a ticket's age. A
 * breach here means: no first response yet, and the category's SLA window
 * (measured from ticket creation) has already passed, and the ticket isn't
 * already resolved/closed.
 *
 * "Alerts a supervisor" means the assigned staff member if the ticket has
 * one, otherwise every active SuperAdmin — the same escalation pattern as
 * cron/reconcile-credit-ledger.php, since this build has no separate
 * "supervisor" role/concept to target more specifically.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Notifier;

$db = Database::getInstance();

$breaches = $db->select(
    "SELECT t.id, t.ticket_number, t.subject, t.priority, t.assigned_to, t.created_at,
            TIMESTAMPDIFF(HOUR, t.created_at, NOW()) AS hours_open
     FROM support_tickets t
     LEFT JOIN support_categories c ON c.id = t.category_id
     WHERE t.first_response_at IS NULL
       AND t.status NOT IN ('resolved', 'closed')
       AND TIMESTAMPDIFF(HOUR, t.created_at, NOW()) > COALESCE(c.sla_hours, 24)"
);

foreach ($breaches as $ticket) {
    alertForBreach($db, $ticket);
}

$db->execute(
    'INSERT INTO system_health_checks (check_name, last_run_at, status, details) VALUES (?, NOW(), ?, ?)
     ON DUPLICATE KEY UPDATE last_run_at = NOW(), status = VALUES(status), details = VALUES(details)',
    [
        'support_sla_breaches',
        empty($breaches) ? 'ok' : 'warning',
        json_encode(['breach_count' => count($breaches)]),
    ]
);

echo count($breaches) . " support ticket(s) currently in SLA breach.\n";

function alertForBreach(Database $db, array $ticket): void
{
    $summary = "Ticket #{$ticket['ticket_number']} (\"{$ticket['subject']}\", priority: {$ticket['priority']}) "
        . "has had no first response in {$ticket['hours_open']} hours, past its SLA window.";

    $recipients = $ticket['assigned_to']
        ? [['id' => $ticket['assigned_to']]]
        : $db->select("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.hierarchy_level = 1 AND u.status = 'active'");

    // Disambiguated by ticket id + today's date — re-alerts once per day
    // while a ticket stays breached, rather than going silent after the
    // first alert or re-alerting on every 30-minute tick.
    foreach ($recipients as $recipient) {
        Notifier::send((int) $recipient['id'], "support_sla_breach:{$ticket['id']}-" . date('Y-m-d'), ['breach_summary' => $summary]);
    }
}
