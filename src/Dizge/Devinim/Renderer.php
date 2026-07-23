<?php

declare(strict_types=1);

namespace Dizge\Devinim;

/**
 * Server-side DevinimJS adapter. It validates known component props, escapes every data value
 * and allows nested component markup only through an explicit callback trust boundary.
 */
final class Renderer
{
    /** @var array<string, list<string>> */
    private const COMPONENTS = [
        'counter' => ['start', 'step'], 'tabs' => ['active', 'label'],
        'disclosure' => ['open', 'summary'], 'modal' => ['open', 'label'],
        'toast' => ['message', 'open', 'duration'], 'pagination' => ['page', 'total', 'size', 'label'],
        'dropdown' => ['open', 'label'], 'search' => ['query', 'label', 'placeholder'],
        'product-card' => ['id', 'name', 'price', 'action'],
        'field' => ['id', 'name', 'label', 'control', 'type', 'options', 'value', 'placeholder', 'required', 'disabled', 'error'],
        'confirm' => ['label', 'message', 'confirmLabel', 'cancelLabel', 'value'],
        'autocomplete' => ['items', 'query', 'label'], 'data-table' => ['columns', 'rows', 'label'],
        'cart' => ['items', 'label', 'empty', 'removeLabel', 'totalLabel'],
        'toast-stack' => ['duration', 'label'], 'state' => ['state', 'loading', 'empty', 'error', 'retryLabel'],
    ];

    /** @var list<string> */
    private array $used = [];
    private string $modulePath = '/assets/devinim/modules/';

    /** @return list<string> Registered component names (without the "dv-" prefix), e.g. "counter". */
    public static function componentNames(): array
    {
        return array_keys(self::COMPONENTS);
    }

    public function setModulePath(string $path): void
    {
        if ($path === '') throw new \InvalidArgumentException('DevinimJS module path cannot be empty.');
        $this->modulePath = rtrim($path, '/') . '/';
    }

    /** @param array<string, mixed> $args */
    public function render(string $name, array $args = []): string
    {
        if (!array_key_exists($name, self::COMPONENTS)) {
            throw new \InvalidArgumentException(sprintf('Unknown DevinimJS component "%s".', $name));
        }
        $tag = 'dv-' . $name;
        $children = $args['children'] ?? null;
        $attributes = $args['attrs'] ?? [];
        unset($args['children'], $args['attrs']);
        if ($children !== null && !is_callable($children)) {
            throw new \InvalidArgumentException('children must be a callback. Escape user input inside its returned markup with e().');
        }
        if (!is_array($attributes)) throw new \InvalidArgumentException('attrs must be an array of approved HTML attributes.');

        $output = '<' . $tag . $this->renderHtmlAttributes($attributes);
        foreach ($args as $key => $value) {
            if (!in_array($key, self::COMPONENTS[$name], true)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a supported %s prop.', $key, $tag));
            }
            if ($value === null || $value === false) continue;
            $attribute = 'data-' . self::toKebab($key);
            if ($value === true) {
                $output .= ' ' . $attribute;
                continue;
            }
            if (is_array($value)) $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new \InvalidArgumentException(sprintf('"%s" must be scalar, array, boolean or null.', $key));
            }
            $output .= sprintf(' %s="%s"', $attribute, \e((string) $value));
        }
        $output .= '>';
        if ($children !== null) $output .= (string) $children();
        $output .= '</' . $tag . '>';

        if (!in_array($name, $this->used, true)) $this->used[] = $name;
        return $output;
    }

    public function scripts(): string
    {
        return implode('', array_map(
            fn (string $name): string => sprintf('<script type="module" src="%s"></script>', \e(self::versionedUrl($this->modulePath . 'dv-' . $name . '.js'))),
            $this->used,
        ));
    }

    /**
     * Appends a `?v=<mtime>` cache-busting query string to a same-origin `/assets/...` URL,
     * read from the actual published file's mtime on disk (see `bin/publish-assets`). This is
     * what makes the long, immutable `Cache-Control` on `/assets/**` in `public/.htaccess` safe:
     * component/runtime filenames are not content-hashed (this project is build-free by
     * contract, see `AGENTS.md`), so without a changing URL a browser would keep serving a
     * stale, possibly broken component after an upgrade for up to the cache lifetime.
     *
     * An external URL (an operator-configured CDN via `dv_path()`, or a protocol-relative URL)
     * is returned unchanged: `filemtime()` has no meaning for a URL that is not a local file,
     * and a pinned CDN URL (see `docs/deployment.md`) is already immutable by tagged version.
     *
     * General-purpose: works for any local `/assets/...` path, not just component modules —
     * e.g. `/assets/dizge-bridge.js`. `dv_bridge_scripts()` (`dizge.php`) does not call this yet
     * even though that file has the same staleness exposure; see the comment above its
     * definition and `docs/swarm/handoffs/TASK-003-implementer.md` for why.
     */
    public static function versionedUrl(string $urlPath): string
    {
        if (!str_starts_with($urlPath, '/') || str_starts_with($urlPath, '//')) {
            return $urlPath;
        }
        $localFile = dirname(__DIR__, 3) . '/public' . $urlPath;
        $mtime = is_file($localFile) ? filemtime($localFile) : false;
        return $mtime !== false ? $urlPath . '?v=' . $mtime : $urlPath;
    }

    /** @param array<string, mixed> $attributes */
    private function renderHtmlAttributes(array $attributes): string
    {
        $output = '';
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !preg_match('/^(id|class|title|role|aria-[a-z-]+|data-[a-z0-9-]+)$/', $name)) {
                throw new \InvalidArgumentException(sprintf('HTML attribute "%s" is not permitted.', (string) $name));
            }
            if ($value === null || $value === false) continue;
            if ($value === true) { $output .= ' ' . $name; continue; }
            if (!is_scalar($value)) throw new \InvalidArgumentException(sprintf('HTML attribute "%s" must be scalar.', $name));
            $output .= sprintf(' %s="%s"', $name, \e((string) $value));
        }
        return $output;
    }

    private static function toKebab(string $value): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '-$0', $value));
    }
}
