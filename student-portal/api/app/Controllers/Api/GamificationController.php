<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Gamification — docs/student-module/04h-apis-notebook-collab.md. No
 * POST endpoint awards anything here, by design — see App\Core\Gamification's
 * docblock for where XP/streaks/badges actually get awarded (hooked into
 * existing event endpoints elsewhere, never a separate client call).
 */
class GamificationController extends Controller
{
    public function profile(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $xp = $this->db->fetchOne('SELECT total_xp, current_level FROM student_xp WHERE student_id = ?', [$studentId]);
        $streak = $this->db->fetchOne('SELECT current_streak_days, longest_streak_days, last_activity_date FROM student_streaks WHERE student_id = ?', [$studentId]);

        $this->success([
            'total_xp' => (int) ($xp['total_xp'] ?? 0),
            'current_level' => (int) ($xp['current_level'] ?? 1),
            'current_streak_days' => (int) ($streak['current_streak_days'] ?? 0),
            'longest_streak_days' => (int) ($streak['longest_streak_days'] ?? 0),
            'last_activity_date' => $streak['last_activity_date'] ?? null,
        ]);
    }

    public function xpHistory(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $result = $this->db->paginate(
            'SELECT amount, reason, source_type, source_id, created_at FROM xp_transactions WHERE student_id = ? ORDER BY created_at DESC',
            [$studentId], $page, $perPage
        );

        $this->success($result['data'], [
            'current_page' => $result['current_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'last_page' => $result['last_page'],
        ]);
    }

    /** A badge already earned stays earned even if criteria_value is raised later — this never re-evaluates past awards (04h's explicit no-retroactive-revocation rule). */
    public function badges(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            "SELECT b.id, b.name, b.description, b.icon_url, sb.earned_at
             FROM badges b LEFT JOIN student_badges sb ON sb.badge_id = b.id AND sb.student_id = ?
             WHERE b.is_active = 1 ORDER BY b.id",
            [$studentId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'description' => $r['description'],
            'icon_url' => $r['icon_url'],
            'earned_at' => $r['earned_at'],
            'locked' => $r['earned_at'] === null,
        ], $rows));
    }

    /**
     * Cached server-side for a short window — a plain indexed query, not a
     * correctness concern, purely avoiding identical re-queries across many
     * dashboard loads in the same few minutes (04h's explicit reasoning,
     * and the same GoDaddy-driven "no Redis" fallback already established
     * elsewhere in this build — a flat JSON file on local disk instead).
     */
    public function leaderboard(Request $request): void
    {
        $scope = $request->input('scope', 'global') === 'course' ? 'course' : 'global';
        $courseId = (int) $request->input('course_id', 0);

        if ($scope === 'course' && ! $courseId) {
            $this->fail('course_id is required for scope=course.', ['course_id' => ['required']]);
        }

        $cacheKey = $scope === 'course' ? "leaderboard_course_{$courseId}" : 'leaderboard_global';
        $cached = $this->readCache($cacheKey);
        if ($cached !== null) {
            $this->success($cached);
        }

        if ($scope === 'course') {
            $rows = $this->db->select(
                "SELECT sx.student_id, u.first_name, u.last_name, sx.total_xp, sx.current_level
                 FROM student_xp sx JOIN users u ON u.id = sx.student_id
                 JOIN enrollments e ON e.user_id = sx.student_id AND e.course_id = ?
                 ORDER BY sx.total_xp DESC LIMIT 100",
                [$courseId]
            );
        } else {
            $rows = $this->db->select(
                "SELECT sx.student_id, u.first_name, u.last_name, sx.total_xp, sx.current_level
                 FROM student_xp sx JOIN users u ON u.id = sx.student_id
                 ORDER BY sx.total_xp DESC LIMIT 100"
            );
        }

        $data = array_map(fn (array $r, int $i) => [
            'rank' => $i + 1,
            'student_id' => (int) $r['student_id'],
            'first_name' => $r['first_name'],
            'last_name' => $r['last_name'],
            'total_xp' => (int) $r['total_xp'],
            'current_level' => (int) $r['current_level'],
        ], $rows, array_keys($rows));

        $this->writeCache($cacheKey, $data);
        $this->success($data);
    }

    private function cachePath(string $key): string
    {
        return BASE_PATH . "/storage/cache/{$key}.json";
    }

    private function readCache(string $key): ?array
    {
        $path = $this->cachePath($key);
        if (! file_exists($path) || (time() - filemtime($path)) > 300) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(string $key, array $data): void
    {
        $path = $this->cachePath($key);
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($data));
    }
}
