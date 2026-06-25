<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Safety/moderation — docs/student-module/05a §5. The doc's design is two
 * LLM-backed passes (a cheap precheck before the main call, plus per-chunk
 * output moderation running *during* streaming). Two adaptations, both
 * driven by decisions already made earlier in this build:
 *  - **No streaming exists at all** (AiGateway's docblock: shared hosting's
 *    output buffering isn't reliable enough to promise a real token-by-
 *    token stream), so "moderate the rolling output buffer mid-stream" has
 *    nothing to attach to — output moderation here just checks the
 *    complete response before it's returned, which is simpler precisely
 *    because there's no stream to abort mid-flight.
 *  - **The input precheck is rule-based, not a second paid LLM call.**
 *    Spending a full provider call just to decide whether to spend a
 *    second one would double the cost of every single AI interaction in
 *    this build — the opposite of the cost-discipline this whole no-cloud
 *    project has otherwise held to. A keyword/pattern check catches the
 *    obvious cases for free; a safety instruction folded into every system
 *    prompt (see AiSystemPrompt-building call sites) covers nuance the
 *    keyword list can't, at zero extra cost since it rides on the call
 *    that was happening anyway.
 */
class Moderation
{
    /** Deliberately small and blunt — catches the obvious cases for free; nuance is the system-prompt instruction's job, not this list's. */
    private const BLOCKED_PATTERNS = [
        '/\bsuicide\b/i', '/\bself[\s-]?harm\b/i', '/\bkill myself\b/i',
        '/\bporn(ography)?\b/i', '/\bnude\b/i', '/\bsex(ual)?\b/i',
        '/\bhate speech\b/i', '/\bbuild a (bomb|weapon)\b/i',
    ];

    public const SAFETY_INSTRUCTION = 'If the student asks something unrelated to learning, or inappropriate, '
        . 'unsafe, or harmful for a school context, do not answer it — instead reply with a brief, '
        . 'age-appropriate redirect back to the lesson at hand.';

    public static function isBlocked(string $text): bool
    {
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Output check on the complete (non-streamed) response, before it's
     * returned to the student — the same blocklist, applied defensively to
     * what the model produced, not just what the student sent in.
     */
    public static function isResponseSafe(string $responseText): bool
    {
        return ! self::isBlocked($responseText);
    }

    public static function fallbackMessage(): string
    {
        return "Let's keep our conversation focused on your coursework — try rephrasing your question about the lesson.";
    }
}
