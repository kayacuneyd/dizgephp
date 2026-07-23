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
        $files = glob($directory . '/*.php') ?: [];
        $base = [];
        $overrides = [];
        foreach ($files as $file) {
            if (str_ends_with(basename($file, '.php'), '.local')) $overrides[] = $file;
            else $base[] = $file;
        }
        // Overrides are applied in a second pass, after every base config file, so a
        // *.local.php file always wins regardless of glob()'s alphabetical file order
        // (e.g. "modules.local.php" sorts before "modules.php").
        foreach ($base as $file) {
            $value = require $file;
            if (!is_array($value)) continue;
            $this->values[basename($file, '.php')] = $value;
        }
        foreach ($overrides as $file) {
            $value = require $file;
            if (!is_array($value)) continue;
            $name = substr(basename($file, '.php'), 0, -strlen('.local'));
            $this->values[$name] = [...($this->values[$name] ?? []), ...$value];
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
