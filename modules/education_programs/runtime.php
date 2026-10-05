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
use ChurchCMS\Modules\EducationPrograms\EducationProgramsAdminController;
use ChurchCMS\Modules\EducationPrograms\EducationProgramsPublicApiController;
use ChurchCMS\Modules\EducationPrograms\EducationProgramsPublicController;

foreach ([
    'EducationProgramRecord.php',
    'EducationProgramRepository.php',
    'EducationProgramService.php',
    'EducationProgramOrganizationAccessService.php',
    'EducationProgramCatalogService.php',
    'EducationProgramsAdminController.php',
    'EducationProgramsPublicController.php',
    'EducationProgramsPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_programs';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'education_programs',
            label: 'Образовательные программы',
            route: 'admin_education_programs',
            permission: 'education_programs.read',
            priority: 48,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/education/programs', [EducationProgramsAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_education_programs');
        $router->add('POST', '/admin/education/programs', [EducationProgramsAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_programs_create');
        $router->add('POST', '/admin/education/programs/{publicId}', [EducationProgramsAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_programs_update');
        $router->add('POST', '/admin/education/programs/{publicId}/publish', [EducationProgramsAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_programs_publish');
        $router->add('POST', '/admin/education/programs/{publicId}/unpublish', [EducationProgramsAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_programs_unpublish');

        $router->add('GET', '/education/programs', [EducationProgramsPublicController::class, 'index'], [], 'education_programs_index');
        $router->add('GET', '/education/programs/{publicId}', [EducationProgramsPublicController::class, 'show'], [], 'education_programs_show');
        $router->add('GET', '/api/v1/education/programs', [EducationProgramsPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_programs');
        $router->add('GET', '/api/v1/education/programs/{publicId}', [EducationProgramsPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_programs_show');
    }
};
