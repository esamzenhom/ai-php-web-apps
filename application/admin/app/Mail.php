<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Tiny SMTP mailer — sends email through any SMTP provider (Postmark,
 * Amazon SES, SendGrid, Gmail, …) configured via MAIL_* in .env. No
 * Composer dependency.
 *
 *   \App\Mail::send('them@example.com', 'Subject', 'Hello!');
 *   \App\Mail::send($to, $subject, '<p>Hi</p>', html: true);
 *
 * Leave MAIL_HOST blank to disable email (send() then throws a clear error).
 */
final class Mail
{
    public static function send(string $to, string $subject, string $body, bool $html = false): void
    {
        $host = getenv('MAIL_HOST') ?: '';
        if ($host === '') {
            throw new RuntimeException('Email is not configured — set MAIL_HOST in .env.');
        }
        $port = (int) (getenv('MAIL_PORT') ?: 587);
        $user = getenv('MAIL_USER') ?: '';
        $pass = getenv('MAIL_PASSWORD') ?: '';
        $enc  = strtolower(getenv('MAIL_ENCRYPTION') ?: 'tls');
        $from = getenv('MAIL_FROM') ?: ('no-reply@' . $host);
        $fromName = getenv('MAIL_FROM_NAME') ?: 'App';

        $endpoint = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($endpoint, $errno, $errstr, 20);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 20);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break; // last line of a (possibly multi-line) reply
                }
            }
            return $data;
        };
        $expect = static function (string $resp, string $code, string $stage): void {
            if (strncmp($resp, $code, strlen($code)) !== 0) {
                throw new RuntimeException("SMTP {$stage} failed: " . trim($resp));
            }
        };
        $cmd = static function (string $c) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            return $read();
        };

        $expect($read(), '220', 'greeting');
        $expect($cmd('EHLO localhost'), '250', 'EHLO');

        if ($enc === 'tls') {
            $expect($cmd('STARTTLS'), '220', 'STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS negotiation failed');
            }
            $expect($cmd('EHLO localhost'), '250', 'EHLO(tls)');
        }

        if ($user !== '') {
            $expect($cmd('AUTH LOGIN'), '334', 'AUTH');
            $expect($cmd(base64_encode($user)), '334', 'AUTH user');
            $expect($cmd(base64_encode($pass)), '235', 'AUTH pass');
        }

        $expect($cmd("MAIL FROM:<{$from}>"), '250', 'MAIL FROM');
        $expect($cmd("RCPT TO:<{$to}>"), '25', 'RCPT TO');   // 250 or 251
        $expect($cmd('DATA'), '354', 'DATA');

        $headers = [
            'From: ' . self::encode($fromName) . " <{$from}>",
            "To: <{$to}>",
            'Subject: ' . self::encode($subject),
            'MIME-Version: 1.0',
            'Content-Type: ' . ($html ? 'text/html' : 'text/plain') . '; charset=UTF-8',
            'Date: ' . date('r'),
        ];
        // Normalise line endings and dot-stuff lines starting with "."
        $bodyOut = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body));
        $expect($cmd(implode("\r\n", $headers) . "\r\n\r\n" . $bodyOut . "\r\n."), '250', 'send');
        $cmd('QUIT');
        fclose($fp);
    }

    /** RFC 2047-encode a header value only if it contains non-ASCII. */
    private static function encode(string $s): string
    {
        return preg_match('/[\x80-\xff]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
