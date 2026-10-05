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
use ChurchCMS\Modules\Saints\SaintsAdminController;
use ChurchCMS\Modules\Saints\SaintsPublicApiController;
use ChurchCMS\Modules\Saints\SaintsPublicController;

foreach ([
    'SaintRecord.php',
    'SaintRepository.php',
    'SaintService.php',
    'SaintOrganizationAccessService.php',
    'SaintCatalogService.php',
    'SaintsAdminController.php',
    'SaintsPublicController.php',
    'SaintsPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'saints';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'saints',
            label: 'Святые',
            route: 'admin_saints',
            permission: 'saints.read',
            priority: 47,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/saints', [SaintsAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_saints');
        $router->add('POST', '/admin/saints', [SaintsAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_saints_create');
        $router->add('POST', '/admin/saints/{publicId}', [SaintsAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_saints_update');
        $router->add('POST', '/admin/saints/{publicId}/publish', [SaintsAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_saints_publish');
        $router->add('POST', '/admin/saints/{publicId}/unpublish', [SaintsAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_saints_unpublish');

        $router->add('GET', '/saints', [SaintsPublicController::class, 'index'], [], 'saints_index');
        $router->add('GET', '/saints/{publicId}', [SaintsPublicController::class, 'show'], [], 'saints_show');
        $router->add('GET', '/api/v1/saints', [SaintsPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_saints');
        $router->add('GET', '/api/v1/saints/{publicId}', [SaintsPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_saints_show');
    }
};
