<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Http\Middleware;

/**
 * Discovers and activates only local, explicitly enabled extensions.
 *
 * A plugin entrypoint must return a callable accepting this registry. The callable may register
 * explicit routes through registerRoute(); it may not download code or alter the core loader.
 */
final class ExtensionRegistry
{
    /** @var array<string, PluginManifest>|null */
    private ?array $plugins = null;
    /** @var array<string, ThemeManifest>|null */
    private ?array $themes = null;
    /** @var list<string> */
    private array $booted = [];

    public function __construct(private string $basePath) {}

    /** @return array<string, PluginManifest> */
    public function plugins(): array
    {
        if ($this->plugins !== null) return $this->plugins;
        $this->plugins = [];
        foreach (glob($this->basePath . '/plugins/*/plugin.json') ?: [] as $path) {
            try {
                $manifest = PluginManifest::fromFile($path);
                $this->plugins[$manifest->id()] = $manifest;
            } catch (\InvalidArgumentException) {
            }
        }
        return $this->plugins;
    }

    /** @return array<string, ThemeManifest> */
    public function themes(): array
    {
        if ($this->themes !== null) return $this->themes;
        $this->themes = [];
        foreach (glob($this->basePath . '/themes/*/theme.json') ?: [] as $path) {
            try {
                $manifest = ThemeManifest::fromFile($path);
                $this->themes[$manifest->id()] = $manifest;
            } catch (\InvalidArgumentException) {
            }
        }
        return $this->themes;
    }

    /** @return list<string> */
    public function enabledPluginIds(): array
    {
        $ids = $this->settings()['plugins']['enabled'] ?? [];
        return array_values(array_filter(is_array($ids) ? $ids : [], 'is_string'));
    }

    public function activeThemeId(): string
    {
        return (string) ($this->settings()['themes']['active'] ?? 'starter');
    }

    public function activeTheme(): ?ThemeManifest
    {
        return $this->themes()[$this->activeThemeId()] ?? null;
    }

    public function boot(): void
    {
        foreach ($this->enabledPluginIds() as $id) {
            $manifest = $this->plugins()[$id] ?? throw new \RuntimeException("Enabled plugin is not installed: {$id}");
            if (in_array($id, $this->booted, true)) continue;
            $extension = require $manifest->entrypointPath();
            if (!is_callable($extension)) throw new \RuntimeException("Plugin entrypoint must return a callable: {$id}");
            $extension($this);
            $this->booted[] = $id;
        }
    }

    public function enablePlugin(string $id): void
    {
        if (!isset($this->plugins()[$id])) throw new \InvalidArgumentException("Plugin is not installed: {$id}");
        $ids = $this->enabledPluginIds();
        if (!in_array($id, $ids, true)) $ids[] = $id;
        $this->writeSettings([...$this->settings(), 'plugins' => ['enabled' => array_values($ids)]]);
    }

    public function disablePlugin(string $id): void
    {
        $ids = array_values(array_filter($this->enabledPluginIds(), static fn (string $item): bool => $item !== $id));
        $this->writeSettings([...$this->settings(), 'plugins' => ['enabled' => $ids]]);
    }

    public function useTheme(string $id): void
    {
        if (!isset($this->themes()[$id])) throw new \InvalidArgumentException("Theme is not installed: {$id}");
        $this->writeSettings([...$this->settings(), 'themes' => ['active' => $id]]);
    }

    /** @param list<Middleware|callable> $middleware */
    public function registerRoute(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        if ($method === 'GET') app()->get($pattern, $handler, $middleware);
        elseif ($method === 'POST') app()->post($pattern, $handler, $middleware);
        else throw new \InvalidArgumentException('Plugins may register only explicit GET or POST routes.');
    }

    /** @return array<string,mixed> */
    private function settings(): array
    {
        $base = is_file($this->basePath . '/config/cms.php') ? require $this->basePath . '/config/cms.php' : [];
        $local = is_file($this->basePath . '/config/cms.local.php') ? require $this->basePath . '/config/cms.local.php' : [];
        return [...(is_array($base) ? $base : []), ...(is_array($local) ? $local : [])];
    }

    /** @param array<string,mixed> $settings */
    private function writeSettings(array $settings): void
    {
        $path = $this->basePath . '/config/cms.local.php';
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($settings, true) . ";\n";
        if (file_put_contents($path, $content, LOCK_EX) === false) throw new \RuntimeException('CMS extension settings could not be written.');
    }
}
