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
use ChurchCMS\Modules\EducationStaff\EducationStaffAdminController;
use ChurchCMS\Modules\EducationStaff\EducationStaffPublicApiController;
use ChurchCMS\Modules\EducationStaff\EducationStaffPublicController;

foreach ([
    'EducationChairRecord.php',
    'EducationTeacherAssignmentRecord.php',
    'EducationStaffRepository.php',
    'EducationStaffService.php',
    'EducationStaffOrganizationAccessService.php',
    'EducationStaffCatalogService.php',
    'EducationStaffAdminController.php',
    'EducationStaffPublicController.php',
    'EducationStaffPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_staff';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'education_staff',
            label: 'Кафедры и преподаватели',
            route: 'admin_education_staff',
            permission: 'education_staff.read',
            priority: 49,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/education/staff', [EducationStaffAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_education_staff');
        $router->add('POST', '/admin/education/chairs', [EducationStaffAdminController::class, 'createChair'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_chair_create');
        $router->add('POST', '/admin/education/chairs/{publicId}', [EducationStaffAdminController::class, 'updateChair'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_chair_update');
        $router->add('POST', '/admin/education/chairs/{publicId}/publish', [EducationStaffAdminController::class, 'publishChair'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_chair_publish');
        $router->add('POST', '/admin/education/chairs/{publicId}/unpublish', [EducationStaffAdminController::class, 'unpublishChair'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_chair_unpublish');
        $router->add('POST', '/admin/education/chairs/{chairPublicId}/teachers', [EducationStaffAdminController::class, 'assignTeacher'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_teacher_assign');
        $router->add('POST', '/admin/education/teachers/{assignmentPublicId}', [EducationStaffAdminController::class, 'updateTeacher'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_teacher_update');
        $router->add('POST', '/admin/education/teachers/{assignmentPublicId}/deactivate', [EducationStaffAdminController::class, 'deactivateTeacher'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_teacher_deactivate');
        $router->add('POST', '/admin/education/teachers/{assignmentPublicId}/reactivate', [EducationStaffAdminController::class, 'reactivateTeacher'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_teacher_reactivate');

        $router->add('GET', '/education/chairs', [EducationStaffPublicController::class, 'index'], [], 'education_staff_index');
        $router->add('GET', '/education/chairs/{publicId}', [EducationStaffPublicController::class, 'show'], [], 'education_staff_show');
        $router->add('GET', '/api/v1/education/chairs', [EducationStaffPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_chairs');
        $router->add('GET', '/api/v1/education/chairs/{publicId}', [EducationStaffPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_chairs_show');
    }
};
