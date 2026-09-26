<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\PageCache;
use ChurchCMS\Core\Request;

final class PublicPageCacheMiddleware
{
    public function handle(Request $request): bool
    {
        if (Config::get('performance.page_cache.enabled', true) !== true) {
            return true;
        }

        if (!$this->cacheableRequest($request)) {
            return true;
        }

        $cache = PageCache::fromConfig();
        $key = $cache->key($request);
        $cached = $cache->get($key);
        $ttl = max(1, min(3600, (int) Config::get('performance.page_cache.ttl_seconds', 60)));
        $stale = max(0, min(86400, (int) Config::get('performance.page_cache.stale_while_revalidate_seconds', 300)));

        if ($cached !== null) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            header("Cache-Control: public, max-age={$ttl}, stale-while-revalidate={$stale}");
            header('X-ChurchCMS-Cache: HIT');
            echo $cached;
            exit;
        }

        header("Cache-Control: public, max-age={$ttl}, stale-while-revalidate={$stale}");
        header('X-ChurchCMS-Cache: MISS');

        ob_start();

        register_shutdown_function(static function () use ($cache, $key): void {
            if (http_response_code() !== 200) {
                return;
            }

            foreach (headers_list() as $header) {
                $normalized = strtolower($header);
                if (
                    str_starts_with($normalized, 'set-cookie:')
                    || str_contains($normalized, 'cache-control: no-store')
                    || str_contains($normalized, 'cache-control: private')
                ) {
                    return;
                }
            }

            $body = ob_get_contents();
            if (is_string($body) && $body !== '') {
                $cache->put($key, $body);
            }
        });

        return true;
    }

    private function cacheableRequest(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        $path = $request->path();
        foreach ([
            '/admin',
            '/api/',
            '/feeds/',
            '/_theme-asset',
            '/health',
            '/install.php',
            '/robots.txt',
            '/sitemap.xml',
        ] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                return false;
            }
        }

        if (trim((string) $request->server('QUERY_STRING', '')) !== '') {
            return false;
        }

        if (trim((string) $request->header('Authorization', '')) !== '') {
            return false;
        }

        $sessionName = (string) Config::get('session.name', 'churchcms_session');
        if (isset($_COOKIE[$sessionName])) {
            return false;
        }

        return true;
    }
}
