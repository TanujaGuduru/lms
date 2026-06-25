<?php

declare(strict_types=1);

namespace App\Core;

/**
 * The one dispatch entry point — docs/student-module/06-communication-engine.md.
 * Every feature is meant to call `Notifier::send()` rather than hand-rolling
 * its own send logic, the same "one centralized abstraction" instinct
 * App\Core\AiGateway applies to LLM calls (06 §1), applied here to comms.
 *
 * **Real for `in_app` and `email`; explicitly not implemented for `whatsapp`
 * and `sms`.** WhatsApp Cloud API and SMS gateways (Twilio/MSG91/etc.) are
 * third-party cloud accounts requiring their own signup/credentials — not
 * the "an API key, no infrastructure" shape of this build's one accepted
 * cloud exception (see AiGateway's docblock), and not part of the GoDaddy
 * hosting package the way outbound SMTP to your own mailbox is. Rather than
 * fake a send that never happens, `dispatchChannel()` for those two channels
 * always returns a real, logged failure with a clear `failed_reason` — which
 * correctly and honestly exercises the fallback mechanic in §3 (a WhatsApp
 * attempt "fails," the next configured channel is genuinely tried), without
 * pretending a message was delivered when it wasn't.
 *
 * **Idempotency (06 §2)**: before any send, checks `communication_logs` for
 * ANY existing row — and `notification_queue` for any still-pending batched
 * occurrence — matching this exact `trigger_event` string for this user.
 * Per-occurrence triggers (e.g. one reminder per assignment, not just per
 * trigger *type*) disambiguate by appending `:{entityId}` to the
 * trigger_event string itself (e.g. `assignment_reminder_24h:482`) — the
 * naming convention 06 §2 describes ("distinguishable instances get their
 * own trigger_event string") — rather than needing a separate entity-id
 * column the schema doesn't have.
 */
class Notifier
{
    public static function send(int $userId, string $triggerEvent, array $context = [], ?string $batchKey = null): void
    {
        if (self::alreadyHandled($userId, $triggerEvent)) {
            return;
        }

        $base = self::baseTriggerEvent($triggerEvent);
        $config = self::channelConfig($base);

        if ($config['batchable']) {
            Database::getInstance()->insertInto('notification_queue', [
                'user_id' => $userId,
                'trigger_event' => $triggerEvent,
                'context' => json_encode($context),
                'batch_key' => $batchKey,
            ]);
            return;
        }

        self::dispatch($userId, $triggerEvent, $base, $context, $config);
    }

    /**
     * The actual send — called directly by send() for non-batchable
     * triggers, and by cron/process-notification-batches.php once a queued
     * batchable trigger (or a combined group of them) is ready to go out.
     */
    public static function dispatch(int $userId, string $triggerEvent, string $base, array $context, array $config): void
    {
        $user = Database::getInstance()->fetchOne('SELECT id, language, notification_preferences FROM users WHERE id = ?', [$userId]);
        if (! $user) {
            return;
        }
        $language = $user['language'] ?: 'en';
        $preferences = $user['notification_preferences'] ? json_decode($user['notification_preferences'], true) : [];

        // In-app is the floor every notification guarantees (06 §3) —
        // fires unconditionally, independent of the external channel
        // sequence below and never opted out of.
        self::dispatchChannel($userId, $triggerEvent, $base, 'in_app', $context, $language);

        $candidates = array_values(array_filter(
            $config['external_channels'],
            fn (string $channel) => ($preferences[$channel] ?? true) !== false
        ));

        if (empty($candidates)) {
            return;
        }

        if ($config['mode'] === 'all') {
            foreach ($candidates as $channel) {
                self::dispatchChannel($userId, $triggerEvent, $base, $channel, $context, $language);
            }
            return;
        }

        // 'fallback' — try in configured order, stop at the first success
        // (06 §3's "Fallback mechanics": a failure on the primary channel
        // triggers a retry on the next configured channel).
        foreach ($candidates as $channel) {
            if (self::dispatchChannel($userId, $triggerEvent, $base, $channel, $context, $language)['success']) {
                return;
            }
        }
    }

    /**
     * @return array{success: bool}
     */
    private static function dispatchChannel(int $userId, string $triggerEvent, string $base, string $channel, array $context, string $language): array
    {
        $template = self::templateFor($base, $channel, $language);
        $subject = self::render($template['subject'], $context);
        $body = self::render($template['body'], $context);

        $result = match ($channel) {
            'in_app' => self::sendInApp($userId, $subject, $body, $context),
            'email' => self::sendEmail($userId, $subject, $body),
            'whatsapp', 'sms' => ['success' => false, 'reason' => "{$channel} is not implemented in this build — requires a third-party cloud account (WhatsApp Cloud API / SMS gateway) outside this build's one accepted cloud exception (a pay-per-call AI API)."],
            default => ['success' => false, 'reason' => "Unknown channel: {$channel}"],
        };

        self::log($userId, $channel, $triggerEvent, $template['version'], $result);

        return $result;
    }

    private static function sendInApp(int $userId, string $title, string $message, array $context): array
    {
        Database::getInstance()->insertInto('notifications', [
            'user_id' => $userId,
            'type' => 'communication_engine',
            'title' => $title,
            'message' => $message,
            'data' => ! empty($context) ? json_encode($context) : null,
            'channel' => 'inapp',
            'status' => 'sent',
        ]);
        return ['success' => true, 'reason' => null];
    }

    private static function sendEmail(int $userId, string $subject, string $body): array
    {
        $user = Database::getInstance()->fetchOne('SELECT first_name, last_name, email FROM users WHERE id = ?', [$userId]);
        if (! $user || ! $user['email']) {
            return ['success' => false, 'reason' => 'No email address on file'];
        }

        $result = SimpleMailer::send($user['email'], trim("{$user['first_name']} {$user['last_name']}"), $subject, $body);
        return ['success' => $result['success'], 'reason' => $result['error']];
    }

    /**
     * Called by cron/process-notification-batches.php once a recipient (or
     * a family `batch_key` group, 06 §4's distinct "outer delivery wrapper"
     * case) has one or more queued batchable triggers ready to combine into
     * one consolidated send, rather than firing each independently.
     *
     * @param list<array<string, mixed>> $queuedRows rows from `notification_queue`, same recipient/batch_key group
     */
    public static function dispatchBatch(int $userId, array $queuedRows): void
    {
        $user = Database::getInstance()->fetchOne('SELECT language, notification_preferences FROM users WHERE id = ?', [$userId]);
        $language = ($user['language'] ?? null) ?: 'en';
        $preferences = ($user && $user['notification_preferences']) ? json_decode($user['notification_preferences'], true) : [];

        $lines = [];
        $externalChannels = [];
        foreach ($queuedRows as $row) {
            $base = self::baseTriggerEvent($row['trigger_event']);
            $context = $row['context'] ? json_decode($row['context'], true) : [];
            $lines[] = self::render(self::templateFor($base, 'in_app', $language)['body'], $context);

            foreach (self::channelConfig($base)['external_channels'] as $channel) {
                if (! in_array($channel, $externalChannels, true)) {
                    $externalChannels[] = $channel;
                }
            }
        }

        $firstBase = self::baseTriggerEvent($queuedRows[0]['trigger_event']);
        $firstContext = $queuedRows[0]['context'] ? json_decode($queuedRows[0]['context'], true) : [];
        $subject = count($queuedRows) > 1
            ? count($queuedRows) . ' updates for you'
            : self::render(self::templateFor($firstBase, 'in_app', $language)['subject'], $firstContext);
        $body = implode("\n\n", $lines);

        // in_app fires unconditionally, same floor-guarantee as a single send (06 §3).
        $inAppResult = self::sendInApp($userId, $subject, $body, []);
        foreach ($queuedRows as $row) {
            self::log($userId, 'in_app', $row['trigger_event'], 'combined_batch_in_app_v1', $inAppResult);
        }

        // External fallback chain across the UNION of every batched
        // trigger's configured channels — logged against the first queued
        // row's trigger_event only (one combined send, not N identical
        // ones) rather than duplicating the same outcome across every
        // original trigger, which in_app's per-row logging above already
        // covers for idempotency purposes.
        $candidates = array_values(array_filter($externalChannels, fn (string $c) => ($preferences[$c] ?? true) !== false));
        foreach ($candidates as $channel) {
            $result = $channel === 'email'
                ? self::sendEmail($userId, $subject, $body)
                : ['success' => false, 'reason' => "{$channel} is not implemented in this build — requires a third-party cloud account outside this build's one accepted cloud exception."];
            self::log($userId, $channel, $queuedRows[0]['trigger_event'], 'combined_batch_v1', $result);
            if ($result['success']) {
                break;
            }
        }
    }

    private static function log(int $userId, string $channel, string $triggerEvent, string $templateVersion, array $result): void
    {
        Database::getInstance()->insertInto('communication_logs', [
            'user_id' => $userId,
            'channel' => $channel,
            'trigger_event' => $triggerEvent,
            'template_used' => $templateVersion,
            'status' => $result['success'] ? 'sent' : 'failed',
            'failed_reason' => $result['reason'] ?? null,
            'sent_at' => $result['success'] ? date('Y-m-d H:i:s') : null,
        ]);
    }

    private static function alreadyHandled(int $userId, string $triggerEvent): bool
    {
        $db = Database::getInstance();
        if ($db->fetchOne('SELECT 1 FROM communication_logs WHERE user_id = ? AND trigger_event = ?', [$userId, $triggerEvent])) {
            return true;
        }
        return (bool) $db->fetchOne(
            "SELECT 1 FROM notification_queue WHERE user_id = ? AND trigger_event = ? AND status = 'pending'",
            [$userId, $triggerEvent]
        );
    }

    /** Strips a `:{entityId}` per-occurrence suffix (see this class's docblock) to find the trigger's config/template entry. */
    public static function baseTriggerEvent(string $triggerEvent): string
    {
        return strstr($triggerEvent, ':', true) ?: $triggerEvent;
    }

    private static function channelConfig(string $base): array
    {
        $catalog = require BASE_PATH . '/config/notification_channels.php';
        return $catalog[$base] ?? ['external_channels' => [], 'mode' => 'fallback', 'batchable' => false];
    }

    /** @return array{version: string, subject: string, body: string} */
    private static function templateFor(string $base, string $channel, string $language): array
    {
        $templates = require BASE_PATH . '/config/notification_templates.php';
        $forTrigger = $templates[$base][$channel] ?? $templates['_default'][$channel] ?? $templates['_default']['in_app'];
        return $forTrigger[$language] ?? $forTrigger['en'];
    }

    private static function render(string $template, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $template = str_replace('{' . $key . '}', (string) $value, $template);
            }
        }
        return $template;
    }
}
