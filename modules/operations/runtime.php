<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Operations\OperationsAdminController;

$moduleRoot = __DIR__;
foreach ([
    'OperationsService.php',
    'OperationsAdminController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'operations';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'operations',
            label: 'Система',
            route: 'admin_operations',
            permission: 'settings.manage',
            priority: 90,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/system',
            [OperationsAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_operations',
        );

        $router->add(
            'POST',
            '/admin/system/backups/create',
            [OperationsAdminController::class, 'createBackup'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_operations_backup_create',
        );

        $router->add(
            'POST',
            '/admin/system/backups/{backupId}/verify',
            [OperationsAdminController::class, 'verifyBackup'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_operations_backup_verify',
        );

        $router->add(
            'POST',
            '/admin/system/backups/{backupId}/restore',
            [OperationsAdminController::class, 'restoreBackup'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_operations_backup_restore',
        );

        $router->add(
            'POST',
            '/admin/system/updates/download',
            [OperationsAdminController::class, 'downloadUpdate'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_operations_update_download',
        );

        $router->add(
            'POST',
            '/admin/system/updates/{stageId}/apply',
            [OperationsAdminController::class, 'applyUpdate'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_operations_update_apply',
        );
    }
};
