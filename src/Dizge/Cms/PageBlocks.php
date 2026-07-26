<?php

declare(strict_types=1);

namespace Dizge\Cms;

/**
 * Fixed, data-only page composition contract. Themes render these blocks; editors never submit
 * template markup as a substitute for a block definition.
 */
final class PageBlocks
{
    private const TYPES = ['hero', 'rich_text', 'image', 'cta', 'service_list'];

    /** @return list<array{type:string,data:array<string,string>}> */
    public function decode(string $json): array
    {
        if ($json === '') return [];
        try { $blocks = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $exception) { throw new \InvalidArgumentException('Page blocks must be valid JSON.', 0, $exception); }
        if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) > 50) throw new \InvalidArgumentException('Page blocks must be a list of at most 50 blocks.');
        $result = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null) || !in_array($block['type'], self::TYPES, true) || !is_array($block['data'] ?? null)) throw new \InvalidArgumentException('Page block type or data is invalid.');
            $data = [];
            foreach ($block['data'] as $key => $value) {
                if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) || !is_string($value) || mb_strlen($value) > 5000) throw new \InvalidArgumentException('Page block field is invalid.');
                $data[$key] = $value;
            }
            $result[] = ['type' => $block['type'], 'data' => $data];
        }
        return $result;
    }

    /** @param list<array{type:string,data:array<string,string>}> $blocks */
    public function encode(array $blocks): string { return json_encode($blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
}
