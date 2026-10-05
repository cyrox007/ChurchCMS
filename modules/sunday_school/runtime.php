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
use ChurchCMS\Modules\SundaySchool\SundaySchoolsAdminController;
use ChurchCMS\Modules\SundaySchool\SundaySchoolsPublicApiController;
use ChurchCMS\Modules\SundaySchool\SundaySchoolsPublicController;

foreach ([
    'SundaySchoolRecord.php',
    'SundaySchoolRepository.php',
    'SundaySchoolService.php',
    'SundaySchoolOrganizationAccessService.php',
    'SundaySchoolCatalogService.php',
    'SundaySchoolsAdminController.php',
    'SundaySchoolsPublicController.php',
    'SundaySchoolsPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'sunday_school';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'sunday_school',
            label: 'Воскресная школа',
            route: 'admin_sunday_school',
            permission: 'sunday_school.read',
            priority: 47,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/sunday-school', [SundaySchoolsAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_sunday_school');
        $router->add('POST', '/admin/sunday-school', [SundaySchoolsAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_sunday_school_create');
        $router->add('POST', '/admin/sunday-school/{publicId}', [SundaySchoolsAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_sunday_school_update');
        $router->add('POST', '/admin/sunday-school/{publicId}/publish', [SundaySchoolsAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_sunday_school_publish');
        $router->add('POST', '/admin/sunday-school/{publicId}/unpublish', [SundaySchoolsAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_sunday_school_unpublish');

        $router->add('GET', '/sunday-school', [SundaySchoolsPublicController::class, 'index'], [], 'sunday_school_index');
        $router->add('GET', '/sunday-school/{publicId}', [SundaySchoolsPublicController::class, 'show'], [], 'sunday_school_show');
        $router->add('GET', '/api/v1/sunday-school', [SundaySchoolsPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_sunday_school');
        $router->add('GET', '/api/v1/sunday-school/{publicId}', [SundaySchoolsPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_sunday_school_show');
    }
};
