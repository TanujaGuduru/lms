<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\AiGateway;
use App\Core\AiQuota;
use App\Core\AiUsageLog;
use App\Core\Controller;
use App\Core\Moderation;
use App\Core\Request;

/**
 * Digital Notebook — docs/student-module/04h-apis-notebook-collab.md.
 * `POST /notes/voice-transcribe` is **not implemented in this pass** —
 * Anthropic's API (this app's one accepted external dependency, see
 * App\Core\AiGateway) has no speech-to-text capability; a real
 * implementation would need a second external provider (e.g. OpenAI's
 * Whisper API) purely for audio transcription. Adding a second accepted
 * cloud dependency for one convenience feature isn't a call to make
 * silently — deferred and documented here rather than faked with a stub
 * that pretends to transcribe.
 *
 * Per 05a, every AI call here goes through the same shared quota
 * (App\Core\AiQuota) and moderation (App\Core\Moderation) gates as the
 * Doubt Solver chat — these endpoints were unmetered before this pass,
 * a real gap 05a's "every feature... relies on" framing exposed.
 */
class NoteController extends Controller
{
    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $courseId = $request->input('course_id');
        $tag = $request->input('tag');
        $favorite = $request->input('favorite');

        $sql = "SELECT id, title, linked_course_id, linked_lesson_id, linked_live_class_id, is_ai_generated, is_favorite, tags, updated_at
                FROM notes WHERE student_id = ? AND deleted_at IS NULL";
        $params = [$studentId];

        if ($courseId) {
            $sql .= ' AND linked_course_id = ?';
            $params[] = $courseId;
        }
        if ($tag) {
            $sql .= ' AND JSON_CONTAINS(tags, JSON_QUOTE(?))';
            $params[] = $tag;
        }
        if ($favorite !== null) {
            $sql .= ' AND is_favorite = ?';
            $params[] = filter_var($favorite, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        $sql .= ' ORDER BY updated_at DESC';

        $rows = $this->db->select($sql, $params);
        $this->success(array_map(fn (array $r) => $this->formatNote($r), $rows));
    }

    public function create(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $content = (string) $request->input('content', '');

        $id = $this->db->insertInto('notes', [
            'student_id' => $studentId,
            'title' => $request->input('title') ?: null,
            'content' => $content,
            'linked_course_id' => $request->input('linked_course_id') ?: null,
            'linked_lesson_id' => $request->input('linked_lesson_id') ?: null,
            'tags' => $request->has('tags') ? json_encode($request->input('tags')) : null,
        ]);

        $this->success($this->formatNote($this->ownNote((string) $id)), [], 201);
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->formatNote($this->ownNote($id)));
    }

    /**
     * Debounced autosave straight to content — a full note_versions
     * snapshot only on a coarser threshold (here: once per 15-minute
     * editing session), never on every keystroke (04h's explicit reasoning
     * for why note_versions doesn't grow unboundedly).
     */
    public function update(Request $request, string $id): void
    {
        $note = $this->ownNote($id);
        $allowed = ['title', 'content', 'is_favorite'];
        $data = array_intersect_key($request->all(), array_flip($allowed));

        if ($request->has('tags')) {
            $data['tags'] = json_encode($request->input('tags'));
        }
        if (isset($data['is_favorite'])) {
            $data['is_favorite'] = filter_var($data['is_favorite'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (empty($data)) {
            $this->fail('No updatable fields were provided.', ['reason' => ['empty_payload']]);
        }

        if (isset($data['content']) && $data['content'] !== $note['content']) {
            $this->maybeSnapshotVersion($note);
        }

        $this->db->updateTable('notes', $data, 'id = ?', [$id]);
        $this->success($this->formatNote($this->ownNote($id)));
    }

    public function destroy(Request $request, string $id): void
    {
        $this->ownNote($id);
        $this->db->updateTable('notes', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->success(true);
    }

    public function versions(Request $request, string $id): void
    {
        $this->ownNote($id);
        $rows = $this->db->select('SELECT id, version_number, created_at FROM note_versions WHERE note_id = ? ORDER BY version_number DESC', [$id]);
        $this->success($rows);
    }

    /** A suggestion only — never overwrites content directly; the client offers replace-or-append, then an ordinary PATCH (04h's explicit rule). */
    public function summarize(Request $request, string $id): void
    {
        $note = $this->ownNote($id);
        $studentId = (int) $this->currentUser()['id'];
        $this->requireQuotaAndModeration($studentId, $note['content']);

        try {
            $result = AiGateway::complete(
                [['role' => 'user', 'content' => $note['content']]],
                'Condense this note into 3-5 key points, in the student\'s own conceptual framing, not a generic restatement. ' . Moderation::SAFETY_INSTRUCTION,
                AiGateway::tierFor('notebook.summarize'),
                'notebook_summarize_v1'
            );
        } catch (\Throwable $e) {
            $this->fail('The AI assistant is temporarily unavailable. Please try again shortly.', ['reason' => ['ai_gateway_error']], 503);
        }

        $this->logUsage($studentId, 'notebook.summarize', $result);
        $summary = Moderation::isResponseSafe($result['content']) ? $result['content'] : Moderation::fallbackMessage();
        $this->success(['suggested_summary' => $summary]);
    }

    public function flashcards(Request $request, string $id): void
    {
        $note = $this->ownNote($id);
        $studentId = (int) $this->currentUser()['id'];
        $this->requireQuotaAndModeration($studentId, $note['content']);

        $pairs = $this->extractFlashcardPairs($note['content'], $studentId);
        if (empty($pairs)) {
            $this->fail('Could not extract any question/answer pairs from this note.', ['reason' => ['no_pairs_found']], 422);
        }

        $deckName = $note['title'] ?: 'Untitled deck';
        $created = [];
        foreach ($pairs as $pair) {
            $cardId = $this->db->insertInto('flashcards', [
                'source_note_id' => $note['id'],
                'student_id' => $studentId,
                'deck_name' => $deckName,
                'front_text' => $pair['front'],
                'back_text' => $pair['back'],
                'next_review_at' => date('Y-m-d H:i:s'),
            ]);
            $created[] = (int) $cardId;
        }

        $this->success(['deck_name' => $deckName, 'flashcard_ids' => $created]);
    }

    /** Reuses the exact same exam infrastructure as every other assessment (04h's explicit reuse principle) — no parallel quiz system. */
    public function generateQuiz(Request $request, string $id): void
    {
        $note = $this->ownNote($id);
        $studentId = (int) $this->currentUser()['id'];
        $this->requireQuotaAndModeration($studentId, $note['content']);

        $questions = $this->extractQuizQuestions($note['content'], $studentId);
        if (empty($questions)) {
            $this->fail('Could not generate quiz questions from this note.', ['reason' => ['generation_failed']], 422);
        }

        $examId = $this->db->insertInto('exams', [
            'course_id' => $note['linked_course_id'],
            'created_by' => $studentId,
            'title' => 'Practice Quiz: ' . ($note['title'] ?: 'My Note'),
            'type' => 'practice',
            'source_note_id' => $note['id'],
            'status' => 'published',
            'show_result_immediately' => 1,
        ]);

        foreach ($questions as $i => $q) {
            $questionId = $this->db->insertInto('questions', [
                'created_by' => $studentId,
                'question_text' => $q['question_text'],
                'type' => 'mcq',
                'options' => json_encode($q['options']),
                'correct_answer' => json_encode($q['correct_answer']),
                'marks' => 5,
            ]);
            $this->db->insertInto('exam_questions', ['exam_id' => $examId, 'question_id' => $questionId, 'sort_order' => $i]);
        }

        $this->success(['exam_id' => (int) $examId]);
    }

    private function maybeSnapshotVersion(array $note): void
    {
        $latest = $this->db->fetchOne('SELECT created_at, version_number FROM note_versions WHERE note_id = ? ORDER BY version_number DESC LIMIT 1', [$note['id']]);

        if ($latest && (time() - strtotime($latest['created_at'])) < 900) {
            return; // within the same ~15-minute editing session — already protected by the live autosave.
        }

        $this->db->insertInto('note_versions', [
            'note_id' => $note['id'],
            'content' => $note['content'],
            'version_number' => $latest ? (int) $latest['version_number'] + 1 : 1,
        ]);
    }

    private function extractFlashcardPairs(string $content, int $studentId): array
    {
        try {
            $result = AiGateway::complete(
                [['role' => 'user', 'content' => $content]],
                'Extract question/answer pairs from this student note that test understanding of a concept, not recall of an exact '
                    . 'sentence from the note. Skip anything too trivial to be useful as a review card. Reply with ONLY a JSON array '
                    . 'like [{"front":"...","back":"..."}], nothing else. Generate at most 8 pairs; if none are worth extracting, reply with [].',
                AiGateway::tierFor('notebook.flashcards'),
                'notebook_flashcards_v1'
            );
            $this->logUsage($studentId, 'notebook.flashcards', $result);
            $decoded = json_decode($this->extractJsonArray($result['content']), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function extractQuizQuestions(string $content, int $studentId): array
    {
        try {
            $result = AiGateway::complete(
                [['role' => 'user', 'content' => $content]],
                'Generate up to 5 multiple-choice practice questions from this student note. Reply with ONLY a JSON array like '
                    . '[{"question_text":"...","options":["A","B","C","D"],"correct_answer":"B"}], nothing else.',
                AiGateway::tierFor('notebook.quiz_generation'),
                'notebook_quiz_generation_v1'
            );
            $this->logUsage($studentId, 'notebook.quiz_generation', $result);
            $decoded = json_decode($this->extractJsonArray($result['content']), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Same quota/moderation gate as the Doubt Solver chat (05a's "every feature... relies on" requirement) — input precheck before any provider call. */
    private function requireQuotaAndModeration(int $studentId, string $content): void
    {
        if (Moderation::isBlocked($content)) {
            $this->fail(Moderation::fallbackMessage(), ['reason' => ['content_flagged']], 422);
        }
        if (! AiQuota::tryReserve($this->db, $studentId)) {
            $this->fail('Daily AI help limit reached.', ['reason' => 'quota_exhausted', 'resets_at' => AiQuota::resetsAt()], 429);
        }
    }

    private function logUsage(int $studentId, string $feature, array $gatewayResult): void
    {
        $costUsd = AiGateway::estimateCostUsd($gatewayResult['model'], $gatewayResult['tokens_input'], $gatewayResult['tokens_output']);
        AiUsageLog::record($this->db, $studentId, $feature, $gatewayResult, $costUsd);
        AiQuota::finalize($this->db, $studentId, $gatewayResult['tokens_input'] + $gatewayResult['tokens_output'], $costUsd);
    }

    private function extractJsonArray(string $text): string
    {
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        return ($start !== false && $end !== false) ? substr($text, $start, $end - $start + 1) : '[]';
    }

    private function formatNote(array $note): array
    {
        $note['is_favorite'] = (bool) $note['is_favorite'];
        $note['is_ai_generated'] = isset($note['is_ai_generated']) ? (bool) $note['is_ai_generated'] : null;
        $note['tags'] = isset($note['tags']) && $note['tags'] ? json_decode($note['tags'], true) : [];
        return $note;
    }

    private function ownNote(string $id): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $note = $this->db->fetchOne('SELECT * FROM notes WHERE id = ? AND student_id = ? AND deleted_at IS NULL', [$id, $studentId]);

        if (! $note) {
            $this->fail('No such note.', ['reason' => ['not_found']], 404);
        }

        return $note;
    }
}
