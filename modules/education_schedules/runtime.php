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
use ChurchCMS\Modules\EducationSchedules\EducationSchedulesAdminController;
use ChurchCMS\Modules\EducationSchedules\EducationSchedulesPublicApiController;
use ChurchCMS\Modules\EducationSchedules\EducationSchedulesPublicController;

foreach ([
    'EducationScheduleRecord.php',
    'EducationScheduleRepository.php',
    'EducationScheduleService.php',
    'EducationScheduleOrganizationAccessService.php',
    'EducationScheduleCatalogService.php',
    'EducationSchedulesAdminController.php',
    'EducationSchedulesPublicController.php',
    'EducationSchedulesPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_schedules';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'education_schedules',
            label: 'Расписание обучения',
            route: 'admin_education_schedules',
            permission: 'education_schedules.read',
            priority: 51,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/education/schedules', [EducationSchedulesAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_education_schedules');
        $router->add('POST', '/admin/education/schedules', [EducationSchedulesAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_schedules_create');
        $router->add('POST', '/admin/education/schedules/{publicId}', [EducationSchedulesAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_schedules_update');
        $router->add('POST', '/admin/education/schedules/{publicId}/publish', [EducationSchedulesAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_schedules_publish');
        $router->add('POST', '/admin/education/schedules/{publicId}/unpublish', [EducationSchedulesAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_schedules_unpublish');

        $router->add('GET', '/education/schedules', [EducationSchedulesPublicController::class, 'index'], [], 'education_schedules_index');
        $router->add('GET', '/education/schedules/{publicId}', [EducationSchedulesPublicController::class, 'show'], [], 'education_schedules_show');
        $router->add('GET', '/api/v1/education/schedules', [EducationSchedulesPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_schedules');
        $router->add('GET', '/api/v1/education/schedules/{publicId}', [EducationSchedulesPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_schedules_show');
    }
};
