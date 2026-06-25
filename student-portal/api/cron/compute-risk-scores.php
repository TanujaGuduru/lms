<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run nightly, e.g. 1:00 AM:
 *   php /home/yourusername/public_html/api/cron/compute-risk-scores.php
 *
 * docs/student-module/05c §4 states a deliberate boundary worth restating
 * here in code, not just in the doc: **risk scores are computed by
 * deterministic, rule-based arithmetic — never an LLM.** A score that
 * triggers real interventions needs to be exactly reproducible and
 * auditable; an LLM's free-text judgment can't guarantee that the same
 * inputs always produce the same output the way this cron's plain math
 * does. This is also a genuine gap-fill: `risk_scores` existed in the
 * schema and `ParentController::riskSummary()` already reads from it, but
 * nothing populated it — this is the first real producer of that table.
 *
 * `intervention_status` here is a **computed escalation tier**, matching
 * exactly what `ParentController::riskSummary()` already checks for
 * ('mentor_call_scheduled'/'parent_escalated' = elevated, shown to a
 * parent) — it is NOT itself a literal record of what got delivered. That
 * distinction still holds now that the Communication Engine
 * (docs/student-module/06, App\Core\Notifier) is wired in below: a tier
 * crossing fires a real Notifier::send() call (`notifyForTier()`), but
 * `intervention_status` describes the escalation level reached, not the
 * delivery outcome — `communication_logs` (which Notifier writes) is the
 * actual record of whether a channel-specific message went out.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Notifier;

const RECENT_WINDOW_DAYS = 14;
const ASSIGNMENT_WINDOW_DAYS = 30;

$db = Database::getInstance();

$enrollments = $db->select(
    "SELECT e.id AS enrollment_id, e.user_id AS student_id, e.course_id, e.batch_id, u.last_login_at, u.first_name, u.last_name
     FROM enrollments e JOIN users u ON u.id = e.user_id
     WHERE e.status = 'active'"
);

$count = 0;
foreach ($enrollments as $enrollment) {
    computeAndStore($db, $enrollment);
    $count++;
}

echo "{$count} active enrollment(s) risk-scored.\n";

function computeAndStore(Database $db, array $enrollment): void
{
    $components = [];
    $factors = [];

    $attendance = attendanceDeclineComponent($db, (int) $enrollment['student_id'], $enrollment['batch_id']);
    if ($attendance !== null) {
        $components[] = $attendance['component'];
        $factors['recent_attendance_pct'] = $attendance['recent_pct'];
        $factors['lifetime_attendance_pct'] = $attendance['lifetime_pct'];
    }

    $completion = assignmentCompletionComponent($db, (int) $enrollment['student_id'], (int) $enrollment['course_id']);
    if ($completion !== null) {
        $components[] = $completion['component'];
        $factors['assignment_completion_rate_recent'] = $completion['rate'];
    }

    $loginDays = $enrollment['last_login_at'] ? (int) floor((time() - strtotime($enrollment['last_login_at'])) / 86400) : null;
    if ($loginDays !== null) {
        $components[] = min(100, $loginDays * 5);
        $factors['days_since_last_login'] = $loginDays;
    }

    if (empty($components)) {
        return; // no signal at all yet (e.g. a brand-new enrollment) — nothing to score.
    }

    $scoreValue = (int) round(array_sum($components) / count($components));
    $tier = escalationTier($scoreValue);

    $db->insertInto('risk_scores', [
        'student_id' => $enrollment['student_id'],
        'score_type' => 'dropout_risk',
        'score_value' => $scoreValue,
        'contributing_factors' => json_encode($factors),
        'intervention_status' => $tier,
    ]);

    notifyForTier($db, $enrollment, $tier);
}

/**
 * Re-notifying every single night this cron runs while a student stays at
 * the same tier would be spam; never re-notifying again after the first
 * crossing would leave a student who's *still* at risk weeks later
 * silent. Disambiguating by ISO year-week (rather than just enrollment_id)
 * is this pass's resolution — at most one notification per tier per week,
 * not a permanent one-time-ever cap nor a nightly nag. 06 doesn't pin this
 * down explicitly for risk tiers the way it does for e.g. assignment
 * reminders (naturally bounded by each offset only crossing once).
 */
function notifyForTier(Database $db, array $enrollment, string $tier): void
{
    $entityKey = $enrollment['enrollment_id'] . '-' . date('oW');
    $studentName = trim("{$enrollment['first_name']} {$enrollment['last_name']}");

    match ($tier) {
        'ai_nudge_sent' => Notifier::send((int) $enrollment['student_id'], "risk_tier_nudge:{$entityKey}"),
        'mentor_call_scheduled' => Notifier::send((int) $enrollment['student_id'], "risk_tier_mentor_call:{$entityKey}"),
        'parent_escalated' => notifyParentsOfEscalation($db, (int) $enrollment['student_id'], $studentName, $entityKey),
        default => null, // 'none' — no signal worth notifying about.
    };
}

function notifyParentsOfEscalation(Database $db, int $studentId, string $studentName, string $entityKey): void
{
    $parents = $db->select('SELECT parent_id FROM parent_student_links WHERE student_id = ?', [$studentId]);
    foreach ($parents as $parent) {
        Notifier::send((int) $parent['parent_id'], "risk_tier_parent_escalation:{$entityKey}", ['student_name' => $studentName]);
    }
}

/** This student's recent (last 14 days) attendance vs their own lifetime average — a real decline, not just a low baseline, is the actual risk signal. */
function attendanceDeclineComponent(Database $db, int $studentId, ?int $batchId): ?array
{
    if (! $batchId) {
        return null;
    }

    $lifetime = $db->fetchOne(
        "SELECT AVG(a.attendance_percent) AS v FROM attendance a JOIN live_classes lc ON lc.id = a.live_class_id WHERE lc.batch_id = ? AND a.student_id = ?",
        [$batchId, $studentId]
    )['v'];
    $recent = $db->fetchOne(
        "SELECT AVG(a.attendance_percent) AS v FROM attendance a JOIN live_classes lc ON lc.id = a.live_class_id
         WHERE lc.batch_id = ? AND a.student_id = ? AND a.session_date >= DATE_SUB(CURDATE(), INTERVAL " . RECENT_WINDOW_DAYS . " DAY)",
        [$batchId, $studentId]
    )['v'];

    if ($lifetime === null || $recent === null) {
        return null; // no attendance history yet for this batch.
    }

    $decline = max(0, (float) $lifetime - (float) $recent);
    return [
        'component' => min(100, $decline * 2),
        'recent_pct' => round((float) $recent, 1),
        'lifetime_pct' => round((float) $lifetime, 1),
    ];
}

/** Share of assignments due in the last 30 days that were actually submitted (on time or late — submitting late still means the student didn't disengage). */
function assignmentCompletionComponent(Database $db, int $studentId, int $courseId): ?array
{
    $totalDue = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM assignments
         WHERE course_id = ? AND status IN ('published', 'closed')
           AND due_date BETWEEN DATE_SUB(NOW(), INTERVAL " . ASSIGNMENT_WINDOW_DAYS . " DAY) AND NOW()",
        [$courseId]
    )['c'];

    if ($totalDue === 0) {
        return null; // nothing due recently — not a signal either way.
    }

    $completed = (int) $db->fetchOne(
        "SELECT COUNT(*) AS c FROM assignment_submissions asub JOIN assignments a ON a.id = asub.assignment_id
         WHERE asub.student_id = ? AND a.course_id = ? AND asub.status IN ('submitted', 'graded', 'resubmitted')
           AND a.due_date BETWEEN DATE_SUB(NOW(), INTERVAL " . ASSIGNMENT_WINDOW_DAYS . " DAY) AND NOW()",
        [$studentId, $courseId]
    )['c'];

    $rate = $completed / $totalDue;
    return [
        'component' => (1 - $rate) * 100,
        'rate' => round($rate, 2),
    ];
}

function escalationTier(int $scoreValue): string
{
    return match (true) {
        $scoreValue >= 90 => 'parent_escalated',
        $scoreValue >= 75 => 'mentor_call_scheduled',
        $scoreValue >= 50 => 'ai_nudge_sent',
        default => 'none',
    };
}
