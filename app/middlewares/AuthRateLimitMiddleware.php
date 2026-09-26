<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\RateLimiter;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class AuthRateLimitMiddleware
{
    public function handle(Request $request): bool
    {
        $login = strtolower(trim((string) $request->post('username', '')));
        $fingerprint = $request->ip() . ':' . substr(hash('sha256', $login), 0, 20);

        $result = (new RateLimiter(CHURCHCMS_ROOT . '/storage/rate-limits'))
            ->consume('auth:' . $fingerprint, 10, 300);

        if (!$result['allowed']) {
            header('Retry-After: ' . $result['retry_after']);
            Response::text('Too many login attempts.', 429);
        }

        return true;
    }
}
