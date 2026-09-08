<?php
declare(strict_types=1);

namespace App;

/**
 * Security helpers — the safe, turnkey way to handle output, CSRF,
 * sessions, and passwords. See .agents/skills/app-security/SKILL.md
 * for when and how to use each one.
 */
final class Security
{
    // ── Output escaping (XSS) ────────────────────────────────
    /** Escape a value before printing it into HTML. */
    public static function esc(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ── Sessions ─────────────────────────────────────────────
    /** Start a hardened session (httponly + SameSite, Secure on HTTPS). */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        // Detect HTTPS, including when behind the edge proxy (which
        // terminates TLS and forwards X-Forwarded-Proto: https).
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ]);
        session_start();
    }

    // ── CSRF protection ──────────────────────────────────────
    /** Get (or lazily create) the per-session CSRF token. */
    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Hidden form field to drop inside any state-changing <form>. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . self::esc(self::csrfToken()) . '">';
    }

    /** Verify a submitted CSRF token (constant-time). */
    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    // ── Passwords ────────────────────────────────────────────
    /** Hash a plaintext password for storage. */
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /** Check a plaintext password against a stored hash. */
    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
