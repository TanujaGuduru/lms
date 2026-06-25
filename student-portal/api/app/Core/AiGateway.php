<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The one accepted external dependency in this entire platform — a direct,
 * pay-per-call HTTPS request to Anthropic's API. There is no way to run a
 * capable AI model on shared PHP hosting itself, so this is the floor, not
 * a cloud-infrastructure choice: a plain API call with a key, no account
 * dashboard to configure, no server of any kind to manage. Uses PHP's
 * built-in cURL extension directly — no Anthropic SDK, matching this app's
 * no-Composer-dependencies-at-runtime convention.
 *
 * docs/student-module/05a describes this as "the Gateway" — the one place
 * provider calls, quota, cost accounting, and now safety/circuit-breaking
 * are centralized, rather than re-implemented per feature. Two of 05a's
 * mechanisms are centralized here directly:
 *  - **Platform-wide daily spend circuit breaker** (05a §4) — beyond any
 *    individual student's quota (App\Core\AiQuota), a configured daily
 *    total-spend cap protects against a runaway bug (an infinite retry
 *    loop, a prompt that somehow generates an oversized response
 *    repeatedly). 05a says this "pages ops if approached" — with no
 *    alerting channel in this build, a critical log line is the honest
 *    equivalent; past the cap, calls are actually refused (a log nobody
 *    is paged for isn't real protection).
 *  - **One retry against the same route** (05a §3's "Failover", scoped
 *    down: there's no second provider/Bedrock route in this build to fail
 *    over to, only the one accepted API) — a transient network failure or
 *    a 5xx from the provider gets retried once before surfacing to the
 *    caller; a 4xx (bad request, auth failure) never is, since retrying an
 *    error that will deterministically repeat wastes a call for nothing.
 *
 * Deliberately NOT implemented, each for a stated reason:
 *  - Streaming (SSE) — shared hosting (mod_php/FastCGI) buffers output
 *    unreliably enough that a flaky stream is worse than a plain
 *    request/response with a short wait. A real, accepted latency
 *    tradeoff, not a missing feature.
 *  - RAG over course content — the original design needed Pinecone (a
 *    cloud vector DB); with no cloud service of any kind, there is no
 *    course-content retrieval at all in this pass. The system prompt
 *    instead just states the course/lesson context by name, accepting that
 *    the model speaks from its own general knowledge of the subject, not
 *    this platform's specific lesson content.
 */
class AiGateway
{
    public static function complete(array $messages, string $systemPrompt, string $tier = 'fast', ?string $promptKey = null): array
    {
        $config = self::config();
        $model = $tier === 'deep' ? $config['deep_model'] : $config['fast_model'];

        if (! $config['api_key']) {
            throw new \RuntimeException('AI_API_KEY is not configured.');
        }

        self::enforceDailySpendCap($config);

        $response = self::requestWithRetry($config, $model, $systemPrompt, $messages);

        $text = '';
        foreach ($response['decoded']['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }

        return [
            'content' => $text,
            // 05a §1's "prompt versioning" — a composite like
            // claude-sonnet@doubt_solver_hint_v1, so "did response quality
            // change?" can be answered against which prompt version was
            // active, not just which model.
            'model' => $promptKey ? "{$model}@{$promptKey}" : $model,
            'tokens_input' => (int) ($response['decoded']['usage']['input_tokens'] ?? 0),
            'tokens_output' => (int) ($response['decoded']['usage']['output_tokens'] ?? 0),
            'latency_ms' => $response['latency_ms'],
        ];
    }

    private static function requestWithRetry(array $config, string $model, string $systemPrompt, array $messages): array
    {
        $payload = json_encode([
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => $messages,
        ]);

        $attempt = self::request($config, $payload);

        if ($attempt['transportFailed'] || $attempt['httpCode'] >= 500) {
            Logger::error('AI Gateway attempt failed, retrying once', [
                'http_code' => $attempt['httpCode'],
                'curl_error' => $attempt['curlError'],
            ]);
            $attempt = self::request($config, $payload);
        }

        if ($attempt['transportFailed']) {
            throw new \RuntimeException("AI Gateway request failed: {$attempt['curlError']}");
        }
        if ($attempt['httpCode'] >= 400) {
            $message = $attempt['decoded']['error']['message'] ?? 'Unknown error';
            throw new \RuntimeException("AI provider returned {$attempt['httpCode']}: {$message}");
        }

        return $attempt;
    }

    private static function request(array $config, string $payload): array
    {
        $ch = curl_init($config['base_url'] . '/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $config['api_key'],
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $start = microtime(true);
        $response = curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $start) * 1000);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'transportFailed' => $response === false,
            'decoded' => $response !== false ? json_decode($response, true) : null,
            'httpCode' => $httpCode,
            'curlError' => $curlError,
            'latency_ms' => $latencyMs,
        ];
    }

    /** Refuses further calls once today's total spend crosses the configured cap — a log nobody's paged for isn't real protection against a runaway bug. */
    private static function enforceDailySpendCap(array $config): void
    {
        if ($config['daily_spend_cap_usd'] <= 0) {
            return; // not configured — no cap enforced.
        }

        $db = Database::getInstance();
        // Sums both tables — conversational chat (ai_messages) and one-shot
        // utility calls with no conversation to attach to (ai_usage_log,
        // see its schema comment). Missing either would silently undercount
        // real platform spend.
        $spentToday = (float) ($db->fetchOne(
            "SELECT
                (SELECT COALESCE(SUM(cost_usd), 0) FROM ai_messages WHERE DATE(created_at) = CURDATE())
                + (SELECT COALESCE(SUM(cost_usd), 0) FROM ai_usage_log WHERE DATE(created_at) = CURDATE())
             AS total"
        )['total'] ?? 0);

        if ($spentToday >= $config['daily_spend_cap_usd']) {
            Logger::critical('AI daily spend cap reached — refusing further calls', [
                'spent_today' => $spentToday,
                'cap' => $config['daily_spend_cap_usd'],
            ]);
            throw new \RuntimeException('Daily AI spend cap reached platform-wide.');
        }
    }

    /** config/ai_tiers.php's {feature}.{mode} routing table — a tier is config, not a per-call decision scattered across controllers (05a §3). */
    public static function tierFor(string $featureMode): string
    {
        $tiers = require BASE_PATH . '/config/ai_tiers.php';
        return $tiers[$featureMode] ?? 'fast';
    }

    /** Anthropic's published per-token rates for the configured models, in USD. Update if pricing changes. */
    public static function estimateCostUsd(string $model, int $tokensInput, int $tokensOutput): float
    {
        // $model may be a "model@prompt_key" composite (see complete()) — rates are keyed on the bare model name.
        $bareModel = explode('@', $model, 2)[0];
        $rates = self::config()['rates'][$bareModel] ?? ['input' => 0.0, 'output' => 0.0];
        return ($tokensInput / 1_000_000 * $rates['input']) + ($tokensOutput / 1_000_000 * $rates['output']);
    }

    private static function config(): array
    {
        return require BASE_PATH . '/config/ai.php';
    }
}
