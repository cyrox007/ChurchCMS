<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Redirects\RedirectAdminController;
use ChurchCMS\Modules\Redirects\RedirectRequestInterceptor;

$moduleRoot = __DIR__;

foreach ([
    'RedirectRule.php',
    'RedirectRepository.php',
    'RedirectService.php',
    'RedirectRequestInterceptor.php',
    'RedirectAdminController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'redirects';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'redirects',
            label: 'Редиректы',
            route: 'admin_redirects',
            permission: 'redirects.manage',
            priority: 27,
        );

        $router = Router::getInstance();

        $router->addPreDispatchHandler([
            RedirectRequestInterceptor::class,
            'handle',
        ]);

        $router->add(
            'GET',
            '/admin/redirects',
            [RedirectAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_redirects',
        );

        $router->add(
            'POST',
            '/admin/redirects',
            [RedirectAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_redirect_create',
        );

        $router->add(
            'POST',
            '/admin/redirects/{publicId}',
            [RedirectAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_redirect_update',
        );

        $router->add(
            'POST',
            '/admin/redirects/{publicId}/delete',
            [RedirectAdminController::class, 'delete'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_redirect_delete',
        );
    }
};
