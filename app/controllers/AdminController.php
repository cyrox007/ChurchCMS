<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AdminAuthorization;
use ChurchCMS\App\Services\AuthorizationService;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\ThemeRenderer;

final class AdminController
{
    public function dashboard(Request $request): never
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        $roles = AuthorizationService::fromDatabase()->roles($userId);

        $canModerateComments = AdminAuthorization::can($request, 'comments.moderate');
        $pendingComments = 0;

        if ($canModerateComments) {
            $capability = ModuleRuntimeLoader::capability('comments', 'comments.moderation');
            if ($capability !== null && method_exists($capability, 'pendingCount')) {
                $pendingComments = (int) $capability->pendingCount();
            }
        }

        ThemeRenderer::fromConfig()->page('admin.dashboard', [
            'title' => 'Панель управления',
            'siteName' => 'ChurchCMS',
            'adminUser' => $user,
            'roles' => $roles,
            'canModerateComments' => $canModerateComments,
            'pendingComments' => $pendingComments,
        ]);
    }
}
