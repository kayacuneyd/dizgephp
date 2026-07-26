<?php

declare(strict_types=1);

namespace Dizge\Cms;

use XMLReader;

/** Streaming WordPress WXR reader. It imports content only; plugin/theme code is never executed. */
final class WxrImporter
{
    /**
     * @param callable(array<string,mixed>): int $create
     * @param callable(array{url:string,original_name:string}): array<string,mixed>|null $storeMedia
     */
    public function import(string $path, callable $create, bool $dryRun = false, ?callable $storeMedia = null): array
    {
        if (!is_file($path) || !is_readable($path)) throw new \InvalidArgumentException('WXR file is not readable.');
        $reader = new XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) throw new \InvalidArgumentException('WXR XML could not be opened.');
        $items = 0; $imported = 0; $media = 0; $skipped = 0; $errors = [];
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'item') continue;
                $xml = $reader->readOuterXml();
                if ($xml === '') { $skipped++; continue; }
                try {
                    $item = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                    if ($item === false) throw new \RuntimeException('Invalid item XML.');
                    $type = (string) $item->children('wp', true)->post_type;
                    if ($type === 'attachment') {
                        $attachmentUrl = trim((string) ($item->children('wp', true)->attachment_url ?? ''));
                        if (preg_match('#^https://#i', $attachmentUrl) === 1 && ($dryRun || $storeMedia !== null)) {
                            if (!$dryRun) $storeMedia(['url' => $attachmentUrl, 'original_name' => basename((string) parse_url($attachmentUrl, PHP_URL_PATH)) ?: 'imported-image']);
                            $media++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    if (!in_array($type, ['post', 'page'], true)) { $skipped++; continue; }
                    $title = trim((string) $item->title);
                    $wp = $item->children('wp', true);
                    $slug = Slug::make((string) ($wp->post_name ?: $title));
                    $content = (string) $item->children('content', true)->encoded;
                    if ($title === '' || $slug === '') throw new \RuntimeException('Title or slug is empty.');
                    $record = [
                        'type' => $type === 'post' ? 'article' : 'page',
                        'title' => $title,
                        'slug' => $slug,
                        'old_slug' => $slug,
                        'body_html' => $content,
                        'meta_description' => self::metadata($item),
                        'status' => (string) $wp->status === 'publish' ? 'published' : 'draft',
                        'published_at' => self::date((string) ($wp->post_date_gmt ?: $wp->post_date)),
                        'created_at' => self::date((string) ($wp->post_date_gmt ?: $wp->post_date)),
                    ];
                    $items++;
                    if (!$dryRun) { $create($record); $imported++; }
                } catch (\Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        } finally {
            $reader->close();
        }
        return ['items' => $items, 'imported' => $imported, 'media' => $media, 'skipped' => $skipped, 'errors' => $errors, 'dry_run' => $dryRun];
    }

    /** @return string */
    private static function metadata(\SimpleXMLElement $item): string
    {
        foreach ($item->children('wp', true)->postmeta as $postmeta) {
            $key = (string) $postmeta->meta_key;
            if (in_array($key, ['_yoast_wpseo_metadesc', 'rank_math_description', 'description'], true)) return trim((string) $postmeta->meta_value);
        }
        return '';
    }

    private static function date(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false || str_starts_with($value, '0000-') ? gmdate('c') : gmdate('c', $timestamp);
    }
}
