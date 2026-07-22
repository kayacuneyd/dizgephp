<?php

declare(strict_types=1);

namespace Dizge\Security;

final class RateLimit
{
    public function __construct(private bool $allowed, private int $remaining, private int $retryAfter) {}
    public function allowed(): bool { return $this->allowed; }
    public function remaining(): int { return $this->remaining; }
    public function retryAfter(): int { return $this->retryAfter; }
}
