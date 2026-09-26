<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AuthorizationService;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\ThemeRenderer;

final class AdminController
{
    public function dashboard(Request $request): never
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        $roles = AuthorizationService::fromDatabase()->roles($userId);

        ThemeRenderer::fromConfig()->page('admin.dashboard', [
            'title' => 'Панель управления',
            'siteName' => 'ChurchCMS',
            'adminUser' => $user,
            'roles' => $roles,
        ]);
    }
}
