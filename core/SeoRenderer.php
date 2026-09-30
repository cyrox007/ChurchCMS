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
        $ogTitle = trim((string) ($seo['og_title'] ?? $title));
        $ogDescription = trim((string) ($seo['og_description'] ?? $description));
        $robots = (($seo['index'] ?? true) === false ? 'noindex' : 'index')
            . ', '
            . (($seo['follow'] ?? true) === false ? 'nofollow' : 'follow');

        $tags = [];
        if ($description !== '') {
            $tags[] = self::meta('name', 'description', $description);
        }

        $keywords = trim((string) ($seo['keywords'] ?? ''));
        if ($keywords !== '') {
            $tags[] = self::meta('name', 'keywords', $keywords);
        }

        $tags[] = self::meta('name', 'robots', $robots);
        $tags[] = self::meta('name', 'yandex', $robots);

        if ($canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . self::e($canonical) . '">';
        }

        $tags[] = self::meta('property', 'og:title', $ogTitle);
        $tags[] = self::meta('property', 'og:type', $type);
        $tags[] = self::meta('property', 'og:site_name', $siteName);
        $tags[] = self::meta('property', 'og:locale', (string) ($seo['locale'] ?? 'ru_RU'));

        if ($canonical !== '') {
            $tags[] = self::meta('property', 'og:url', $canonical);
        }
        if ($ogDescription !== '') {
            $tags[] = self::meta('property', 'og:description', $ogDescription);
        }
        if ($image !== '') {
            $tags[] = self::meta('property', 'og:image', $image);
            $tags[] = self::meta('name', 'twitter:card', 'summary_large_image');
            $tags[] = self::meta('name', 'twitter:image', $image);
        } else {
            $tags[] = self::meta('name', 'twitter:card', 'summary');
        }

        $tags[] = self::meta('name', 'twitter:title', $ogTitle);
        if ($ogDescription !== '') {
            $tags[] = self::meta('name', 'twitter:description', $ogDescription);
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
            $tags[] = self::meta(
                'name',
                'author',
                (string) $seo['author'],
            );
        }

        $structuredData = $seo['structured_data'] ?? [];
        if (is_array($structuredData)) {
            foreach (self::structuredItems($structuredData) as $item) {
                try {
                    $json = json_encode(
                        $item,
                        JSON_THROW_ON_ERROR
                        | JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_HEX_TAG
                        | JSON_HEX_AMP
                        | JSON_HEX_APOS
                        | JSON_HEX_QUOT,
                    );
                } catch (\JsonException) {
                    continue;
                }

                $tags[] = '<script type="application/ld+json">'
                    . $json
                    . '</script>';
            }
        }

        return implode("\n    ", $tags);
    }

    /**
     * @param array<mixed> $value
     * @return list<array<string,mixed>>
     */
    private static function structuredItems(array $value): array
    {
        if ($value === []) {
            return [];
        }

        if (isset($value['@type']) || isset($value['@context'])) {
            return [$value];
        }

        return array_values(array_filter(
            $value,
            static fn(mixed $item): bool =>
                is_array($item)
                && (
                    isset($item['@type'])
                    || isset($item['@context'])
                ),
        ));
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
