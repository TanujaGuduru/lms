<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

/**
 * Renewal / Upsell — docs/student-module/04g-apis-completion-growth.md.
 * The ranking mechanics that populate `course_recommendations` are an
 * explicitly out-of-scope "Phase 5" AI concern per the doc — this endpoint
 * only ever serves whatever the engine already computed, never invents it.
 */
class RecommendationController extends Controller
{
    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];

        $rows = $this->db->select(
            "SELECT cr.id, cr.recommended_course_id, c.title, cr.confidence_score, cr.reason_summary
             FROM course_recommendations cr JOIN courses c ON c.id = cr.recommended_course_id
             WHERE cr.student_id = ? AND cr.converted_at IS NULL
             ORDER BY cr.confidence_score DESC",
            [$studentId]
        );

        if (empty($rows)) {
            $this->success([], ['state' => 'explore_other_tracks']);
        }

        // The request is the access event — same pattern 04c already uses
        // for material downloads — which is what makes the engine's hit
        // rate measurable later via converted_at (set by a backend job, never a client call).
        $this->db->execute(
            "UPDATE course_recommendations SET shown_at = NOW() WHERE student_id = ? AND shown_at IS NULL",
            [$studentId]
        );

        $this->success(array_map(fn (array $r) => [
            'id' => (int) $r['id'],
            'recommended_course_id' => (int) $r['recommended_course_id'],
            'title' => $r['title'],
            'confidence_score' => (int) $r['confidence_score'],
            'reason_summary' => $r['reason_summary'],
        ], $rows));
    }
}
