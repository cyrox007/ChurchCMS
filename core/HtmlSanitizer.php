<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'b', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote',
        'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const GLOBAL_ATTRIBUTES = ['class'];

    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'title', 'rel'],
        'th' => ['scope', 'colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (!class_exists(DOMDocument::class)) {
            throw new RuntimeException('DOM extension is required for rich HTML sanitization.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="utf-8" ?><div id="churchcms-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->getElementById('churchcms-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child) ?: '';
        }

        return $result;
    }

    private static function cleanChildren(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanChildren($child);
            }

            $child = $next;
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(
            self::GLOBAL_ATTRIBUTES,
            self::TAG_ATTRIBUTES[$tag] ?? [],
        );

        $remove = [];
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->name);
            if (!in_array($name, $allowed, true)) {
                $remove[] = $attribute->name;
                continue;
            }

            if ($tag === 'a' && $name === 'href' && !self::safeHref($attribute->value)) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function safeHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
