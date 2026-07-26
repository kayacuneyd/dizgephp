<?php

declare(strict_types=1);

namespace Dizge\Cms;

/** Immutable, validated metadata for a locally installed CMS plugin. */
final class PluginManifest
{
    /** @var list<string> */
    private const CAPABILITIES = ['routes', 'migrations', 'admin', 'commands', 'content_types', 'seo'];

    /** @param list<string> $capabilities */
    private function __construct(
        private string $id,
        private string $name,
        private string $version,
        private array $capabilities,
        private string $entrypoint,
        private string $directory,
    ) {}

    public static function fromFile(string $path): self
    {
        $directory = dirname($path);
        $json = file_get_contents($path);
        if ($json === false) throw new \InvalidArgumentException('Plugin manifest is not readable.');
        return self::fromJson($json, $directory);
    }

    /** @return self */
    public static function fromJson(string $json, string $directory): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) throw new \InvalidArgumentException('Plugin manifest must be a JSON object.');
        foreach (['id', 'name', 'version', 'entrypoint'] as $key) {
            if (!is_string($data[$key] ?? null) || trim($data[$key]) === '') throw new \InvalidArgumentException("Plugin manifest field {$key} is required.");
        }
        $id = (string) $data['id'];
        if (preg_match('/^[a-z][a-z0-9-]{1,62}$/', $id) !== 1) throw new \InvalidArgumentException('Plugin id is invalid.');
        $capabilities = $data['capabilities'] ?? [];
        if (!is_array($capabilities) || array_diff($capabilities, self::CAPABILITIES) !== []) throw new \InvalidArgumentException('Plugin capability is not allowlisted.');
        $entrypoint = (string) $data['entrypoint'];
        if ($entrypoint !== basename($entrypoint) || !str_ends_with($entrypoint, '.php')) throw new \InvalidArgumentException('Plugin entrypoint must be a local PHP file.');
        $resolved = realpath($directory . '/' . $entrypoint);
        if ($resolved === false || !str_starts_with($resolved, rtrim(realpath($directory) ?: $directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) throw new \InvalidArgumentException('Plugin entrypoint must remain inside its plugin directory.');
        return new self($id, (string) $data['name'], (string) $data['version'], array_values($capabilities), $entrypoint, realpath($directory) ?: $directory);
    }

    public function id(): string { return $this->id; }
    public function name(): string { return $this->name; }
    public function version(): string { return $this->version; }
    /** @return list<string> */
    public function capabilities(): array { return $this->capabilities; }
    public function entrypoint(): string { return $this->entrypoint; }
    public function directory(): string { return $this->directory; }
    public function entrypointPath(): string { return $this->directory . DIRECTORY_SEPARATOR . $this->entrypoint; }
}
