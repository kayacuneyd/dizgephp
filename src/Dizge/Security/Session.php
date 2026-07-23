<?php

declare(strict_types=1);

namespace Dizge\Security;

use Dizge\Http\TrustedProxy;

/** Native PHP session wrapper with strict IDs, HttpOnly cookies and SameSite=Lax defaults. */
final class Session
{
    public function __construct(private string $name = 'dizge_session') {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->secureCookie(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (!session_start()) throw new \RuntimeException('Unable to start the session.');
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $fallback;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        if (!session_regenerate_id(true)) throw new \RuntimeException('Unable to regenerate the session id.');
    }

    private function secureCookie(): bool
    {
        $configured = getenv('DIZGE_SESSION_SECURE');
        if ($configured !== false && $configured !== '') return $configured !== '0';
        if (($_SERVER['HTTPS'] ?? '') === 'on') return true;
        return $this->behindTrustedHttpsProxy();
    }

    /**
     * Behind a TLS-terminating reverse proxy $_SERVER['HTTPS'] is empty, so without this the
     * Secure cookie flag would silently never be set unless an operator remembers
     * DIZGE_SESSION_SECURE. Only trusted when REMOTE_ADDR is an explicitly configured proxy
     * (DIZGE_TRUSTED_PROXIES) — a request can never claim its own X-Forwarded-Proto.
     */
    private function behindTrustedHttpsProxy(): bool
    {
        $trusted = TrustedProxy::fromEnv();
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($trusted === [] || !is_string($remoteAddr) || !TrustedProxy::isTrusted($remoteAddr, $trusted)) return false;
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
