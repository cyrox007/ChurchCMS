<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\RateLimiter;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class PasswordResetRateLimitMiddleware
{
    public function handle(Request $request): bool
    {
        $path = $request->path();

        if (str_ends_with($path, '/forgot')) {
            $value = strtolower(trim((string) $request->post(
                'email',
                '',
            )));
            $scope = 'request';
        } else {
            $value = trim((string) $request->post(
                'token',
                '',
            ));
            $scope = 'consume';
        }

        $fingerprint = $request->ip()
            . ':'
            . substr(
                hash('sha256', $value),
                0,
                24,
            );

        $result = (
            new RateLimiter(
                CHURCHCMS_ROOT . '/storage/rate-limits'
            )
        )->consume(
            'password-reset:'
                . $scope
                . ':'
                . $fingerprint,
            5,
            900,
        );

        if (!$result['allowed']) {
            header(
                'Retry-After: '
                . $result['retry_after']
            );

            Response::text(
                'Слишком много попыток. Повторите позже.',
                429,
            );
        }

        return true;
    }
}
