<?php
declare(strict_types=1);
namespace Dizge\Security;
final class UserRecord
{
    /** @param array<string, scalar|array<scalar>|null> $attributes */
    public function __construct(private string|int $id, private string $passwordHash, private array $attributes = []) {}
    public function id(): string|int { return $this->id; }
    public function passwordHash(): string { return $this->passwordHash; }
    public function identity(): Identity { return new Identity($this->id, $this->attributes); }
}
