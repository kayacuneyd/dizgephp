<?php

declare(strict_types=1);

namespace Dizge\Security;

final class ApiToken
{
    /** @param list<string> $abilities */
    public function __construct(private string $subjectId, private array $abilities) {}
    public function subjectId(): string { return $this->subjectId; }
    public function allows(string $ability): bool { return in_array('*', $this->abilities, true) || in_array($ability, $this->abilities, true); }
    /** @return list<string> */ public function abilities(): array { return $this->abilities; }
}
