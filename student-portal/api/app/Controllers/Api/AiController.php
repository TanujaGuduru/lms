<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\AiGateway;
use App\Core\AiQuota;
use App\Core\Controller;
use App\Core\Moderation;
use App\Core\Request;

/**
 * AI Doubt Solver & AI Coding Assistant —
 * docs/student-module/04d-apis-assignments-sandbox-ai.md, cross-cutting
 * plumbing per 05a (App\Core\AiGateway/AiQuota/Moderation), prompt design
 * per 05b. Adapted for the no-cloud decision (see AiGateway's docblock for
 * what's deliberately not implemented and why: no streaming, no
 * RAG/Pinecone — so `buildSystemPrompt()`'s layers below skip 05b §1's RAG
 * context layer entirely, there's nothing to retrieve). Three more
 * simplifications specific to this controller:
 *  - **No sandbox-verification loop on debug/review/escalate.** 05b §3
 *    describes this as "the one place that needs a concrete mechanism, not
 *    just careful prompting" — propose a fix, run it against a throwaway
 *    workspace copy, confirm or hedge based on the real outcome. There is
 *    no sandbox in this build at all (no live code execution, by
 *    deliberate choice), so there's nothing to run the proposed fix
 *    against. Every Coding Assistant response is delivered with the same
 *    appropriate-uncertainty framing baked into the prompt itself instead
 *    (see the `debug`/`review` instructions below) — never a confident
 *    "verified" claim this build has no way to actually verify.
 *  - **Coding Assistant context is the student's pasted code/error, not
 *    live `workspace_files`** — 05b §3 describes bundling every file in
 *    the workspace; with no workspace/sandbox at all, there's nothing to
 *    bundle. The student's message (pasted code + error) is the only
 *    context this mode has, the same as conversation history for every
 *    other mode.
 *  - **Near-duplicate detection is a local text-similarity heuristic**
 *    (`similar_text()`), not embedding similarity — there's no vector DB of
 *    any kind in this build. Cheaper and cruder, but real and running, not
 *    a stub.
 */
class AiController extends Controller
{
    private const NEAR_DUPLICATE_THRESHOLD_PERCENT = 70.0;
    private const NEAR_DUPLICATE_COUNT_TO_FLAG = 3;

    public function index(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $type = (string) $request->input('type', '');

        $sql = 'SELECT id, conversation_type, mode, linked_course_id, linked_lesson_id, started_at, last_message_at
                FROM ai_conversations WHERE student_id = ?';
        $params = [$studentId];
        if (in_array($type, ['doubt_solver', 'coding_assistant'], true)) {
            $sql .= ' AND conversation_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY last_message_at DESC';

        $this->success($this->db->select($sql, $params));
    }

    public function create(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $type = (string) $request->input('conversation_type', '');
        $mode = (string) $request->input('mode', $type === 'coding_assistant' ? 'debug' : 'hint');

        $validModesByType = [
            'doubt_solver' => ['hint', 'explain', 'practice'],
            'coding_assistant' => ['debug', 'review'],
        ];

        if (! isset($validModesByType[$type]) || ! in_array($mode, $validModesByType[$type], true)) {
            $this->fail('A valid conversation_type and mode are required.', [
                'conversation_type' => ['required|in:doubt_solver,coding_assistant'],
            ]);
        }

        $id = $this->db->insertInto('ai_conversations', [
            'student_id' => $studentId,
            'conversation_type' => $type,
            'mode' => $mode,
            'linked_course_id' => $request->input('linked_course_id') ?: null,
            'linked_lesson_id' => $request->input('linked_lesson_id') ?: null,
            'language' => (string) $request->input('language', 'en'),
        ]);

        $this->success(['id' => (int) $id, 'conversation_type' => $type, 'mode' => $mode], [], 201);
    }

    public function messages(Request $request, string $id): void
    {
        $conversation = $this->ownConversation($id);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));

        $result = $this->db->paginate(
            "SELECT id, role, content, model_used, tokens_input, tokens_output, cost_usd, created_at
             FROM ai_messages WHERE conversation_id = ? ORDER BY created_at",
            [$conversation['id']],
            $page,
            $perPage
        );

        $this->success($result['data'], [
            'current_page' => $result['current_page'],
            'per_page' => $result['per_page'],
            'total' => $result['total'],
            'last_page' => $result['last_page'],
        ]);
    }

    public function sendMessage(Request $request, string $id): void
    {
        $conversation = $this->ownConversation($id);
        $content = trim((string) $request->input('content', ''));

        if (! $content) {
            $this->fail('content is required.', ['content' => ['required']]);
        }
        // Checked before any model call — a length/line-count threshold,
        // not a request to the model to ask for narrowing itself. Zero
        // tokens spent on a canned response (05b §3's explicit point: cheap
        // and reliable beats hoping the model reliably asks first).
        if (mb_strlen($content) > 4000) {
            $message = $conversation['conversation_type'] === 'coding_assistant'
                ? "That's a lot of code at once — let's focus on one function or file so I can actually help."
                : "Let's focus on one question at a time — try narrowing this down.";
            $this->fail($message, ['content' => ['max:4000']], 422);
        }

        // Input precheck (05a §5) — before any quota is spent or provider call made.
        if (Moderation::isBlocked($content)) {
            $this->fail(Moderation::fallbackMessage(), ['reason' => ['content_flagged']], 422);
        }

        if (! AiQuota::tryReserve($this->db, (int) $conversation['student_id'])) {
            $this->fail('Daily AI help limit reached.', ['reason' => 'quota_exhausted', 'resets_at' => AiQuota::resetsAt()], 429);
        }
        $this->checkNearDuplicate((int) $conversation['id'], $content);

        $promptKey = "{$conversation['conversation_type']}_{$conversation['mode']}_v1";
        $tier = AiGateway::tierFor("{$conversation['conversation_type']}.{$conversation['mode']}");
        $systemPrompt = $this->buildSystemPrompt($conversation, escalate: false);
        $history = $this->recentHistory((int) $conversation['id']);
        $history[] = ['role' => 'user', 'content' => $content];

        $this->db->insertInto('ai_messages', [
            'conversation_id' => $conversation['id'],
            'role' => 'user',
            'content' => $content,
        ]);

        $result = $this->callAiAndStore($conversation, $history, $systemPrompt, $tier, $promptKey);

        $this->success($result);
    }

    /**
     * "Show me the fix" — a genuinely different, more permissive prompt
     * (no "don't give the answer" instruction), not a flag inside the same
     * prompt asking the model to police itself. Logged like any other
     * message, visible on the conversation transcript.
     */
    public function escalate(Request $request, string $id): void
    {
        $conversation = $this->ownConversation($id);

        if (! AiQuota::tryReserve($this->db, (int) $conversation['student_id'])) {
            $this->fail('Daily AI help limit reached.', ['reason' => 'quota_exhausted', 'resets_at' => AiQuota::resetsAt()], 429);
        }

        $escalationNote = '[Student escalated: asked to be shown the direct answer/fix.]';
        $this->db->insertInto('ai_messages', [
            'conversation_id' => $conversation['id'],
            'role' => 'user',
            'content' => $escalationNote,
        ]);

        $systemPrompt = $this->buildSystemPrompt($conversation, escalate: true);
        $history = $this->recentHistory((int) $conversation['id']);
        $promptKey = "{$conversation['conversation_type']}_escalate_v1";
        $tier = AiGateway::tierFor("{$conversation['conversation_type']}.escalate");

        $result = $this->callAiAndStore($conversation, $history, $systemPrompt, $tier, $promptKey);

        $this->success($result);
    }

    public function quota(Request $request): void
    {
        $studentId = (int) $this->currentUser()['id'];
        $limit = require BASE_PATH . '/config/ai.php';
        $row = AiQuota::currentRow($this->db, $studentId);

        $this->success([
            'period' => 'daily',
            'messages_used' => (int) ($row['messages_used'] ?? 0),
            'quota_limit_messages' => $limit['daily_message_limit'],
            'resets_at' => AiQuota::resetsAt(),
        ]);
    }

    private function callAiAndStore(array $conversation, array $history, string $systemPrompt, string $tier, string $promptKey): array
    {
        try {
            $result = AiGateway::complete($history, $systemPrompt, $tier, $promptKey);
        } catch (\Throwable $e) {
            $this->fail('The AI assistant is temporarily unavailable. Please try again shortly.', ['reason' => ['ai_gateway_error']], 503);
        }

        // Output check (05a §5) — on the complete response, before it's
        // returned or persisted as the model's real answer.
        $responseContent = Moderation::isResponseSafe($result['content']) ? $result['content'] : Moderation::fallbackMessage();

        $costUsd = AiGateway::estimateCostUsd($result['model'], $result['tokens_input'], $result['tokens_output']);

        $messageId = $this->db->insertInto('ai_messages', [
            'conversation_id' => $conversation['id'],
            'role' => 'assistant',
            'content' => $responseContent,
            'model_used' => $result['model'],
            'tokens_input' => $result['tokens_input'],
            'tokens_output' => $result['tokens_output'],
            'cost_usd' => $costUsd,
            'latency_ms' => $result['latency_ms'],
        ]);

        $this->db->updateTable('ai_conversations', ['last_message_at' => date('Y-m-d H:i:s')], 'id = ?', [$conversation['id']]);

        AiQuota::finalize($this->db, (int) $conversation['student_id'], $result['tokens_input'] + $result['tokens_output'], $costUsd);

        return [
            'message_id' => (int) $messageId,
            'content' => $responseContent,
            'tokens_output' => $result['tokens_output'],
            'cost_usd' => round($costUsd, 6),
        ];
    }

    /**
     * Cheap and local: compares the new question against this conversation's
     * last few user messages with similar_text(). Three+ matches above the
     * threshold flags the conversation for mentor review — silently; the
     * student's response is completely unaffected (03e's explicit point:
     * a quiet escalation, not a visible block).
     */
    private function checkNearDuplicate(int $conversationId, string $newContent): void
    {
        $recentUserMessages = $this->db->select(
            "SELECT content FROM ai_messages WHERE conversation_id = ? AND role = 'user' ORDER BY id DESC LIMIT 5",
            [$conversationId]
        );

        $matches = 0;
        foreach ($recentUserMessages as $row) {
            similar_text(mb_strtolower($newContent), mb_strtolower($row['content']), $percent);
            if ($percent >= self::NEAR_DUPLICATE_THRESHOLD_PERCENT) {
                $matches++;
            }
        }

        if ($matches >= self::NEAR_DUPLICATE_COUNT_TO_FLAG) {
            $this->db->updateTable('ai_conversations', [
                'flagged_for_review' => 1,
                'flagged_reason' => 'repeated_near_duplicate_question',
            ], 'id = ? AND flagged_for_review = 0', [$conversationId]);
        }
    }

    /**
     * Assembled from composable layers per 05b §1, rather than one hand-
     * written mega-string — RAG context is the one documented layer
     * skipped entirely (no vector DB in this build, see class docblock).
     */
    private function buildSystemPrompt(array $conversation, bool $escalate): string
    {
        $layers = [
            $this->baseLayer($conversation),
            $escalate ? $this->escalationLayer() : $this->modeLayer($conversation),
        ];

        return implode(' ', array_filter($layers));
    }

    /** Always present: persona/safety + the core directive (05b §1.1) — everything else swaps in around this. */
    private function baseLayer(array $conversation): string
    {
        $context = $this->contextLine($conversation);
        return "You are CodeGurukul's tutor for a student{$context}. "
            . 'You teach; you do not hand over direct answers unless explicitly told you may. '
            . 'Keep responses concise, age-appropriate, and encouraging. '
            . Moderation::SAFETY_INSTRUCTION;
    }

    /**
     * The model is never told about escalation mechanics or near-duplicate
     * detection in its own context — staying firm and routing to the
     * explicit escalate() endpoint is an application-level decision, not
     * something this prompt asks the model to self-police (05b §2's
     * explicit reasoning: LLMs are unreliable at correctly applying their
     * own conditional "give in after N tries" logic).
     */
    private function modeLayer(array $conversation): string
    {
        return match ($conversation['mode']) {
            'hint' => "Do NOT give the direct answer or corrected code. Point at the relevant concept or ask a guiding question instead. If asked directly for the answer, redirect with a guiding question once. If asked again after that in this same conversation, hold the same line — do not give in.",
            'explain' => 'Give a full, clear conceptual explanation. Use a simple analogy if it helps. If this connects to a specific lesson, reference it by name at a concept level, never by inventing or quoting exact lesson text you were not given.',
            'practice' => 'Generate an analogous practice problem — a different scenario and different numbers from anything the student has shown you, testing the same underlying concept, never a thin reskin of their exact problem. Include a short starter-code stub if it helps. Do not include the answer.',
            'debug' => "Lead with *why* something is wrong and a guiding question, not a corrected code block — unless the student has explicitly escalated to 'show me the fix'. You cannot run code to verify a fix in this conversation, so phrase any suggested fix as something to try, not a confirmed solution.",
            'review' => 'Review the code for style/structure/best-practice issues, not just correctness bugs. Explain why something is a smell rather than just rewriting it.',
            default => '',
        };
    }

    /**
     * A genuinely different prompt variant, not a flag inside the mode
     * layer asking the model to honor it (05b §4's explicit reasoning,
     * same as §2's) — the redirect-to-a-guiding-question instruction is
     * simply absent here, not present-but-overridden.
     */
    private function escalationLayer(): string
    {
        return 'The student has explicitly asked to be shown the direct answer or fix this time — you may give it directly, clearly explained.';
    }

    private function contextLine(array $conversation): string
    {
        if (! $conversation['linked_lesson_id']) {
            return '';
        }
        $lesson = $this->db->fetchOne('SELECT title FROM lessons WHERE id = ?', [$conversation['linked_lesson_id']]);
        return $lesson ? " currently studying \"{$lesson['title']}\"" : '';
    }

    /** Last 10 turns, not the full history — keeps token cost bounded regardless of conversation length. */
    private function recentHistory(int $conversationId): array
    {
        $rows = $this->db->select(
            "SELECT role, content FROM ai_messages WHERE conversation_id = ? AND role IN ('user','assistant') ORDER BY id DESC LIMIT 10",
            [$conversationId]
        );
        return array_reverse(array_map(fn (array $r) => ['role' => $r['role'], 'content' => $r['content']], $rows));
    }

    private function ownConversation(string $id): array
    {
        $studentId = (int) $this->currentUser()['id'];
        $conversation = $this->db->fetchOne('SELECT * FROM ai_conversations WHERE id = ? AND student_id = ?', [$id, $studentId]);

        if (! $conversation) {
            $this->fail('No such conversation.', ['reason' => ['not_found']], 404);
        }

        return $conversation;
    }
}
