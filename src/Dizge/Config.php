<?php

declare(strict_types=1);

namespace Dizge;

/** File-backed configuration with environment variables as deployment overrides. */
final class Config
{
    /** @var array<string, array<string,mixed>> */
    private array $values = [];

    public function __construct(string $directory)
    {
        if (!is_dir($directory)) return;
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $value = require $file;
            if (!is_array($value)) continue;
            $name = basename($file, '.php');
            if ($name === 'modules.local') $this->values['modules'] = [...($this->values['modules'] ?? []), ...$value];
            else $this->values[$name] = $value;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = $this->values[array_shift($parts)] ?? null;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) return $default;
            $value = $value[$part];
        }
        return $value ?? $default;
    }
}
