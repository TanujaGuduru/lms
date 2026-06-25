<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cost/token logging for AI Gateway calls that aren't part of an
 * `ai_conversations` thread — Notebook AI (summarize/flashcards/quiz-
 * generation, recording-to-notes). See `ai_usage_log`'s schema comment for
 * why this table exists: `ai_messages.conversation_id` is NOT NULL, and
 * these are one-shot utility calls with no conversation to attach to.
 * Without this, their spend would be invisible to AiGateway's platform-wide
 * daily circuit breaker.
 */
class AiUsageLog
{
    public static function record(Database $db, int $studentId, string $feature, array $gatewayResult, float $costUsd): void
    {
        $db->insertInto('ai_usage_log', [
            'student_id' => $studentId,
            'feature' => $feature,
            'model_used' => $gatewayResult['model'],
            'tokens_input' => $gatewayResult['tokens_input'],
            'tokens_output' => $gatewayResult['tokens_output'],
            'cost_usd' => $costUsd,
        ]);
    }
}
