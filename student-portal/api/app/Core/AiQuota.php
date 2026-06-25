<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Shared AI quota check-and-reserve — docs/student-module/05a §4. Originally
 * lived only inside AiController, but 05a is explicit that *every* feature
 * calling the Gateway (notebook summarize/flashcards/quiz-generation,
 * recording→notes, not just the Doubt Solver chat) goes through the same
 * quota gate — having it live in one controller meant every other AI-
 * calling endpoint in this build was unmetered. One implementation now,
 * used everywhere `AiGateway::complete()` is called from student-facing
 * code (the originality-check and parent-report crons are *not* student-
 * facing requests and aren't gated by a per-student quota the same way).
 */
class AiQuota
{
    /**
     * Atomic check-and-reserve, not check-then-call — a burst of near-
     * simultaneous requests must not all pass a quota check against the
     * same stale "messages_used" read (05a's explicit reasoning). The
     * affected-row-count from this single UPDATE is the actual gate.
     */
    public static function tryReserve(Database $db, int $studentId): bool
    {
        $limit = (require BASE_PATH . '/config/ai.php')['daily_message_limit'];
        $periodStart = date('Y-m-d');

        $db->execute(
            "INSERT INTO ai_usage_quotas (student_id, period, period_start, messages_used, quota_limit_messages)
             VALUES (?, 'daily', ?, 0, ?)
             ON DUPLICATE KEY UPDATE quota_limit_messages = quota_limit_messages",
            [$studentId, $periodStart, $limit]
        );

        $stmt = $db->query(
            "UPDATE ai_usage_quotas SET messages_used = messages_used + 1
             WHERE student_id = ? AND period = 'daily' AND period_start = ? AND messages_used < quota_limit_messages",
            [$studentId, $periodStart]
        );

        return $stmt->rowCount() > 0;
    }

    /** Token/cost accounting is necessarily after-the-fact — real usage isn't known until the provider response exists (05a's explicit point). */
    public static function finalize(Database $db, int $studentId, int $totalTokens, float $costUsd): void
    {
        $db->execute(
            "UPDATE ai_usage_quotas SET tokens_used = tokens_used + ?, cost_usd_used = cost_usd_used + ?
             WHERE student_id = ? AND period = 'daily' AND period_start = ?",
            [$totalTokens, $costUsd, $studentId, date('Y-m-d')]
        );
    }

    public static function currentRow(Database $db, int $studentId): array|false
    {
        return $db->fetchOne(
            "SELECT * FROM ai_usage_quotas WHERE student_id = ? AND period = 'daily' AND period_start = ?",
            [$studentId, date('Y-m-d')]
        );
    }

    public static function resetsAt(): string
    {
        return date('Y-m-d 00:00:00', strtotime('+1 day'));
    }
}
