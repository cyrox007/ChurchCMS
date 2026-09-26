<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class PublicOriginMiddleware
{
    public function handle(Request $request): bool
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $expectedHost = strtolower((string) parse_url(
            (string) Config::get('app.url', ''),
            PHP_URL_HOST,
        ));

        if ($expectedHost === '') {
            return true;
        }

        foreach (['Origin', 'Referer'] as $header) {
            $value = trim((string) $request->header($header, ''));
            if ($value === '') {
                continue;
            }

            $host = strtolower((string) parse_url($value, PHP_URL_HOST));
            if ($host === '' || !hash_equals($expectedHost, $host)) {
                Response::text('403 Invalid request origin', 403);
            }

            return true;
        }

        return true;
    }
}
