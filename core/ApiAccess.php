<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class ApiAccess
{
    public static function requireScope(Request $request, string $scope): void
    {
        $scopes = $request->attribute('api.scopes', []);
        if (!is_array($scopes) || !in_array($scope, $scopes, true)) {
            ApiResponse::error(
                'insufficient_scope',
                'The API token does not grant the required scope.',
                403,
                ['required_scope' => $scope],
            );
        }
    }

    public static function partnerId(Request $request): ?string
    {
        $id = $request->attribute('api.partner_id');
        return is_string($id) && $id !== '' ? $id : null;
    }
}
