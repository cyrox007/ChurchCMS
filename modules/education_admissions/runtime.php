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
use ChurchCMS\Modules\EducationAdmissions\EducationAdmissionsAdminController;
use ChurchCMS\Modules\EducationAdmissions\EducationAdmissionsPublicApiController;
use ChurchCMS\Modules\EducationAdmissions\EducationAdmissionsPublicController;

foreach ([
    'EducationAdmissionRecord.php',
    'EducationAdmissionRepository.php',
    'EducationAdmissionService.php',
    'EducationAdmissionOrganizationAccessService.php',
    'EducationAdmissionCatalogService.php',
    'EducationAdmissionsAdminController.php',
    'EducationAdmissionsPublicController.php',
    'EducationAdmissionsPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_admissions';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'education_admissions',
            label: 'Приёмная кампания',
            route: 'admin_education_admissions',
            permission: 'education_admissions.read',
            priority: 49,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/education/admissions', [EducationAdmissionsAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_education_admissions');
        $router->add('POST', '/admin/education/admissions', [EducationAdmissionsAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_admissions_create');
        $router->add('POST', '/admin/education/admissions/{publicId}', [EducationAdmissionsAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_admissions_update');
        $router->add('POST', '/admin/education/admissions/{publicId}/publish', [EducationAdmissionsAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_admissions_publish');
        $router->add('POST', '/admin/education/admissions/{publicId}/unpublish', [EducationAdmissionsAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_admissions_unpublish');

        $router->add('GET', '/education/admissions', [EducationAdmissionsPublicController::class, 'index'], [], 'education_admissions_index');
        $router->add('GET', '/education/admissions/{publicId}', [EducationAdmissionsPublicController::class, 'show'], [], 'education_admissions_show');
        $router->add('GET', '/api/v1/education/admissions', [EducationAdmissionsPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_admissions');
        $router->add('GET', '/api/v1/education/admissions/{publicId}', [EducationAdmissionsPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_admissions_show');
    }
};
