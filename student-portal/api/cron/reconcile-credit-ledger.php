<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run nightly, e.g. 2:30 AM:
 *   php /home/yourusername/public_html/api/cron/reconcile-credit-ledger.php
 *
 * docs/student-module/08-infrastructure-devops.md §3: "Credit-ledger
 * reconciliation drift... any nonzero drift pages someone immediately,
 * since 02a already established that drift specifically means an
 * application bug exists, not background noise to dashboard and ignore."
 * No reconciliation job existed anywhere in this build before this pass —
 * `credit_wallets.credits_balance` is denormalized ("maintained by
 * trigger/app — never computed ad hoc in queries," per its own column
 * comment), and nothing had ever actually checked it against the
 * append-only `credit_transactions` ledger it's supposed to mirror.
 *
 * "Pages someone immediately" in this no-cloud build means a real email to
 * every active SuperAdmin (`roles.hierarchy_level = 1`) via
 * App\Core\Notifier — there's no PagerDuty/Opsgenie integration possible
 * here, but the underlying signal (an application bug exists right now)
 * genuinely needs a human notified, not just logged for someone to
 * eventually notice on a dashboard.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Logger;
use App\Core\Notifier;

$db = Database::getInstance();

$wallets = $db->select(
    "SELECT w.id, w.student_id, w.credits_balance,
            COALESCE((SELECT SUM(amount) FROM credit_transactions WHERE wallet_id = w.id), 0) AS ledger_sum
     FROM credit_wallets w"
);

$drifted = [];
foreach ($wallets as $wallet) {
    $expected = (int) $wallet['ledger_sum'];
    $actual = (int) $wallet['credits_balance'];
    if ($expected !== $actual) {
        $drifted[] = [
            'wallet_id' => (int) $wallet['id'],
            'student_id' => (int) $wallet['student_id'],
            'expected' => $expected,
            'actual' => $actual,
            'drift' => $actual - $expected,
        ];
    }
}

recordHealthCheck($db, $drifted, count($wallets));

if (! empty($drifted)) {
    Logger::critical('Credit ledger reconciliation drift detected', ['drifted_wallets' => $drifted]);
    alertSuperAdmins($db, $drifted);
}

echo count($wallets) . ' wallet(s) checked; ' . count($drifted) . " drifted.\n";

function recordHealthCheck(Database $db, array $drifted, int $checkedCount): void
{
    $db->execute(
        'INSERT INTO system_health_checks (check_name, last_run_at, status, details) VALUES (?, NOW(), ?, ?)
         ON DUPLICATE KEY UPDATE last_run_at = NOW(), status = VALUES(status), details = VALUES(details)',
        [
            'credit_ledger_reconciliation',
            empty($drifted) ? 'ok' : 'critical',
            json_encode(['checked' => $checkedCount, 'drifted' => count($drifted)]),
        ]
    );
}

function alertSuperAdmins(Database $db, array $drifted): void
{
    $superAdmins = $db->select(
        "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.hierarchy_level = 1 AND u.status = 'active'"
    );

    $first = $drifted[0];
    $summary = count($drifted) . ' wallet(s) drifted, e.g. wallet #' . $first['wallet_id']
        . ' expected ' . $first['expected'] . ' but stored ' . $first['actual'] . '.';

    // Disambiguated by date, not a fixed entity id — an unresolved drift
    // should keep paging daily until fixed, never go silent after one alert.
    foreach ($superAdmins as $admin) {
        Notifier::send((int) $admin['id'], 'credit_ledger_drift_detected:' . date('Y-m-d'), ['drift_summary' => $summary]);
    }
}
