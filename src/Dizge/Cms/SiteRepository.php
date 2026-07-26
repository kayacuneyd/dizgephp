<?php

declare(strict_types=1);

namespace Dizge\Cms;

use Dizge\Data\Database;

/** Shared, validated site settings and navigation source for classic and headless rendering. */
final class SiteRepository
{
    /** @var array<string,mixed> */
    private const DEFAULTS = [
        'site_name' => 'DizgeCMS',
        'site_description' => '',
        'home_page_id' => null,
        'logo_media_id' => null,
        'favicon_media_id' => null,
        'social_links' => [],
        'theme_accent' => '#0f766e',
        'theme_content_measure' => '68rem',
    ];

    /** @var list<string> */
    private const MENU_LOCATIONS = ['primary', 'footer'];

    public function __construct(private Database $database) {}

    /** @return array{site_name:string,site_description:string,home_page_id:?int,logo_media_id:?int,favicon_media_id:?int,social_links:list<mixed>,theme_accent:string,theme_content_measure:string} */
    public function settings(): array
    {
        $settings = self::DEFAULTS;
        $statement = $this->database->pdo()->query('SELECT setting_key, setting_value FROM site_settings');
        foreach ($statement->fetchAll() as $row) {
            $key = (string) $row['setting_key'];
            if (!array_key_exists($key, self::DEFAULTS)) continue;
            $settings[$key] = $this->decode($key, (string) $row['setting_value']);
        }
        /** @var array{site_name:string,site_description:string,home_page_id:?int,logo_media_id:?int,favicon_media_id:?int,social_links:list<mixed>,theme_accent:string,theme_content_measure:string} $settings */
        return $settings;
    }

    /** @param array<string,mixed> $settings */
    public function saveSettings(array $settings): void
    {
        if ($settings === []) return;
        $this->database->transaction(function () use ($settings): void {
            $statement = $this->database->pdo()->prepare('INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at');
            foreach ($settings as $key => $value) {
                if (!array_key_exists($key, self::DEFAULTS)) throw new \InvalidArgumentException('Unsupported site setting: ' . $key);
                $statement->execute([$key, $this->encode($key, $value), gmdate('c')]);
            }
        });
    }

    /** @return array{location:string,items:list<array{id:int,kind:string,label:string,content_id:?int,url:?string,parent_id:?int,position:int}>} */
    public function menu(string $location): array
    {
        $this->assertLocation($location);
        $statement = $this->database->pdo()->prepare('SELECT i.id, i.kind, i.label, i.content_id, i.url, i.parent_id, i.position FROM menus m JOIN menu_items i ON i.menu_id = m.id WHERE m.location = ? ORDER BY i.position ASC, i.id ASC');
        $statement->execute([$location]);
        $items = array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'kind' => (string) $item['kind'],
            'label' => (string) $item['label'],
            'content_id' => $item['content_id'] === null ? null : (int) $item['content_id'],
            'url' => $item['url'] === null ? null : (string) $item['url'],
            'parent_id' => $item['parent_id'] === null ? null : (int) $item['parent_id'],
            'position' => (int) $item['position'],
        ], $statement->fetchAll());
        return ['location' => $location, 'items' => $items];
    }

    /** @param list<array<string,mixed>> $items */
    public function replaceMenu(string $location, array $items): void
    {
        $this->assertLocation($location);
        $normalized = [];
        foreach ($items as $position => $item) $normalized[] = $this->normalizeItem($item, $position);
        $this->database->transaction(function () use ($location, $normalized): void {
            $now = gmdate('c');
            $menu = $this->database->pdo()->prepare('INSERT INTO menus (location, created_at, updated_at) VALUES (?, ?, ?) ON CONFLICT(location) DO UPDATE SET updated_at = excluded.updated_at');
            $menu->execute([$location, $now, $now]);
            $idStatement = $this->database->pdo()->prepare('SELECT id FROM menus WHERE location = ?');
            $idStatement->execute([$location]);
            $menuId = (int) $idStatement->fetchColumn();
            $this->database->pdo()->prepare('DELETE FROM menu_items WHERE menu_id = ?')->execute([$menuId]);
            $insert = $this->database->pdo()->prepare('INSERT INTO menu_items (menu_id, kind, label, content_id, url, parent_id, position, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ids = [];
            foreach ($normalized as $index => $item) {
                $parentId = $item['parent_index'] === null ? null : ($ids[$item['parent_index']] ?? null);
                if ($item['parent_index'] !== null && $parentId === null) throw new \InvalidArgumentException('Menu children must follow their parent item.');
                $insert->execute([$menuId, $item['kind'], $item['label'], $item['content_id'], $item['url'], $parentId, $item['position'], $now, $now]);
                $ids[$index] = (int) $this->database->pdo()->lastInsertId();
            }
        });
    }

    private function assertLocation(string $location): void
    {
        if (!in_array($location, self::MENU_LOCATIONS, true)) throw new \InvalidArgumentException('Unsupported menu location.');
    }

    /** @param array<string,mixed> $item @return array{kind:string,label:string,content_id:?int,url:?string,parent_index:?int,position:int} */
    private function normalizeItem(array $item, int $position): array
    {
        $kind = (string) ($item['kind'] ?? '');
        $label = trim((string) ($item['label'] ?? ''));
        if (!in_array($kind, ['content', 'url', 'section'], true) || $label === '' || mb_strlen($label) > 120) throw new \InvalidArgumentException('Invalid menu item.');
        $contentId = isset($item['content_id']) && $item['content_id'] !== '' ? (int) $item['content_id'] : null;
        $url = isset($item['url']) && $item['url'] !== '' ? trim((string) $item['url']) : null;
        if ($kind === 'content' && ($contentId === null || $contentId < 1 || $url !== null)) throw new \InvalidArgumentException('Content menu items require only a content id.');
        if ($kind === 'url' && ($contentId !== null || !$this->isSafeUrl($url))) throw new \InvalidArgumentException('URL menu items require a local or HTTPS URL.');
        if ($kind === 'section' && ($contentId !== null || $url === null || !preg_match('/^#[A-Za-z][A-Za-z0-9_-]*$/', $url))) throw new \InvalidArgumentException('Section menu items require an anchor URL.');
        $parentIndex = array_key_exists('parent_index', $item) && $item['parent_index'] !== '' ? filter_var($item['parent_index'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) : null;
        if ($parentIndex === false || ($parentIndex !== null && $parentIndex >= $position)) throw new \InvalidArgumentException('Menu parent must be an earlier item.');
        return ['kind' => $kind, 'label' => $label, 'content_id' => $contentId, 'url' => $url, 'parent_index' => $parentIndex, 'position' => $position];
    }

    private function isSafeUrl(?string $url): bool
    {
        if ($url === null || str_starts_with($url, '//')) return false;
        if (str_starts_with($url, '/')) return true;
        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($url), 'https://');
    }

    private function encode(string $key, mixed $value): string
    {
        return match ($key) {
            'site_name' => $this->stringValue($value, 120, false),
            'site_description' => $this->stringValue($value, 320, true),
            'home_page_id', 'logo_media_id', 'favicon_media_id' => $this->nullableId($value),
            'social_links' => $this->socialLinks($value),
            'theme_accent' => $this->themeAccent($value),
            'theme_content_measure' => $this->themeMeasure($value),
        };
    }

    private function decode(string $key, string $value): mixed
    {
        return match ($key) {
            'home_page_id', 'logo_media_id', 'favicon_media_id' => (int) $value > 0 ? (int) $value : null,
            'social_links' => is_array($decoded = json_decode($value, true)) ? array_values($decoded) : [],
            default => $value,
        };
    }

    private function stringValue(mixed $value, int $max, bool $emptyAllowed): string
    {
        if (!is_string($value) || (!$emptyAllowed && trim($value) === '') || mb_strlen($value) > $max) throw new \InvalidArgumentException('Invalid site setting value.');
        return trim($value);
    }

    private function nullableId(mixed $value): string
    {
        if ($value === null || $value === '') return '0';
        if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) throw new \InvalidArgumentException('Invalid site setting id.');
        return (string) $value;
    }

    private function socialLinks(mixed $value): string
    {
        if (!is_array($value) || count($value) > 12) throw new \InvalidArgumentException('Invalid social links.');
        foreach ($value as $url) if (!is_string($url) || !$this->isSafeUrl($url) || !str_starts_with(strtolower($url), 'https://')) throw new \InvalidArgumentException('Social links must be HTTPS URLs.');
        return json_encode(array_values($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function themeAccent(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) throw new \InvalidArgumentException('Theme accent must be a six-digit hex color.');
        return strtolower($value);
    }

    private function themeMeasure(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['48rem', '56rem', '68rem', '76rem'], true)) throw new \InvalidArgumentException('Unsupported theme content width.');
        return $value;
    }
}
