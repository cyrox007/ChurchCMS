<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers\Api\V1;

use ChurchCMS\Core\ApiAccess;
use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;

final class ExternalApiController
{
    public function meta(Request $request): never
    {
        ApiResponse::success([
            'name' => (string) Config::get('app.name', 'ChurchCMS'),
            'api_version' => (string) Config::get('api.version', 'v1'),
            'capabilities' => [
                'public_read_api',
                'partner_token_auth',
                'scoped_access',
                'rate_limits',
                'cors_allowlist',
            ],
        ], cacheSeconds: (int) Config::get('api.public_cache_seconds', 60));
    }

    public function partnerPing(Request $request): never
    {
        ApiResponse::success([
            'authenticated' => true,
            'partner' => ApiAccess::partnerId($request),
            'scopes' => $request->attribute('api.scopes', []),
        ]);
    }

    public function preflight(Request $request): never
    {
        http_response_code(204);
        header('Cache-Control: no-store');
        exit;
    }
}
