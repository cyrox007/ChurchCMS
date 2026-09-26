<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;

final class ApiEnabledMiddleware
{
    public function handle(Request $request): bool
    {
        if (Config::get('api.enabled', true) !== true) {
            ApiResponse::error(
                'api_disabled',
                'The external API is disabled.',
                503,
            );
        }

        return true;
    }
}
