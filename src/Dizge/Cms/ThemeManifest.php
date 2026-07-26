<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** Validated theme metadata; themes may render, but never own CMS state or authorization. */
final class ThemeManifest
{
    private function __construct(private string $id, private string $name, private string $version, private string $directory = '') {}

    public static function fromFile(string $path): self
    {
        $json = file_get_contents($path);
        if ($json === false) throw new \InvalidArgumentException('Theme manifest is not readable.');
        $manifest = self::fromJson($json);
        return new self($manifest->id, $manifest->name, $manifest->version, realpath(dirname($path)) ?: dirname($path));
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) throw new \InvalidArgumentException('Theme manifest must be a JSON object.');
        if (!is_string($data['id'] ?? null) || preg_match('/^[a-z][a-z0-9-]{1,62}$/', (string) $data['id']) !== 1) throw new \InvalidArgumentException('Theme field id is invalid.');
        foreach (['name', 'version'] as $key) {
            if (!is_string($data[$key] ?? null) || trim((string) $data[$key]) === '') throw new \InvalidArgumentException("Theme field {$key} is invalid.");
        }
        return new self((string) $data['id'], (string) $data['name'], (string) $data['version']);
    }

    public function id(): string { return $this->id; }
    public function name(): string { return $this->name; }
    public function version(): string { return $this->version; }
    public function directory(): string { return $this->directory; }
    public function stylesheetPath(string $basePath): string { return $basePath . '/public/themes/' . $this->id . '/theme.css'; }
}
