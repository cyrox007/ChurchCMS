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
use ChurchCMS\Modules\Ministries\MinistriesAdminController;
use ChurchCMS\Modules\Ministries\MinistriesPublicApiController;
use ChurchCMS\Modules\Ministries\MinistriesPublicController;

foreach ([
    'MinistryRecord.php',
    'MinistryRepository.php',
    'MinistryService.php',
    'MinistryOrganizationAccessService.php',
    'MinistryCatalogService.php',
    'MinistriesAdminController.php',
    'MinistriesPublicController.php',
    'MinistriesPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'ministries';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'ministries',
            label: 'Служения',
            route: 'admin_ministries',
            permission: 'ministries.read',
            priority: 45,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/ministries', [MinistriesAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_ministries');
        $router->add('POST', '/admin/ministries', [MinistriesAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_ministries_create');
        $router->add('POST', '/admin/ministries/{publicId}', [MinistriesAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_ministries_update');
        $router->add('POST', '/admin/ministries/{publicId}/publish', [MinistriesAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_ministries_publish');
        $router->add('POST', '/admin/ministries/{publicId}/unpublish', [MinistriesAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_ministries_unpublish');

        $router->add('GET', '/ministries', [MinistriesPublicController::class, 'index'], [], 'ministries_index');
        $router->add('GET', '/ministries/{publicId}', [MinistriesPublicController::class, 'show'], [], 'ministries_show');
        $router->add('GET', '/api/v1/ministries', [MinistriesPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_ministries');
        $router->add('GET', '/api/v1/ministries/{publicId}', [MinistriesPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_ministries_show');
    }
};
