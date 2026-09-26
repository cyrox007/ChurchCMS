<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\ApiTokenAuthenticator;
use ChurchCMS\Core\Request;

final class PartnerApiMiddleware
{
    public function handle(Request $request): bool
    {
        $authorization = trim((string) $request->header('Authorization', ''));

        if (preg_match('/^Bearer\s+([^\s]+)$/i', $authorization, $matches) !== 1) {
            ApiResponse::error(
                'missing_api_token',
                'A Bearer API token is required.',
                401,
            );
        }

        $identity = (new ApiTokenAuthenticator())->authenticate($matches[1]);
        if ($identity === null) {
            ApiResponse::error(
                'invalid_api_token',
                'The API token is invalid or disabled.',
                401,
            );
        }

        $origin = trim((string) $request->header('Origin', ''));
        if ($origin !== '' && $identity['origins'] !== [] && !in_array($origin, $identity['origins'], true)) {
            ApiResponse::error(
                'origin_not_allowed',
                'This token cannot be used from the supplied origin.',
                403,
            );
        }

        $request->setAttribute('api.partner_id', $identity['id']);
        $request->setAttribute('api.scopes', $identity['scopes']);
        $request->setAttribute('api.origins', $identity['origins']);

        return true;
    }
}
