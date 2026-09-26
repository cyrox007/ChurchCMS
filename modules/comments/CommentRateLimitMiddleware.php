<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\RateLimiter;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class CommentRateLimitMiddleware
{
    public function handle(Request $request): bool
    {
        $attempts = max(1, (int) Config::get('comments.rate_limit.attempts', 5));
        $window = max(60, (int) Config::get('comments.rate_limit.window_seconds', 300));

        $result = (new RateLimiter(CHURCHCMS_ROOT . '/storage/rate-limits'))
            ->consume(
                'comment:' . $request->ip() . ':' . $request->path(),
                $attempts,
                $window,
            );

        if (!$result['allowed']) {
            header('Retry-After: ' . $result['retry_after']);
            Response::text('Слишком много комментариев за короткое время. Попробуйте позже.', 429);
        }

        return true;
    }
}
