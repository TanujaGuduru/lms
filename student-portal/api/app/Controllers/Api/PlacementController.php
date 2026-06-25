<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\AiGateway;
use App\Core\AiUsageLog;
use App\Core\Controller;
use App\Core\Moderation;
use App\Core\Request;

/**
 * Placement / Skill Assessment — docs/student-module/04b-apis-assessment-scheduling.md,
 * communication-section AI scoring per 05d §1.
 *
 * Two business rules the docs assume but never pin down a mechanism for,
 * resolved here with the simplest defensible interpretation (flagged so a
 * real business-rules pass can override later):
 *  - "Which courses require placement at all" has no schema flag anywhere in
 *    the 35-document series — every course is treated as requiring one.
 *  - "Which exam is *the* placement test for this course" has no FK either —
 *    resolved by `exams.type='placement'` scoped to the course, falling back
 *    to a course_id-agnostic one if no course-specific exam exists.
 *
 * **Communication-section AI scoring is now wired up** — it was originally
 * deferred ("pending a real AI Gateway") before 04d/05a built one; now that
 * one exists, `scoreCommunication()` runs it for real. Two things worth
 * noting about how it's wired in:
 *  - **Not gated by `App\Core\AiQuota`.** That quota exists to cap a
 *    student's discretionary daily "AI help" usage (chat, notebook
 *    features) — placement is a mandatory, largely one-shot flow a student
 *    can't simply choose to skip, so blocking it behind an unrelated daily
 *    chat-message cap would be the wrong call. Cost is still logged via
 *    `AiUsageLog` for the platform-wide circuit breaker, same as the
 *    crons' non-quota-gated calls.
 *  - **Borderline routing is a real, deterministic threshold check** (05d
 *    §1's explicit requirement) — computed from the actual overall score
 *    against a configured band around the level cutoffs, not asked of the
 *    model. Surfaced as `is_borderline` on `result()`'s response; there is
 *    no Admin/mentor-portal UI in this codebase to route it to a "mini-
 *    check" queue (that UI lives in the separate Admin panel, out of this
 *    session's scope) — the signal is real and exposed at the API level,
 *    same honest boundary as everywhere else a mentor-side action is
 *    referenced but not built here.
 */
class PlacementController extends Controller
{
    public function status(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $enrollment = $this->activeEnrollment($studentId);

        if (! $enrollment) {
            $this->fail('No active enrollment found.', ['reason' => ['no_active_enrollment']], 404);
        }

        $attempt = $this->latestAttempt($studentId, (int) $enrollment['course_id']);

        // No attempt yet, or the latest one was abandoned/invalidated (the
        // recheck path explicitly invalidates the old attempt so a fresh one
        // is expected) — both report 'required', same as never having taken
        // it; reporting the stale invalidated attempt's old result as 'done'
        // would contradict requestRecheck() having just unblocked start().
        if (! $attempt || in_array($attempt['status'], ['abandoned', 'invalidated'], true)) {
            $this->success(['status' => 'required']);
            return;
        }

        $result = $this->db->fetchOne('SELECT * FROM placement_results WHERE exam_attempt_id = ?', [$attempt['id']]);

        if ($attempt['status'] === 'in_progress') {
            $this->success(['status' => 'in_progress', 'attempt_id' => (int) $attempt['id']]);
            return;
        }

        $this->success(['status' => ($result && $result['reviewed_by']) ? 'done' : 'pending_review', 'attempt_id' => (int) $attempt['id']]);
    }

    public function start(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $enrollment = $this->activeEnrollment($studentId);

        if (! $enrollment) {
            $this->fail('No active enrollment found.', ['reason' => ['no_active_enrollment']], 404);
        }

        // 'abandoned' (never finished) and 'invalidated' (recheck approved by
        // requestRecheck()) both deliberately don't block a new attempt —
        // only a still-live or fully-resolved-and-undisputed one does.
        $existing = $this->latestAttempt($studentId, (int) $enrollment['course_id']);
        if ($existing && ! in_array($existing['status'], ['abandoned', 'invalidated'], true)) {
            $this->fail('A placement attempt already exists for this enrollment.', ['reason' => ['already_completed']], 422);
        }

        $exam = $this->db->fetchOne(
            "SELECT * FROM exams WHERE type = 'placement' AND (course_id = ? OR course_id IS NULL)
             ORDER BY (course_id IS NULL) ASC LIMIT 1",
            [$enrollment['course_id']]
        );

        if (! $exam) {
            $this->fail('No placement exam is configured for this course.', ['reason' => ['no_placement_exam']], 422);
        }

        $attemptId = $this->db->insertInto('exam_attempts', [
            'exam_id' => $exam['id'],
            'user_id' => $studentId,
            'status' => 'in_progress',
        ]);

        $questions = $this->orderedQuestions((int) $exam['id'], (int) $attemptId, (bool) $exam['shuffle_questions']);

        $this->success([
            'attempt_id' => (int) $attemptId,
            'duration_minutes' => (int) $exam['duration_minutes'],
            'started_at' => date('c'),
            'questions' => $questions,
        ]);
    }

    public function answer(Request $request, string $attemptId): void
    {
        $attempt = $this->ownAttempt($attemptId);
        $questionId = (int) $request->input('question_id', 0);
        $response = $request->input('response');

        if (! $questionId) {
            $this->fail('question_id is required.', ['question_id' => ['required']]);
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM exam_responses WHERE attempt_id = ? AND question_id = ?',
            [$attempt['id'], $questionId]
        );

        if ($existing) {
            $this->db->updateTable('exam_responses', ['response' => json_encode($response)], 'id = ?', [$existing['id']]);
        } else {
            $this->db->insertInto('exam_responses', [
                'attempt_id' => $attempt['id'],
                'question_id' => $questionId,
                'response' => json_encode($response),
            ]);
        }

        $this->success(null);
    }

    public function submit(Request $request, string $attemptId): void
    {
        $attempt = $this->ownAttempt($attemptId);

        if ($attempt['status'] !== 'in_progress') {
            $this->fail('This attempt was already submitted.', ['reason' => ['already_submitted']], 422);
        }

        $responses = $this->db->select(
            'SELECT er.*, q.type, q.correct_answer, q.marks, q.category_id
             FROM exam_responses er JOIN questions q ON q.id = er.question_id
             WHERE er.attempt_id = ?',
            [$attempt['id']]
        );

        $objectiveTotal = 0;
        $objectiveAwarded = 0;
        $logicalTotal = 0;
        $logicalAwarded = 0;
        $communicationResponses = [];

        foreach ($responses as $response) {
            $marks = (float) $response['marks'];
            $isObjective = in_array($response['type'], ['mcq', 'msq', 'true_false'], true);

            if (! $isObjective) {
                if (in_array($response['type'], ['short_answer', 'long_answer'], true)) {
                    $communicationResponses[] = $response;
                }
                continue;
            }

            $correct = $this->normalize(json_decode((string) $response['correct_answer'], true));
            $given = $this->normalize(json_decode((string) $response['response'], true));
            $isCorrect = $correct === $given;
            $awarded = $isCorrect ? $marks : 0.0;

            $this->db->updateTable('exam_responses', [
                'is_correct' => $isCorrect ? 1 : 0,
                'marks_awarded' => $awarded,
            ], 'id = ?', [$response['id']]);

            // Logic-puzzle-flavoured questions count toward logical_reasoning;
            // everything else objective counts toward coding — a coarse but
            // defensible split given no dedicated "skill dimension" column
            // exists on `questions`.
            if ($response['type'] === 'true_false') {
                $logicalTotal += $marks;
                $logicalAwarded += $awarded;
            } else {
                $objectiveTotal += $marks;
                $objectiveAwarded += $awarded;
            }
        }

        $codingScore = $objectiveTotal > 0 ? (int) round($objectiveAwarded / $objectiveTotal * 100) : null;
        $logicalScore = $logicalTotal > 0 ? (int) round($logicalAwarded / $logicalTotal * 100) : null;
        $communication = $this->scoreCommunication((int) $this->currentUser()['id'], $communicationResponses);

        $this->db->transaction(function () use ($attempt, $codingScore, $logicalScore, $communication) {
            $this->db->updateTable('exam_attempts', [
                'status' => 'completed',
                'submitted_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$attempt['id']]);

            $this->db->insertInto('placement_results', [
                'exam_attempt_id' => $attempt['id'],
                'coding_score' => $codingScore,
                'logical_reasoning_score' => $logicalScore,
                'communication_score' => $communication['score'],
                'communication_score_rationale' => $communication['rationale'],
                'recommended_level' => $this->levelFromScore($codingScore, $logicalScore, $communication['score']),
                'ai_generated' => 1,
            ]);
        });

        $this->success(['status' => 'submitted', 'attempt_id' => (int) $attempt['id']]);
    }

    public function result(Request $request, string $attemptId): void
    {
        $attempt = $this->ownAttempt($attemptId);
        $result = $this->db->fetchOne('SELECT * FROM placement_results WHERE exam_attempt_id = ?', [$attempt['id']]);

        if (! $result) {
            $this->fail('This attempt has not been submitted yet.', ['reason' => ['not_submitted']], 422);
        }

        if (! $result['reviewed_by']) {
            $this->success([
                'status' => 'pending_review',
                'ai_recommended_level' => $result['recommended_level'],
                'is_borderline' => $this->isBorderline($result),
            ]);
            return;
        }

        $this->success([
            'status' => 'confirmed',
            'recommended_level' => $result['recommended_level'],
            'recommended_course_id' => $result['recommended_course_id'] !== null ? (int) $result['recommended_course_id'] : null,
            'scores' => [
                'coding' => $result['coding_score'] !== null ? (int) $result['coding_score'] : null,
                'logical_reasoning' => $result['logical_reasoning_score'] !== null ? (int) $result['logical_reasoning_score'] : null,
                'communication' => $result['communication_score'] !== null ? (int) $result['communication_score'] : null,
            ],
            'communication_rationale' => $result['communication_score_rationale'],
        ]);
    }

    public function requestRecheck(Request $request, string $attemptId): void
    {
        $attempt = $this->ownAttempt($attemptId);
        $result = $this->db->fetchOne('SELECT * FROM placement_results WHERE exam_attempt_id = ?', [$attempt['id']]);

        if (! $result || ! $result['reviewed_by']) {
            $this->fail('Only a mentor-confirmed result can be disputed.', ['reason' => ['not_yet_confirmed']], 422);
        }

        // "Triggers a fresh attempt cycle" (04b) — marking the old attempt
        // abandoned is what lets start() create a new one without tripping
        // the one-shot already_completed guard.
        $this->db->updateTable('exam_attempts', ['status' => 'invalidated'], 'id = ?', [$attempt['id']]);

        $this->success(['message' => 'A new placement attempt is now available.']);
    }

    private function activeEnrollment(int $studentId): array|false
    {
        return $this->db->fetchOne(
            "SELECT * FROM enrollments WHERE user_id = ? AND status = 'active' ORDER BY enrolled_at DESC LIMIT 1",
            [$studentId]
        );
    }

    private function latestAttempt(int $studentId, int $courseId): array|false
    {
        return $this->db->fetchOne(
            "SELECT ea.* FROM exam_attempts ea
             JOIN exams e ON e.id = ea.exam_id
             WHERE ea.user_id = ? AND e.type = 'placement' AND (e.course_id = ? OR e.course_id IS NULL)
             ORDER BY ea.id DESC LIMIT 1",
            [$studentId, $courseId]
        );
    }

    private function ownAttempt(string $attemptId): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $attempt = $this->db->fetchOne('SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?', [$attemptId, $studentId]);

        if (! $attempt) {
            $this->fail('No such attempt.', ['reason' => ['not_found']], 404);
        }

        return $attempt;
    }

    /**
     * Shuffled, seeded deterministically by attempt_id (03f) — a page reload
     * mid-attempt must show the same order, not re-shuffle.
     */
    private function orderedQuestions(int $examId, int $attemptId, bool $shuffle): array
    {
        $questions = $this->db->select(
            'SELECT q.id, q.question_text, q.type, q.options
             FROM exam_questions eq JOIN questions q ON q.id = eq.question_id
             WHERE eq.exam_id = ? ORDER BY eq.sort_order',
            [$examId]
        );

        if ($shuffle) {
            mt_srand($attemptId);
            shuffle($questions);
            mt_srand(); // reseed from system entropy so nothing else in this request inherits a predictable sequence
        }

        return array_map(fn (array $q) => [
            'id' => (int) $q['id'],
            'type' => $q['type'],
            'text' => $q['question_text'],
            'options' => $q['options'] !== null ? json_decode($q['options'], true) : null,
        ], $questions);
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            sort($value);
        }
        return $value;
    }

    private const LEVEL_CUTOFFS = [50, 80];
    private const BORDERLINE_BAND = 5;

    private function levelFromScore(?int $coding, ?int $logical, ?int $communication): string
    {
        $scores = array_filter([$coding, $logical, $communication], fn ($v) => $v !== null);
        if (! $scores) {
            return 'beginner';
        }
        $avg = $this->overallScore($scores);

        return match (true) {
            $avg >= self::LEVEL_CUTOFFS[1] => 'advanced',
            $avg >= self::LEVEL_CUTOFFS[0] => 'intermediate',
            default => 'beginner',
        };
    }

    private function overallScore(array $scores): float
    {
        return array_sum($scores) / count($scores);
    }

    /**
     * A deterministic threshold check on the returned score, not the model
     * self-reporting uncertainty (05d §1's explicit requirement) — within
     * a configured band of either level cutoff routes to a mentor mini-
     * check rather than a result a mentor might rubber-stamp without a
     * closer look.
     */
    private function isBorderline(array $result): bool
    {
        $scores = array_filter([
            $result['coding_score'] !== null ? (int) $result['coding_score'] : null,
            $result['logical_reasoning_score'] !== null ? (int) $result['logical_reasoning_score'] : null,
            $result['communication_score'] !== null ? (int) $result['communication_score'] : null,
        ], fn ($v) => $v !== null);

        if (! $scores) {
            return false;
        }
        $avg = $this->overallScore($scores);

        foreach (self::LEVEL_CUTOFFS as $cutoff) {
            if (abs($avg - $cutoff) <= self::BORDERLINE_BAND) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fast/cheap tier — runs synchronously while the student waits for
     * their result, sharing the Doubt Solver's <3s-class latency
     * expectation (05d §1), not the relaxed budget of a report-generation
     * job. Falls back to a NULL score (same as before this pass existed)
     * on any failure, rather than blocking placement submission entirely
     * on an AI outage.
     */
    private function scoreCommunication(int $studentId, array $communicationResponses): array
    {
        if (empty($communicationResponses)) {
            return ['score' => null, 'rationale' => null];
        }

        $combined = implode("\n\n", array_map(
            fn (array $r) => 'Response: ' . json_decode((string) $r['response'], true),
            $communicationResponses
        ));

        if (Moderation::isBlocked($combined)) {
            return ['score' => null, 'rationale' => null];
        }

        try {
            $result = AiGateway::complete(
                [['role' => 'user', 'content' => $combined]],
                'You are scoring the open-ended communication section of a student placement assessment. Rate clarity of '
                    . "expression, grammatical correctness appropriate to the student's age band, and ability to explain a "
                    . 'technical concept in their own words. Reply with ONLY a JSON object like {"score": N, "rationale": "..."} '
                    . 'where N is an integer 0-100 and rationale is one short sentence. ' . Moderation::SAFETY_INSTRUCTION,
                AiGateway::tierFor('placement.communication_score'),
                'placement_communication_v1'
            );

            $costUsd = AiGateway::estimateCostUsd($result['model'], $result['tokens_input'], $result['tokens_output']);
            AiUsageLog::record($this->db, $studentId, 'placement.communication_score', $result, $costUsd);

            $decoded = json_decode($this->extractJsonObject($result['content']), true);
            $score = isset($decoded['score']) ? max(0, min(100, (int) $decoded['score'])) : null;
            $rationale = is_string($decoded['rationale'] ?? null) ? mb_substr($decoded['rationale'], 0, 500) : null;

            return ['score' => $score, 'rationale' => $rationale];
        } catch (\Throwable $e) {
            return ['score' => null, 'rationale' => null];
        }
    }

    private function extractJsonObject(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        return ($start !== false && $end !== false) ? substr($text, $start, $end - $start + 1) : '{}';
    }
}
