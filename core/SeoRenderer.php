<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class SeoRenderer
{
    /**
     * @param array<string,mixed> $seo
     */
    public static function render(array $seo, ?string $fallbackTitle = null): string
    {
        $siteName = trim((string) Config::get('site.name', Config::get('app.name', 'ChurchCMS')));
        $title = trim((string) ($seo['title'] ?? $fallbackTitle ?? $siteName));
        $description = trim((string) ($seo['description'] ?? Config::get('seo.default_description', '')));
        $canonical = self::absoluteUrl((string) ($seo['canonical'] ?? ''));
        $image = self::absoluteUrl((string) ($seo['image'] ?? Config::get('seo.default_image', '')));
        $type = trim((string) ($seo['og_type'] ?? 'website'));
        $robots = (($seo['index'] ?? true) === false ? 'noindex' : 'index')
            . ', '
            . (($seo['follow'] ?? true) === false ? 'nofollow' : 'follow');

        $tags = [];
        if ($description !== '') {
            $tags[] = self::meta('name', 'description', $description);
        }

        $tags[] = self::meta('name', 'robots', $robots);
        $tags[] = self::meta('name', 'yandex', $robots);

        if ($canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . self::e($canonical) . '">';
        }

        $tags[] = self::meta('property', 'og:title', $title);
        $tags[] = self::meta('property', 'og:type', $type);
        $tags[] = self::meta('property', 'og:site_name', $siteName);
        $tags[] = self::meta('property', 'og:locale', (string) ($seo['locale'] ?? 'ru_RU'));

        if ($canonical !== '') {
            $tags[] = self::meta('property', 'og:url', $canonical);
        }
        if ($description !== '') {
            $tags[] = self::meta('property', 'og:description', $description);
        }
        if ($image !== '') {
            $tags[] = self::meta('property', 'og:image', $image);
            $tags[] = self::meta('name', 'twitter:card', 'summary_large_image');
            $tags[] = self::meta('name', 'twitter:image', $image);
        } else {
            $tags[] = self::meta('name', 'twitter:card', 'summary');
        }

        $tags[] = self::meta('name', 'twitter:title', $title);
        if ($description !== '') {
            $tags[] = self::meta('name', 'twitter:description', $description);
        }

        foreach ([
            'published_time' => 'article:published_time',
            'modified_time' => 'article:modified_time',
            'author' => 'article:author',
            'section' => 'article:section',
        ] as $key => $property) {
            $value = trim((string) ($seo[$key] ?? ''));
            if ($value !== '') {
                $tags[] = self::meta('property', $property, $value);
            }
        }

        if (!empty($seo['author'])) {
            $tags[] = self::meta('name', 'author', (string) $seo['author']);
        }

        return implode("\n    ", $tags);
    }

    public static function absoluteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            return in_array($scheme, ['http', 'https'], true) ? $url : '';
        }

        if (!str_starts_with($url, '/')) {
            return '';
        }

        $base = rtrim((string) Config::get('app.url', ''), '/');
        return $base !== '' ? $base . $url : '';
    }

    private static function meta(string $attribute, string $name, string $content): string
    {
        return '<meta ' . $attribute . '="' . self::e($name)
            . '" content="' . self::e($content) . '">';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
