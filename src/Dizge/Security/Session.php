<?php

declare(strict_types=1);

namespace Dizge\Security;

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
        return ($_SERVER['HTTPS'] ?? '') === 'on';
    }
}
