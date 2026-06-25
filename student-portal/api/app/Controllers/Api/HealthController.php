<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * docs/student-module/08-infrastructure-devops.md §3's GoDaddy-specific
 * constraint: "no CloudWatch-equivalent deep infrastructure visibility
 * exists underneath a shared-hosting account — monitoring leans more
 * heavily on application-level /health endpoints polled by an external
 * uptime service (UptimeRobot/Pingdom)." `index()` is the fast liveness
 * ping such a service polls every minute or so — kept cheap (one `SELECT
 * 1`) on purpose. `metrics()` is the slower, business-specific signal
 * check 08 §3 actually calls for, meant to be polled far less often (a
 * few times an hour is plenty) since it reads several tables.
 */
class HealthController extends Controller
{
    public function index(Request $request): void
    {
        try {
            $this->db->fetchOne('SELECT 1');
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        $this->success(['status' => $dbOk ? 'ok' : 'degraded', 'database' => $dbOk]);
    }

    /**
     * Business-specific alerts (08 §3), as real numbers rather than a
     * generic infra dashboard: every one of these is also independently
     * logged/alerted by its own producer (cron/reconcile-credit-ledger.php,
     * cron/check-support-sla-breaches.php, App\Core\Notifier's own
     * circuit breaker) — this endpoint exists so an external uptime
     * service has one stable URL to poll and alert on, not to be the
     * sole place these signals are visible.
     *
     * `ai_fallback_rate` doesn't apply to this build the way 08 §3
     * describes it ("AI Gateway fallback-to-secondary-provider rate") —
     * there is no secondary provider here, by design (App\Core\AiGateway's
     * own docblock: "no second provider/Bedrock route in this build to
     * fail over to, only the one accepted API"). The honest equivalent
     * leading indicator this build actually has is how often that one
     * route needed its single transient-failure retry — counted directly
     * from today's log file, since AiGateway doesn't persist a queryable
     * retry flag anywhere (a real, stated limitation, not a silently
     * invented number).
     */
    public function metrics(Request $request): void
    {
        $checks = $this->db->select('SELECT check_name, last_run_at, status, details FROM system_health_checks');
        $healthChecks = [];
        foreach ($checks as $check) {
            $healthChecks[$check['check_name']] = [
                'status' => $check['status'],
                'last_run_at' => $check['last_run_at'],
                'details' => $check['details'] ? json_decode($check['details'], true) : null,
            ];
        }

        $queueBacklog = $this->db->count('notification_queue', "status = 'pending'");
        $aiSpendToday = (float) ($this->db->fetchOne(
            "SELECT (SELECT COALESCE(SUM(cost_usd), 0) FROM ai_messages WHERE DATE(created_at) = CURDATE())
                   + (SELECT COALESCE(SUM(cost_usd), 0) FROM ai_usage_log WHERE DATE(created_at) = CURDATE()) AS total"
        )['total'] ?? 0);

        $this->success([
            'communication_queue_backlog' => $queueBacklog,
            'ai_spend_today_usd' => round($aiSpendToday, 4),
            'ai_gateway_retries_today' => $this->countTodaysRetries(),
            'health_checks' => $healthChecks,
        ]);
    }

    private function countTodaysRetries(): int
    {
        $logFile = BASE_PATH . '/storage/logs/' . date('Y-m-d') . '.log';
        if (! is_file($logFile)) {
            return 0;
        }
        $contents = file_get_contents($logFile);
        return substr_count($contents, 'AI Gateway attempt failed, retrying once');
    }
}
