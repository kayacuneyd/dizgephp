<?php

declare(strict_types=1);

namespace Dizge\Security;

/** Authenticated principal stored as scalar id plus non-sensitive authorization attributes. */
final class Identity
{
    /** @param array<string, scalar|array<scalar>|null> $attributes */
    public function __construct(private string|int $id, private array $attributes = []) {}

    public function id(): string|int { return $this->id; }
    public function attribute(string $key, mixed $fallback = null): mixed { return $this->attributes[$key] ?? $fallback; }

    /** @return array{id: string|int, attributes: array<string, scalar|array<scalar>|null>} */
    public function toSession(): array { return ['id' => $this->id, 'attributes' => $this->attributes]; }

    public static function fromSession(mixed $value): ?self
    {
        if (!is_array($value) || !array_key_exists('id', $value) || !is_string($value['id']) && !is_int($value['id'])) return null;
        return new self($value['id'], is_array($value['attributes'] ?? null) ? $value['attributes'] : []);
    }
}
