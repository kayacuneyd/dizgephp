<?php
declare(strict_types=1);
namespace Dizge\Security;
/** Coordinates generic failure messaging, throttling, password verification and session login. */
final class LoginService
{
    public function __construct(private UserProvider $users, private Auth $auth, private RateLimiter $limiter, private AuditLog $audit) {}
    public function attempt(string $identifier, string $password): bool
    {
        $key = 'login:' . strtolower(trim($identifier));
        $limit = $this->limiter->hit($key, 5, 300);
        if (!$limit->allowed()) { $this->audit->write('login.throttled', ['identifier' => hash('sha256', $key)]); return false; }
        $user = $this->users->findByIdentifier($identifier);
        // Verify a constant-cost dummy hash even for unknown users to reduce enumeration timing.
        $hash = $user?->passwordHash() ?? '$2y$10$Q9yR6fG.G4O8gJ9cqP0JVuOBcSmPCmL1YpsT33obGvzmv5Px7QjM.';
        if ($user === null || !password_verify($password, $hash)) { $this->audit->write('login.failed', ['identifier' => hash('sha256', $key)]); return false; }
        $this->auth->login($user->identity());
        $this->audit->write('login.succeeded', ['user_id' => (string) $user->id()]);
        return true;
    }
}
