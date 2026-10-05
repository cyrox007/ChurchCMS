<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Shrines\ShrinesAdminController;
use ChurchCMS\Modules\Shrines\ShrinesPublicApiController;
use ChurchCMS\Modules\Shrines\ShrinesPublicController;

foreach ([
    'ShrineRecord.php',
    'ShrineRepository.php',
    'ShrineService.php',
    'ShrineOrganizationAccessService.php',
    'ShrineCatalogService.php',
    'ShrinesAdminController.php',
    'ShrinesPublicController.php',
    'ShrinesPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'shrines';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'shrines',
            label: 'Святыни',
            route: 'admin_shrines',
            permission: 'shrines.read',
            priority: 46,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/shrines', [ShrinesAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_shrines');
        $router->add('POST', '/admin/shrines', [ShrinesAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_shrines_create');
        $router->add('POST', '/admin/shrines/{publicId}', [ShrinesAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_shrines_update');
        $router->add('POST', '/admin/shrines/{publicId}/publish', [ShrinesAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_shrines_publish');
        $router->add('POST', '/admin/shrines/{publicId}/unpublish', [ShrinesAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_shrines_unpublish');

        $router->add('GET', '/shrines', [ShrinesPublicController::class, 'index'], [], 'shrines_index');
        $router->add('GET', '/shrines/{publicId}', [ShrinesPublicController::class, 'show'], [], 'shrines_show');
        $router->add('GET', '/api/v1/shrines', [ShrinesPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_shrines');
        $router->add('GET', '/api/v1/shrines/{publicId}', [ShrinesPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_shrines_show');
    }
};
