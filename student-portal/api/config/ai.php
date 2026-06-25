<?php

// The one external API this platform calls — see App\Core\AiGateway's
// docblock for why this is the accepted exception to "no cloud service of
// any kind." AI_BASE_URL is overridable specifically so this can be pointed
// at a local mock during testing without touching any code.
return [
    'api_key' => $_ENV['AI_API_KEY'] ?? '',
    'base_url' => $_ENV['AI_BASE_URL'] ?? 'https://api.anthropic.com',
    'fast_model' => $_ENV['AI_FAST_MODEL'] ?? 'claude-haiku-4-5-20251001',
    'deep_model' => $_ENV['AI_DEEP_MODEL'] ?? 'claude-sonnet-4-6',

    // Per-million-token USD rates, used only for the cost_usd estimate
    // logged on ai_messages — update if Anthropic's published pricing
    // changes; this is config, not something to hardcode into AiGateway.
    'rates' => [
        'claude-haiku-4-5-20251001' => ['input' => 1.00, 'output' => 5.00],
        'claude-sonnet-4-6' => ['input' => 3.00, 'output' => 15.00],
    ],

    // Daily message cap per student — shared across doubt_solver and
    // coding_assistant by default; split into separate pools later by
    // keying the quota query on conversation_type too if ever needed
    // (ai_usage_quotas is already structured to support either, per 03e).
    'daily_message_limit' => (int) ($_ENV['AI_DAILY_MESSAGE_LIMIT'] ?? 15),

    // Platform-wide circuit breaker (05a §4) — protects against a runaway
    // bug (an infinite retry loop, a repeatedly-oversized response), not
    // individual overuse, which per-student quotas above don't catch.
    // 0 = disabled (no cap enforced).
    'daily_spend_cap_usd' => (float) ($_ENV['AI_DAILY_SPEND_CAP_USD'] ?? 10.00),
];
