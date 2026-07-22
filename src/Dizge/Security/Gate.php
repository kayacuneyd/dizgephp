<?php

declare(strict_types=1);

namespace Dizge\Security;

/** Explicit ability registry. Missing policies deny by default. */
final class Gate
{
    /** @var array<string, callable(Identity, mixed): bool> */
    private array $policies = [];

    /** @param callable(Identity, mixed): bool $policy */
    public function define(string $ability, callable $policy): void
    {
        $this->policies[$ability] = $policy;
    }

    public function allows(Identity $identity, string $ability, mixed $resource = null): bool
    {
        return isset($this->policies[$ability]) && ($this->policies[$ability])($identity, $resource) === true;
    }

    public function authorize(Identity $identity, string $ability, mixed $resource = null): void
    {
        if (!$this->allows($identity, $ability, $resource)) {
            throw new AuthorizationException(sprintf('Identity is not authorized for "%s".', $ability));
        }
    }
}
