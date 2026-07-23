<?php

declare(strict_types=1);

namespace Dizge\Validation;

/** Immutable field-to-message map returned when request data fails its explicit schema. */
final class ErrorBag
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private array $errors) {}

    /** @return array<string, list<string>> */
    public function all(): array { return $this->errors; }
    /** @return list<string> */
    public function for(string $field): array { return $this->errors[$field] ?? []; }
    public function has(string $field): bool { return isset($this->errors[$field]); }
}
