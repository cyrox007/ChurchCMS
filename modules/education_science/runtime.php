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
use ChurchCMS\Modules\EducationScience\EducationScienceAdminController;
use ChurchCMS\Modules\EducationScience\EducationSciencePublicApiController;
use ChurchCMS\Modules\EducationScience\EducationSciencePublicController;

foreach ([
    'EducationScienceRecord.php',
    'EducationScienceRepository.php',
    'EducationScienceService.php',
    'EducationScienceOrganizationAccessService.php',
    'EducationScienceCatalogService.php',
    'EducationScienceAdminController.php',
    'EducationSciencePublicController.php',
    'EducationSciencePublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_science';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'education_science',
            label: 'Научная деятельность',
            route: 'admin_education_science',
            permission: 'education_science.read',
            priority: 52,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/education/science', [EducationScienceAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_education_science');
        $router->add('POST', '/admin/education/science', [EducationScienceAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_science_create');
        $router->add('POST', '/admin/education/science/{publicId}', [EducationScienceAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_science_update');
        $router->add('POST', '/admin/education/science/{publicId}/publish', [EducationScienceAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_science_publish');
        $router->add('POST', '/admin/education/science/{publicId}/unpublish', [EducationScienceAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_science_unpublish');

        $router->add('GET', '/education/science', [EducationSciencePublicController::class, 'index'], [], 'education_science_index');
        $router->add('GET', '/education/science/{publicId}', [EducationSciencePublicController::class, 'show'], [], 'education_science_show');
        $router->add('GET', '/api/v1/education/science', [EducationSciencePublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_science');
        $router->add('GET', '/api/v1/education/science/{publicId}', [EducationSciencePublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_science_show');
    }
};
