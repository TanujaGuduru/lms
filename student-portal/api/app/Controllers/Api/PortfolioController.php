<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Achievement Showcase Wall — docs/student-module/04j-apis-showcase-ptm.md.
 * No cloud adaptation needed — pure CRUD + live aggregation from tables
 * already built (`published_projects` 04e, `certificates` 04g,
 * `student_badges`/`student_xp` 04h). `student_portfolios.pending_public_request`
 * is a gap-fill column — see schema_student_portal.sql's comment.
 */
class PortfolioController extends Controller
{
    private const INAPPROPRIATE_SLUG_WORDS = ['admin', 'staff', 'teacher', 'support', 'official', 'fuck', 'sex', 'porn'];

    public function show(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $portfolio = $this->db->fetchOne('SELECT * FROM student_portfolios WHERE student_id = ?', [$studentId]);
        $this->success($portfolio ? $this->formatPortfolio($portfolio) : null);
    }

    public function update(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $existing = $this->db->fetchOne('SELECT * FROM student_portfolios WHERE student_id = ?', [$studentId]);

        $slug = $request->has('slug') ? $this->validateSlug((string) $request->input('slug'), $studentId, $existing) : ($existing['slug'] ?? null);
        if (! $slug) {
            $this->fail('slug is required.', ['slug' => ['required']]);
        }

        $data = [
            'slug' => $slug,
            'headline' => $request->input('headline', $existing['headline'] ?? null),
            'bio' => $request->input('bio', $existing['bio'] ?? null),
            'show_certificates' => $this->boolInput($request, 'show_certificates', $existing['show_certificates'] ?? 1),
            'show_badges' => $this->boolInput($request, 'show_badges', $existing['show_badges'] ?? 1),
            'show_projects' => $this->boolInput($request, 'show_projects', $existing['show_projects'] ?? 1),
        ];

        $requestedPublic = $request->has('is_public') ? $this->boolInput($request, 'is_public', 0) : (bool) ($existing['is_public'] ?? false);
        $currentlyPublic = (bool) ($existing['is_public'] ?? false);

        if (! $requestedPublic) {
            // Turning off always takes effect immediately, for any student (04j's explicit rule).
            $data['is_public'] = 0;
            $data['pending_public_request'] = 0;
        } elseif ($currentlyPublic) {
            $data['is_public'] = 1; // already public, no transition happening.
        } elseif ($this->requiresParentApproval($studentId)) {
            $data['is_public'] = 0;
            $data['pending_public_request'] = 1;
        } else {
            $data['is_public'] = 1;
            $data['pending_public_request'] = 0;
        }

        if ($existing) {
            $this->db->updateTable('student_portfolios', $data, 'student_id = ?', [$studentId]);
        } else {
            $this->db->insertInto('student_portfolios', array_merge($data, ['student_id' => $studentId]));
        }

        $this->success([
            'is_public' => (bool) $data['is_public'],
            'status' => $data['pending_public_request'] ? 'pending_parent_approval' : ($data['is_public'] ? 'public' : 'private'),
        ]);
    }

    /** Own full aggregated view, as it would render publicly — even while is_public=0 or still pending (04j's explicit rule). */
    public function preview(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $this->success($this->aggregatePortfolioForStudent($studentId));
    }

    /** Used by ParentController's portfolio-preview/approve endpoints too — same aggregation, one implementation. */
    public function aggregatePortfolioForStudent(int $studentId): array
    {
        $portfolio = $this->db->fetchOne('SELECT * FROM student_portfolios WHERE student_id = ?', [$studentId]);

        if (! $portfolio) {
            $this->fail('No portfolio has been created yet.', ['reason' => ['not_found']], 404);
        }

        return $this->aggregatePortfolio($portfolio);
    }

    public function publicShow(Request $request, string $slug): void
    {
        $portfolio = $this->db->fetchOne('SELECT * FROM student_portfolios WHERE slug = ? AND is_public = 1', [$slug]);
        if (! $portfolio) {
            $this->fail('No such portfolio.', ['reason' => ['not_found']], 404);
        }

        $this->recordView($portfolio, $request);
        $this->success($this->aggregatePortfolio($portfolio));
    }

    private function recordView(array $portfolio, Request $request): void
    {
        $ipHash = hash('sha256', $request->ip());

        // Deduplicated within a 1-hour window, entirely server-side —
        // refreshing your own page repeatedly doesn't inflate the count
        // (04j's explicit rule); nothing for a client to manage here.
        $recent = $this->db->fetchOne(
            "SELECT 1 FROM portfolio_views WHERE portfolio_student_id = ? AND viewer_ip_hash = ? AND viewed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$portfolio['student_id'], $ipHash]
        );
        if (! $recent) {
            $this->db->insertInto('portfolio_views', ['portfolio_student_id' => $portfolio['student_id'], 'viewer_ip_hash' => $ipHash]);
        }
    }

    private function aggregatePortfolio(array $portfolio): array
    {
        $student = $this->db->fetchOne('SELECT first_name, last_name FROM users WHERE id = ?', [$portfolio['student_id']]);
        $viewCount = (int) $this->db->fetchOne('SELECT COUNT(*) AS c FROM portfolio_views WHERE portfolio_student_id = ?', [$portfolio['student_id']])['c'];

        $data = [
            'slug' => $portfolio['slug'],
            'name' => trim($student['first_name'] . ' ' . $student['last_name']),
            'headline' => $portfolio['headline'],
            'bio' => $portfolio['bio'],
            'view_count' => $viewCount,
        ];

        if ($portfolio['show_projects']) {
            // A published_projects row an admin later revokes (is_public flipped
            // back to 0) simply stops appearing here — the row and original
            // approval stay intact for audit elsewhere (04j's explicit point).
            $data['projects'] = $this->db->select(
                'SELECT id, title, description, cover_image_url, view_count FROM published_projects WHERE student_id = ? AND is_public = 1 ORDER BY created_at DESC',
                [$portfolio['student_id']]
            );
        }
        if ($portfolio['show_certificates']) {
            $data['certificates'] = $this->db->select(
                "SELECT c.certificate_number, c.issued_at, co.title AS course_title
                 FROM certificates c JOIN courses co ON co.id = c.course_id
                 WHERE c.user_id = ? AND c.is_revoked = 0 ORDER BY c.issued_at DESC",
                [$portfolio['student_id']]
            );
        }
        if ($portfolio['show_badges']) {
            $data['badges'] = $this->db->select(
                'SELECT b.name, b.icon_url, sb.earned_at FROM student_badges sb JOIN badges b ON b.id = sb.badge_id WHERE sb.student_id = ? ORDER BY sb.earned_at DESC',
                [$portfolio['student_id']]
            );
            $xp = $this->db->fetchOne('SELECT total_xp, current_level FROM student_xp WHERE student_id = ?', [$portfolio['student_id']]);
            $data['total_xp'] = (int) ($xp['total_xp'] ?? 0);
            $data['current_level'] = (int) ($xp['current_level'] ?? 1);
        }

        return $data;
    }

    /** No distinct "minor" flag exists; reuses the same adult_age_threshold/date_of_birth check ParentController already established. */
    private function requiresParentApproval(int $studentId): bool
    {
        $student = $this->db->fetchOne('SELECT date_of_birth FROM users WHERE id = ?', [$studentId]);
        if (! $student['date_of_birth']) {
            return false; // no DOB on file — nothing to gate against.
        }

        $threshold = (require BASE_PATH . '/config/app.php')['adult_age_threshold'];
        $age = (new \DateTime($student['date_of_birth']))->diff(new \DateTime())->y;
        if ($age >= $threshold) {
            return false;
        }

        return (bool) $this->db->fetchOne("SELECT 1 FROM parent_student_links WHERE student_id = ? AND consent_status = 'granted'", [$studentId]);
    }

    private function validateSlug(string $slug, int $studentId, array|false $existing): string
    {
        $slug = trim(mb_strtolower($slug));
        if (! preg_match('/^[a-z0-9-]{3,100}$/', $slug)) {
            $this->fail('Slug must be 3-100 characters: lowercase letters, numbers, hyphens.', ['slug' => ['format']]);
        }

        foreach (self::INAPPROPRIATE_SLUG_WORDS as $word) {
            if (str_contains($slug, $word)) {
                $this->fail('This slug is not allowed.', ['reason' => ['inappropriate_slug']], 422);
            }
        }

        $taken = $this->db->fetchOne('SELECT student_id FROM student_portfolios WHERE slug = ?', [$slug]);
        if ($taken && (int) $taken['student_id'] !== $studentId) {
            $this->fail('This slug is already taken.', ['reason' => ['slug_taken']], 422);
        }

        return $slug;
    }

    private function boolInput(Request $request, string $key, mixed $default): int
    {
        return filter_var($request->input($key, $default), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function formatPortfolio(array $portfolio): array
    {
        return [
            'slug' => $portfolio['slug'],
            'headline' => $portfolio['headline'],
            'bio' => $portfolio['bio'],
            'show_certificates' => (bool) $portfolio['show_certificates'],
            'show_badges' => (bool) $portfolio['show_badges'],
            'show_projects' => (bool) $portfolio['show_projects'],
            'is_public' => (bool) $portfolio['is_public'],
            'pending_public_request' => (bool) $portfolio['pending_public_request'],
        ];
    }
}
