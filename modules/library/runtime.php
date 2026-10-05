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
use ChurchCMS\Modules\Library\LibraryAdminController;
use ChurchCMS\Modules\Library\LibraryPublicApiController;
use ChurchCMS\Modules\Library\LibraryPublicController;

foreach ([
    'LibraryItemRecord.php',
    'LibraryItemRepository.php',
    'LibraryItemService.php',
    'LibraryOrganizationAccessService.php',
    'LibraryCatalogService.php',
    'LibraryAdminController.php',
    'LibraryPublicController.php',
    'LibraryPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'library';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'library',
            label: 'Библиотека',
            route: 'admin_library',
            permission: 'library.read',
            priority: 48,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/library', [LibraryAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_library');
        $router->add('POST', '/admin/library', [LibraryAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_library_create');
        $router->add('POST', '/admin/library/{publicId}', [LibraryAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_library_update');
        $router->add('POST', '/admin/library/{publicId}/publish', [LibraryAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_library_publish');
        $router->add('POST', '/admin/library/{publicId}/unpublish', [LibraryAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_library_unpublish');

        $router->add('GET', '/library', [LibraryPublicController::class, 'index'], [], 'library_index');
        $router->add('GET', '/library/{publicId}', [LibraryPublicController::class, 'show'], [], 'library_show');
        $router->add('GET', '/api/v1/library', [LibraryPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_library');
        $router->add('GET', '/api/v1/library/{publicId}', [LibraryPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_library_show');
    }
};
