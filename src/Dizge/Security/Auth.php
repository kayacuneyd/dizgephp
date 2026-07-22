<?php

declare(strict_types=1);

namespace Dizge\Security;

/** Explicit login/logout state. Credential lookup and password verification remain application code. */
final class Auth
{
    private const SESSION_KEY = '_dizge_identity';

    public function __construct(private Session $session) {}

    public function login(Identity $identity): void
    {
        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, $identity->toSession());
    }

    public function user(): ?Identity
    {
        return Identity::fromSession($this->session->get(self::SESSION_KEY));
    }

    public function check(): bool { return $this->user() !== null; }

    public function requireUser(): Identity
    {
        return $this->user() ?? throw new AuthenticationException('Authentication is required.');
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();
    }
}
