<?php

declare(strict_types=1);

namespace Dizge\Cms;

use DOMDocument;
use DOMElement;
use DOMNode;

/** Strict HTML allowlist for CMS prose. It is deliberately not a general-purpose HTML parser. */
final class HtmlSanitizer
{
    /** @var array<string, true> */
    private const TAGS = [
        'p' => true, 'h2' => true, 'h3' => true, 'strong' => true, 'em' => true,
        'a' => true, 'ul' => true, 'ol' => true, 'li' => true, 'blockquote' => true,
        'pre' => true, 'code' => true, 'br' => true, 'img' => true,
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') return '';
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="dizge-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('dizge-root');
        if (!$root instanceof DOMElement) return '';

        $output = '';
        foreach ($root->childNodes as $node) $output .= $this->render($node);
        return $output;
    }

    private function render(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) return htmlspecialchars($node->nodeValue ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (!$node instanceof DOMElement) return '';

        $tag = strtolower($node->tagName);
        $children = '';
        foreach ($node->childNodes as $child) $children .= $this->render($child);
        if (!isset(self::TAGS[$tag])) return $children;
        if ($tag === 'br') return '<br>';
        if ($tag === 'img') {
            $src = trim($node->getAttribute('src'));
            if (preg_match('#^/demo/media/[1-9][0-9]*$#', $src) !== 1) return '';
            $alt = htmlspecialchars($node->getAttribute('alt'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<img src="' . $src . '" alt="' . $alt . '">';
        }
        if ($tag === 'a') {
            $href = $this->safeHref($node->getAttribute('href'));
            return $href === null ? $children : '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $children . '</a>';
        }
        return '<' . $tag . '>' . $children . '</' . $tag . '>';
    }

    private function safeHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') return null;
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        return in_array($scheme, ['https', 'http', 'mailto'], true) ? $href : null;
    }
}
