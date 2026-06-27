<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Gamification;
use App\Core\Request;

/**
 * Live Quizzes — docs/student-module/04i-apis-quizzes-replay-offline.md.
 * The doc frames launch/leaderboard/close as "pushed over the live class's
 * existing Pusher/Ably channel, never polled" — there is no Pusher/Ably
 * anywhere in this build (see ClassroomController's class docblock).
 * `current()` is the real adaptation: clients poll it the same ~2s cadence
 * already used for chat/signal polling during a live class, discovering a
 * newly-launched quiz the same way they discover a new chat message —
 * nothing here pushes, everything is asked for.
 *
 * `live_quizzes` rows themselves are created by teacher-side Admin-portal
 * code (launching a quiz), entirely outside this codebase's reach — this
 * controller only ever reads/responds to quizzes that already exist.
 */
class LiveQuizController extends Controller
{
    public function current(Request $request, string $classId): void
    {
        // Every other method here scopes through requireQuiz()'s
        // batch_students join - this one queried live_quizzes directly by
        // classId with no such check, letting any authenticated student
        // poll any class's live quiz (questions/options, not the answer
        // key) regardless of their own batch. Mirrors requireQuiz()'s join
        // for consistency rather than introducing a second authorization
        // pattern.
        $studentId = (int) $this->currentUser()['id'];

        $quiz = $this->db->fetchOne(
            "SELECT lq.* FROM live_quizzes lq
             JOIN live_classes lc ON lc.id = lq.live_class_id
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?
             WHERE lq.live_class_id = ? AND lq.launched_at IS NOT NULL AND lq.closed_at IS NULL
             ORDER BY lq.launched_at DESC LIMIT 1",
            [$studentId, $classId]
        );

        $this->success($quiz ? $this->shapeQuizState($quiz) : null);
    }

    public function show(Request $request, string $id): void
    {
        $quiz = $this->requireQuiz($id, (int) $this->currentUser()['id']);
        $this->success($this->shapeQuizState($quiz));
    }

    public function respond(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $quiz = $this->requireQuiz($id, (int) $user['id']);

        if (! $quiz['launched_at']) {
            $this->fail('This quiz has not been launched yet.', ['reason' => ['not_launched']], 409);
        }

        $deadline = strtotime($quiz['launched_at']) + (int) $quiz['duration_seconds'];
        if (time() > $deadline || $quiz['closed_at']) {
            $this->fail('This quiz has closed.', ['reason' => ['quiz_closed']], 409);
        }

        $existing = $this->db->fetchOne('SELECT id FROM live_quiz_responses WHERE live_quiz_id = ? AND student_id = ?', [$id, $user['id']]);
        if ($existing) {
            $this->fail('You already responded to this quiz.', ['reason' => ['already_responded']], 409);
        }

        $responseValue = (string) $request->input('response_value', '');
        if ($responseValue === '') {
            $this->fail('response_value is required.', ['response_value' => ['required']]);
        }

        // Server-anchored, never client-reported — what makes rapid_quiz
        // speed scoring trustworthy (04i's explicit reasoning).
        $responseTimeMs = (int) round((microtime(true) - strtotime($quiz['launched_at'])) * 1000);

        $correctAnswer = $this->correctAnswer($quiz);
        $isCorrect = $correctAnswer !== null ? $this->valuesMatch($responseValue, $correctAnswer) : null;
        $points = $this->scoreResponse($quiz, $isCorrect, $responseTimeMs);

        $responseId = $this->db->insertInto('live_quiz_responses', [
            'live_quiz_id' => $id,
            'student_id' => $user['id'],
            'response_value' => $responseValue,
            'response_time_ms' => max(0, $responseTimeMs),
            'is_correct' => $isCorrect === null ? null : ($isCorrect ? 1 : 0),
            'points_awarded' => $points,
        ]);

        if ($isCorrect && $points > 0) {
            Gamification::awardXp($this->db, (int) $user['id'], $points, 'quiz_won', 'live_quiz_response', (int) $responseId);
        }

        $this->success(['is_correct' => $isCorrect, 'points_awarded' => $points]);
    }

    public function results(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $quiz = $this->requireQuiz($id, (int) $user['id']);

        $ownResponse = $this->db->fetchOne('SELECT * FROM live_quiz_responses WHERE live_quiz_id = ? AND student_id = ?', [$id, $user['id']]);

        // Denominator is "who was already in the room when this launched,"
        // not the whole batch — a student who joined after launched_at
        // genuinely wasn't there for it (04i's explicit reasoning).
        $presentAtLaunch = (int) $this->db->fetchOne(
            'SELECT COUNT(DISTINCT student_id) AS c FROM attendance WHERE live_class_id = ? AND join_time <= ?',
            [$quiz['live_class_id'], $quiz['launched_at']]
        )['c'];

        $stats = $this->db->fetchOne(
            'SELECT COUNT(*) AS responded, SUM(is_correct = 1) AS correct, AVG(response_time_ms) AS avg_ms
             FROM live_quiz_responses WHERE live_quiz_id = ?',
            [$id]
        );

        $responded = (int) $stats['responded'];

        $this->success([
            'participation_rate' => $presentAtLaunch > 0 ? round($responded / $presentAtLaunch, 2) : null,
            'accuracy' => $responded > 0 ? round((int) $stats['correct'] / $responded, 2) : null,
            'avg_response_time_ms' => $stats['avg_ms'] !== null ? (int) round((float) $stats['avg_ms']) : null,
            'own_response' => $ownResponse ? [
                'response_value' => $ownResponse['response_value'],
                'is_correct' => $ownResponse['is_correct'] === null ? null : (bool) $ownResponse['is_correct'],
            ] : null,
            'correct_answer' => $this->correctAnswer($quiz),
            'explain_mode_conversation_id' => $ownResponse['explain_mode_conversation_id'] ?? null,
        ]);
    }

    /** Reuses the existing AI Doubt Solver rather than inventing a parallel explanation feature (04i's explicit reuse point). */
    public function explainMode(Request $request, string $id): void
    {
        $user = $this->currentUser();
        $quiz = $this->requireQuiz($id, (int) $user['id']);

        $ownResponse = $this->db->fetchOne('SELECT * FROM live_quiz_responses WHERE live_quiz_id = ? AND student_id = ?', [$id, $user['id']]);
        if (! $ownResponse) {
            $this->fail('You did not respond to this quiz.', ['reason' => ['no_response']], 422);
        }
        if ($ownResponse['explain_mode_conversation_id']) {
            $this->success(['conversation_id' => (int) $ownResponse['explain_mode_conversation_id']]);
        }

        $classCourse = $this->db->fetchOne(
            'SELECT b.course_id FROM live_classes lc JOIN batches b ON b.id = lc.batch_id WHERE lc.id = ?',
            [$quiz['live_class_id']]
        );
        $courseId = $classCourse['course_id'] ?? null;

        $conversationId = $this->db->insertInto('ai_conversations', [
            'student_id' => $user['id'],
            'conversation_type' => 'doubt_solver',
            'mode' => 'explain',
            'linked_course_id' => $courseId,
        ]);

        $this->db->updateTable('live_quiz_responses', ['explain_mode_conversation_id' => $conversationId], 'id = ?', [$ownResponse['id']]);

        $this->success(['conversation_id' => (int) $conversationId]);
    }

    private function shapeQuizState(array $quiz): array
    {
        $question = $quiz['question_id']
            ? $this->db->fetchOne('SELECT question_text, options FROM questions WHERE id = ?', [$quiz['question_id']])
            : ['question_text' => $quiz['ad_hoc_question_text'], 'options' => $quiz['ad_hoc_options']];

        return [
            'id' => (int) $quiz['id'],
            'quiz_type' => $quiz['quiz_type'],
            'question_text' => $question['question_text'] ?? null,
            'options' => $question['options'] ? json_decode($question['options'], true) : null,
            'launched_at' => $quiz['launched_at'],
            'duration_seconds' => (int) $quiz['duration_seconds'],
            'server_now' => date('c'),
        ];
    }

    private function correctAnswer(array $quiz): ?string
    {
        if ($quiz['question_id']) {
            $question = $this->db->fetchOne('SELECT correct_answer FROM questions WHERE id = ?', [$quiz['question_id']]);
            $decoded = json_decode($question['correct_answer'] ?? 'null', true);
            return is_array($decoded) ? json_encode($decoded) : (string) $decoded;
        }
        return $quiz['ad_hoc_correct_answer'];
    }

    private function valuesMatch(string $given, string $correct): bool
    {
        $decodedCorrect = json_decode($correct, true);
        if (is_array($decodedCorrect)) {
            $decodedGiven = json_decode($given, true);
            if (is_array($decodedGiven)) {
                sort($decodedGiven);
                sort($decodedCorrect);
                return $decodedGiven == $decodedCorrect;
            }
        }
        return $given === $correct;
    }

    /** rapid_quiz rewards speed; everything else is a flat score for a correct answer — polls never score at all (no correct answer exists). */
    private function scoreResponse(array $quiz, ?bool $isCorrect, int $responseTimeMs): int
    {
        if ($quiz['quiz_type'] === 'poll' || $isCorrect === null) {
            return 0;
        }
        if (! $isCorrect) {
            return 0;
        }
        if ($quiz['quiz_type'] !== 'rapid_quiz') {
            return 10;
        }

        $durationMs = (int) $quiz['duration_seconds'] * 1000;
        $speedFraction = max(0, 1 - ($responseTimeMs / max($durationMs, 1)));
        return 10 + (int) round($speedFraction * 10); // 10-20 points, faster = closer to 20
    }

    /** 404, never 403, on a quiz outside the requester's batch — same "don't confirm it exists" reasoning used throughout this catalog (e.g. RecordingController). */
    private function requireQuiz(string $id, int $studentId): array
    {
        $quiz = $this->db->fetchOne(
            "SELECT lq.* FROM live_quizzes lq
             JOIN live_classes lc ON lc.id = lq.live_class_id
             JOIN batch_students bs ON bs.batch_id = lc.batch_id AND bs.student_id = ?
             WHERE lq.id = ?",
            [$studentId, $id]
        );
        if (! $quiz) {
            $this->fail('No such quiz.', ['reason' => ['not_found']], 404);
        }
        return $quiz;
    }
}
