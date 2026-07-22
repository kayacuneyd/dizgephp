<?php

declare(strict_types=1);

namespace Dizge\Validation;

/** Small explicit validation layer. It validates input; it never HTML-sanitizes output. */
final class Validator
{
    /** @param array<string, mixed> $input @param array<string, string|list<string>> $rules @return array<string, mixed> */
    public function validate(array $input, array $rules): array
    {
        $errors = [];
        $validated = [];
        foreach ($rules as $field => $definition) {
            $fieldRules = is_string($definition) ? explode('|', $definition) : $definition;
            $value = $input[$field] ?? null;
            $nullable = in_array('nullable', $fieldRules, true);
            $required = in_array('required', $fieldRules, true);
            if ($value === null || $value === '') {
                if ($required) $errors[$field][] = 'This field is required.';
                if (!$required || $nullable) continue;
            }
            foreach ($fieldRules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) continue;
                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $message = $this->check($field, $name, $parameter, $value, $input);
                if ($message !== null) $errors[$field][] = $message;
            }
            if (!isset($errors[$field]) && array_key_exists($field, $input)) $validated[$field] = $value;
        }
        if ($errors !== []) throw new ValidationException(new ErrorBag($errors));
        return $validated;
    }

    /** @param array<string, mixed> $input */
    private function check(string $field, string $rule, ?string $parameter, mixed $value, array $input): ?string
    {
        return match ($rule) {
            'string' => is_string($value) ? null : 'Must be a string.',
            'array' => is_array($value) ? null : 'Must be an array.',
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : 'Must be an integer.',
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1'], true) ? null : 'Must be a boolean.',
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Must be a valid email address.',
            'min' => $this->size($value) >= (int) $parameter ? null : "Must be at least {$parameter}.",
            'max' => $this->size($value) <= (int) $parameter ? null : "Must not exceed {$parameter}.",
            'in' => in_array((string) $value, explode(',', (string) $parameter), true) ? null : 'Contains an unsupported value.',
            'confirmed' => hash_equals((string) $value, (string) ($input[$field . '_confirmation'] ?? '')) ? null : 'Confirmation does not match.',
            default => throw new \InvalidArgumentException("Unknown validation rule \"{$rule}\"."),
        };
    }

    private function size(mixed $value): int
    {
        if (is_array($value)) return count($value);
        if (is_numeric($value)) return (int) $value;
        return strlen((string) $value);
    }
}
