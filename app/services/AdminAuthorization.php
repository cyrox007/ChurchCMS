<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class AdminAuthorization
{
    public static function requirePermission(Request $request, string $permission): void
    {
        if (!self::can($request, $permission)) {
            Response::text('403 Forbidden', 403);
        }
    }

    public static function can(Request $request, string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        $cache = $request->attribute('admin.authorization.cache', []);
        if (
            is_array($cache)
            && array_key_exists($permission, $cache)
        ) {
            return $cache[$permission] === true;
        }

        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        $allowed = $userId > 0
            && AuthorizationService::fromDatabase()->hasPermission(
                $userId,
                $permission,
            );

        if (!is_array($cache)) {
            $cache = [];
        }
        $cache[$permission] = $allowed;
        $request->setAttribute('admin.authorization.cache', $cache);

        return $allowed;
    }
}
