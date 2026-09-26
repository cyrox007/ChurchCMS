<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class AdminAuthorization
{
    public static function requirePermission(Request $request, string $permission): void
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        if (
            $userId <= 0
            || !AuthorizationService::fromDatabase()->hasPermission($userId, $permission)
        ) {
            Response::text('403 Forbidden', 403);
        }
    }

    public static function can(Request $request, string $permission): bool
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        return $userId > 0
            && AuthorizationService::fromDatabase()->hasPermission($userId, $permission);
    }
}
