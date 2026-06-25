<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run once a day, e.g. 4:00 AM:
 *   php /home/yourusername/public_html/api/cron/generate-course-recommendations.php
 *
 * Producer for `course_recommendations` — RecommendationController::index()
 * only ever serves rows that already exist; nothing populated them before
 * this pass (a real, previously-missing producer, the same shape as the
 * risk-scoring gap found in 05c). Implements 05d §3's two-stage design
 * exactly: Stage 1 (deterministic, no model call) decides WHICH courses
 * and the confidence_score from `course_next_steps` (curriculum-maintained,
 * see schema_student_portal.sql's comment — empty until an admin populates
 * it, which is the correct "explore_other_tracks" state, not a bug) filtered
 * against the student's performance bucket and `student_profiles.interests`;
 * Stage 2 (fast-tier model call) only narrates an already-decided pick — it
 * never picks. Also closes the loop on `converted_at`: RecommendationController's
 * own doc comment states this is "set by a backend job, never a client
 * call" — this cron is that job.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\AiGateway;
use App\Core\AiUsageLog;
use App\Core\Database;

const MIN_CONFIDENCE_TO_SHOW = 40;
const MAX_CANDIDATES = 4;

$db = Database::getInstance();

markConversions($db);

$completed = $db->select(
    "SELECT e.id AS enrollment_id, e.user_id AS student_id, e.course_id
     FROM enrollments e
     WHERE e.status = 'completed'
       AND NOT EXISTS (SELECT 1 FROM course_recommendations cr WHERE cr.source_enrollment_id = e.id)"
);

$count = 0;
foreach ($completed as $enrollment) {
    if (generateForEnrollment($db, $enrollment)) {
        $count++;
    }
}

echo "{$count} student(s) given new course recommendation(s); " . count($completed) . " completed enrollment(s) checked.\n";

/**
 * A student enrolling in a course they were previously recommended is the
 * conversion event (03h's shown_at/converted_at measurement loop) — only
 * counts after it was actually shown, not just created, since showing it is
 * what the metric measures the effect of.
 */
function markConversions(Database $db): void
{
    $db->execute(
        "UPDATE course_recommendations cr
         JOIN enrollments e ON e.user_id = cr.student_id AND e.course_id = cr.recommended_course_id
         SET cr.converted_at = e.enrolled_at
         WHERE cr.shown_at IS NOT NULL AND cr.converted_at IS NULL AND e.enrolled_at >= cr.shown_at"
    );
}

function generateForEnrollment(Database $db, array $enrollment): bool
{
    $studentId = (int) $enrollment['student_id'];
    $courseId = (int) $enrollment['course_id'];

    $snapshot = $db->fetchOne(
        'SELECT * FROM student_progress_snapshots WHERE enrollment_id = ? ORDER BY snapshot_date DESC LIMIT 1',
        [$enrollment['enrollment_id']]
    );
    $completionPercent = $snapshot ? $snapshot['course_completion_percent'] : null;
    $bucket = performanceBucket($snapshot);

    $candidates = $db->select(
        "SELECT cns.recommended_course_id, cns.sort_order, c.title
         FROM course_next_steps cns JOIN courses c ON c.id = cns.recommended_course_id
         WHERE cns.source_course_id = ?
           AND (? IS NULL OR ? >= cns.min_completion_percent)
         ORDER BY cns.sort_order LIMIT " . MAX_CANDIDATES,
        [$courseId, $completionPercent, $completionPercent]
    );

    if (empty($candidates)) {
        return false; // no curriculum mapping yet, or this student didn't clear the completion bar — 04g's graceful no-fit state, not a bug.
    }

    $profile = $db->fetchOne('SELECT interests FROM student_profiles WHERE user_id = ?', [$studentId]);
    $interests = ($profile && $profile['interests']) ? json_decode($profile['interests'], true) : [];

    $given = 0;
    foreach ($candidates as $candidate) {
        $confidence = confidenceScore($bucket, $candidate, $interests);
        if ($confidence < MIN_CONFIDENCE_TO_SHOW) {
            continue;
        }

        $reasonSummary = narrate($db, $studentId, $candidate['title'], $bucket);

        $db->insertInto('course_recommendations', [
            'student_id' => $studentId,
            'source_enrollment_id' => $enrollment['enrollment_id'],
            'recommended_course_id' => $candidate['recommended_course_id'],
            'confidence_score' => $confidence,
            'reason_summary' => $reasonSummary,
        ]);
        $given++;
    }

    return $given > 0;
}

function performanceBucket(array|false $snapshot): string
{
    if (! $snapshot) {
        return 'unknown';
    }
    $scores = array_filter([
        $snapshot['course_completion_percent'] !== null ? (float) $snapshot['course_completion_percent'] : null,
        $snapshot['assignment_completion_percent'] !== null ? (float) $snapshot['assignment_completion_percent'] : null,
        $snapshot['avg_project_score'] !== null ? (float) $snapshot['avg_project_score'] : null,
        $snapshot['avg_assessment_score'] !== null ? (float) $snapshot['avg_assessment_score'] : null,
    ], fn ($v) => $v !== null);

    if (! $scores) {
        return 'unknown';
    }
    $avg = array_sum($scores) / count($scores);

    return match (true) {
        $avg >= 80 => 'strong',
        $avg >= 50 => 'average',
        default => 'weak',
    };
}

/**
 * Computed here, never asked of the model (05d §3's explicit reasoning) —
 * keeps the one number this feature's real-world accuracy gets measured by
 * (via shown_at/converted_at) out of the LLM's hands, the same separation
 * 05c §4 draws for risk scoring.
 */
function confidenceScore(string $bucket, array $candidate, array $interests): int
{
    $base = match ($bucket) {
        'strong' => 75,
        'average' => 55,
        'weak' => 35,
        default => 45,
    };

    $base -= (int) $candidate['sort_order'] * 5; // earlier-ranked catalog mappings are the curriculum's own stronger pick

    // Crude keyword containment — no controlled-vocabulary join exists
    // between interest tags and course titles, so this is a best-effort
    // boost, not a strict match.
    foreach ($interests as $interest) {
        if (is_string($interest) && stripos($candidate['title'], str_replace('_', ' ', $interest)) !== false) {
            $base += 15;
            break;
        }
    }

    return max(0, min(100, $base));
}

/**
 * Stage 2 (05d §3) — fast/cheap-tier, narration only. The pick and its
 * confidence score are already decided above; the model's only job is
 * explaining it in one short, student-readable sentence.
 */
function narrate(Database $db, int $studentId, string $courseTitle, string $bucket): string
{
    $performanceLine = match ($bucket) {
        'strong' => 'they performed strongly in their most recent course',
        'average' => 'they completed their most recent course with solid, steady progress',
        'weak' => 'they completed their most recent course, with room still to build confidence',
        default => 'they recently completed a course',
    };

    try {
        $result = AiGateway::complete(
            [['role' => 'user', 'content' => "Course being recommended: {$courseTitle}. Context: {$performanceLine}."]],
            'Write one short sentence (max 25 words) explaining to a student why this course is a good next step, given the '
                . 'context. Do not invent specific topics, grades, or events not mentioned in the context. No greeting, no '
                . 'preamble, just the sentence.',
            AiGateway::tierFor('recommendation.narration'),
            'course_recommendation_narration_v1'
        );

        $costUsd = AiGateway::estimateCostUsd($result['model'], $result['tokens_input'], $result['tokens_output']);
        AiUsageLog::record($db, $studentId, 'recommendation.narration', $result, $costUsd);

        return mb_substr(trim($result['content']), 0, 255);
    } catch (\Throwable $e) {
        return 'A recommended next step based on your progress in your recent course.';
    }
}
