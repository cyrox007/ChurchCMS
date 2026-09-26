<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\App\Services\AuthorizationService;
use ChurchCMS\App\Services\AdminSearchService;
use ChurchCMS\App\Services\AdminTaskCenter;
use ChurchCMS\Core\Request;
use ChurchCMS\App\Services\AdminShell;

final class AdminController
{
    public function search(Request $request): never
    {
        $result = AdminSearchService::search($request);

        AdminShell::page(
            $request,
            'admin.search',
            [
                'title' => 'Поиск',
                'searchQuery' => $result['query'],
                'searchValid' => $result['valid'],
                'searchGroups' => $result['groups'],
            ],
            'search',
        );
    }

    public function tasks(Request $request): never
    {
        $summary = AdminTaskCenter::summary($request);

        AdminShell::page(
            $request,
            'admin.tasks',
            [
                'title' => 'Задачи',
                'tasks' => $summary['tasks'],
                'taskCount' => $summary['count'],
            ],
            'tasks',
        );
    }

    public function dashboard(Request $request): never
    {
        $user = $request->attribute('admin.user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;
        $roles = AuthorizationService::fromDatabase()->roles($userId);

        AdminShell::page(
            $request,
            'admin.dashboard',
            [
                'title' => 'Обзор',
                'roles' => $roles,
            ],
            'overview',
        );
    }
}
