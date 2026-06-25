<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hand-rolled SMTP client — same "no external library" instinct as
 * App\Core\SimplePdf, applied to email instead of PDF generation. Talks
 * directly to whatever's configured in config/mail.php — on GoDaddy, that's
 * the cPanel-provisioned mailbox already included in the hosting plan this
 * build is committed to (not a third-party cloud add-on; see AiGateway's
 * docblock for the one accepted cloud exception this build allows, which
 * SMTP-to-your-own-hosting-provider's-mail-server is not an instance of —
 * it's infrastructure that ships with the GoDaddy plan itself).
 *
 * Talks raw SMTP over `fsockopen()`, with an optional STARTTLS upgrade via
 * `stream_socket_enable_crypto()` — no PHPMailer/Swift dependency, matching
 * this codebase's existing pattern of vendoring nothing.
 */
class SimpleMailer
{
    /**
     * @return array{success: bool, error: ?string}
     */
    public static function send(string $toAddress, string $toName, string $subject, string $bodyText): array
    {
        $config = self::config();
        $clientHostname = $_SERVER['SERVER_NAME'] ?? 'localhost';

        try {
            $socket = self::connect($config);
            self::expect($socket, 220);

            self::command($socket, "EHLO {$clientHostname}", 250);

            if ($config['encryption'] === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (! stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS upgrade failed');
                }
                self::command($socket, "EHLO {$clientHostname}", 250);
            }

            if ($config['username'] !== '') {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($config['username']), 334);
                self::command($socket, base64_encode($config['password']), 235);
            }

            self::command($socket, "MAIL FROM:<{$config['from_address']}>", 250);
            self::command($socket, "RCPT TO:<{$toAddress}>", 250);
            self::command($socket, 'DATA', 354);

            $message = self::buildMessage($config, $toAddress, $toName, $subject, $bodyText);
            fwrite($socket, $message . "\r\n.\r\n");
            self::expect($socket, 250);

            self::command($socket, 'QUIT', 221);
            fclose($socket);

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            Logger::error('SimpleMailer send failed', ['to' => $toAddress, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return resource */
    private static function connect(array $config)
    {
        $transport = $config['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @fsockopen($transport . $config['host'], $config['port'], $errno, $errstr, $config['timeout_seconds']);

        if ($socket === false) {
            throw new \RuntimeException("Could not connect to {$config['host']}:{$config['port']} ({$errstr})");
        }
        stream_set_timeout($socket, $config['timeout_seconds']);

        return $socket;
    }

    /** @param resource $socket */
    private static function command($socket, string $line, int $expectedCode): void
    {
        fwrite($socket, $line . "\r\n");
        self::expect($socket, $expectedCode);
    }

    /** @param resource $socket */
    private static function expect($socket, int $expectedCode): void
    {
        $response = '';
        do {
            $line = fgets($socket, 512);
            if ($line === false) {
                throw new \RuntimeException('Connection closed unexpectedly while awaiting SMTP response');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-'); // multi-line response continues until "code " (space, not dash)

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("Unexpected SMTP response (wanted {$expectedCode}): {$response}");
        }
    }

    private static function buildMessage(array $config, string $toAddress, string $toName, string $subject, string $bodyText): string
    {
        $headers = [
            'From' => "{$config['from_name']} <{$config['from_address']}>",
            'To' => "{$toName} <{$toAddress}>",
            'Subject' => self::encodeHeader($subject),
            'Date' => date('r'),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }

        // Lines starting with a lone "." must be escaped per RFC 5321 §4.5.2 — otherwise SMTP's
        // end-of-DATA marker ("\r\n.\r\n") would be triggered early by body content.
        $escapedBody = preg_replace('/^\./m', '..', $bodyText);

        return implode("\r\n", $lines) . "\r\n\r\n" . $escapedBody;
    }

    private static function encodeHeader(string $value): string
    {
        // Plain ASCII subjects pass through untouched; anything outside that
        // range gets RFC 2047 base64 encoding rather than risking a raw
        // non-ASCII byte in a header most servers expect to be ASCII-only.
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function config(): array
    {
        return require BASE_PATH . '/config/mail.php';
    }
}
