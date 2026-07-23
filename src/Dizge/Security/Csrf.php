<?php

declare(strict_types=1);

namespace Dizge\Security;

/** Synchronizer-token CSRF protection for cookie-backed browser sessions. */
final class Csrf
{
    private const SESSION_KEY = '_dizge_csrf';

    public function __construct(private Session $session) {}

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (is_string($token) && strlen($token) === 64) return $token;
        $token = bin2hex(random_bytes(32));
        $this->session->put(self::SESSION_KEY, $token);
        return $token;
    }

    public function verify(?string $candidate): bool
    {
        return is_string($candidate) && hash_equals($this->token(), $candidate);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . \e($this->token()) . '">';
    }
}
