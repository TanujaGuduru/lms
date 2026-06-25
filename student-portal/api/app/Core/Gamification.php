<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Shared XP/streak/badge logic — docs/student-module/04h. "There is no POST
 * endpoint that awards XP, badges, or extends a streak anywhere in this
 * catalog — deliberately. Every award is a server-side side effect of an
 * event that already has its own endpoint elsewhere" (the doc's own words).
 * This class is what those existing endpoints call into:
 * ClassroomController::leave() for `class_attended`, and
 * AssignmentController::submit() for `assignment_submitted`/`project_completed`.
 *
 * Two things the doc leaves unspecified, resolved with a documented,
 * reasonable default rather than guessed silently:
 *  - **Leveling formula**: `floor(sqrt(total_xp / 100)) + 1` — a standard,
 *    increasing-cost RPG curve (100 XP to reach level 2, 400 for level 3,
 *    900 for level 4, ...). Easy to swap for a real design later; nothing
 *    elsewhere depends on the specific curve.
 *  - **Referral-conversion XP** (`xp_transactions.reason = 'referral'`) is
 *    NOT wired here — 04g's own doc already flags that nothing in this
 *    codebase yet flips `referrals.status` to `converted` (a backend job
 *    "watching for a matching new enrollment" that was never built), so
 *    there's no real event to hook this to yet. Not silently skipped:
 *    documented as the same pre-existing gap, not a new one.
 */
class Gamification
{
    public static function awardXp(Database $db, int $studentId, int $amount, string $reason, ?string $sourceType = null, ?int $sourceId = null): void
    {
        if ($sourceType && $sourceId) {
            $already = $db->fetchOne(
                'SELECT 1 FROM xp_transactions WHERE student_id = ? AND source_type = ? AND source_id = ? AND reason = ?',
                [$studentId, $sourceType, $sourceId, $reason]
            );
            if ($already) {
                return; // same event can't award XP twice (e.g. a retried submit/leave call).
            }
        }

        $db->insertInto('xp_transactions', [
            'student_id' => $studentId,
            'amount' => $amount,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);

        $db->execute(
            "INSERT INTO student_xp (student_id, total_xp) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE total_xp = total_xp + ?",
            [$studentId, max($amount, 0), $amount]
        );

        $totalXp = (int) $db->fetchOne('SELECT total_xp FROM student_xp WHERE student_id = ?', [$studentId])['total_xp'];
        $newLevel = (int) floor(sqrt(max($totalXp, 0) / 100)) + 1;
        $db->execute('UPDATE student_xp SET current_level = ? WHERE student_id = ?', [$newLevel, $studentId]);

        self::touchStreak($db, $studentId);
        self::checkAndAwardBadges($db, $studentId, $totalXp);
    }

    private static function touchStreak(Database $db, int $studentId): void
    {
        $today = date('Y-m-d');
        $streak = $db->fetchOne('SELECT * FROM student_streaks WHERE student_id = ?', [$studentId]);

        if (! $streak) {
            $db->insertInto('student_streaks', [
                'student_id' => $studentId,
                'current_streak_days' => 1,
                'longest_streak_days' => 1,
                'last_activity_date' => $today,
            ]);
            return;
        }

        if ($streak['last_activity_date'] === $today) {
            return; // already counted today — multiple events in one day don't inflate the streak.
        }

        $isConsecutive = $streak['last_activity_date'] === date('Y-m-d', strtotime('-1 day'));
        $newStreak = $isConsecutive ? (int) $streak['current_streak_days'] + 1 : 1;

        $db->updateTable('student_streaks', [
            'current_streak_days' => $newStreak,
            'longest_streak_days' => max($newStreak, (int) $streak['longest_streak_days']),
            'last_activity_date' => $today,
        ], 'student_id = ?', [$studentId]);
    }

    /** `custom` criteria are never auto-evaluated here — bespoke logic per badge stays a human/admin decision, not guessed generically. */
    private static function checkAndAwardBadges(Database $db, int $studentId, int $totalXp): void
    {
        $candidates = $db->select(
            "SELECT b.* FROM badges b
             WHERE b.is_active = 1 AND b.criteria_type != 'custom'
               AND b.id NOT IN (SELECT badge_id FROM student_badges WHERE student_id = ?)",
            [$studentId]
        );

        foreach ($candidates as $badge) {
            if (self::meetsCriteria($db, $studentId, $badge, $totalXp)) {
                $stmt = $db->execute(
                    'INSERT IGNORE INTO student_badges (student_id, badge_id) VALUES (?, ?)',
                    [$studentId, $badge['id']]
                );
                // INSERT IGNORE no-ops silently on a duplicate (already
                // earned) — only notify on a genuinely new row, matching
                // 03i's "a badge that takes a day to show up loses most of
                // its motivational value" reasoning (06 §1): fire in this
                // same request, not deferred to a later scan.
                if ($stmt->rowCount() > 0) {
                    Notifier::send($studentId, "badge_earned:{$badge['id']}", ['badge_name' => $badge['name']]);
                }
            }
        }
    }

    private static function meetsCriteria(Database $db, int $studentId, array $badge, int $totalXp): bool
    {
        $value = (int) $badge['criteria_value'];

        return match ($badge['criteria_type']) {
            'xp_threshold' => $totalXp >= $value,
            'streak' => (int) ($db->fetchOne('SELECT current_streak_days FROM student_streaks WHERE student_id = ?', [$studentId])['current_streak_days'] ?? 0) >= $value,
            'course_completion' => (int) $db->fetchOne("SELECT COUNT(*) AS c FROM enrollments WHERE user_id = ? AND status = 'completed'", [$studentId])['c'] >= $value,
            'project_count' => (int) $db->fetchOne(
                "SELECT COUNT(*) AS c FROM assignment_submissions asub JOIN assignments a ON a.id = asub.assignment_id
                 WHERE asub.student_id = ? AND a.type = 'project' AND asub.status = 'graded'",
                [$studentId]
            )['c'] >= $value,
            default => false,
        };
    }
}
