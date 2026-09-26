<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\RateLimiter;
use ChurchCMS\Core\Request;

final class ApiPublicRateLimitMiddleware
{
    public function handle(Request $request): bool
    {
        $limit = (int) Config::get('api.rate_limit.public_per_minute', 120);
        $limiter = new RateLimiter(CHURCHCMS_ROOT . '/storage/rate-limits');
        $result = $limiter->consume('public:' . $request->ip() . ':' . $request->path(), $limit);

        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $result['remaining']);

        if (!$result['allowed']) {
            header('Retry-After: ' . $result['retry_after']);
            ApiResponse::error('rate_limit_exceeded', 'Too many API requests.', 429);
        }

        return true;
    }
}
