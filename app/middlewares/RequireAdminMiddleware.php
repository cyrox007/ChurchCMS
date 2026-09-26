<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\App\Services\AdminAuthService;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class RequireAdminMiddleware
{
    public function handle(Request $request): bool
    {
        $user = AdminAuthService::fromDatabase()->current($request);
        if ($user === null) {
            $request->unsetSession('admin_authenticated');
            $request->unsetSession('admin_user_id');
            $request->unsetSession('admin_user_public_id');
            Response::redirectLocal('/admin/login', 303);
        }

        $request->setAttribute('admin.user', $user);
        return true;
    }
}
