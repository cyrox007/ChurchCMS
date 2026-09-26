<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Seo;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;

final class SeoController
{
    public function robots(Request $request): never
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /api/\n";
        echo "Disallow: /install.php\n";

        if ($base !== '') {
            echo "Sitemap: {$base}/sitemap.xml\n";
        }

        exit;
    }

    public function sitemap(Request $request): never
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        $repository = PublicationSeoRepository::fromDatabase();
        $entries = $repository->sitemapPublications();

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=300, stale-while-revalidate=600');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        self::url($base . '/', null);
        self::url($base . '/publications', null);

        foreach ($entries as $entry) {
            self::url(
                $base . '/publications/' . rawurlencode($entry['slug']),
                $entry['updated_at'] !== '' ? $entry['updated_at'] : null,
            );
        }

        echo '</urlset>';
        exit;
    }

    private static function url(string $location, ?string $lastModified): void
    {
        echo '<url><loc>' . self::xml($location) . '</loc>';

        if ($lastModified !== null) {
            try {
                $date = (new \DateTimeImmutable($lastModified))
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM);
                echo '<lastmod>' . self::xml($date) . '</lastmod>';
            } catch (\Throwable) {
                // Invalid historic timestamp should not break the whole sitemap.
            }
        }

        echo '</url>';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
