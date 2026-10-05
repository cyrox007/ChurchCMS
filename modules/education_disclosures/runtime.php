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
use ChurchCMS\Modules\EducationDisclosures\EducationDisclosuresAdminController;
use ChurchCMS\Modules\EducationDisclosures\EducationDisclosuresPublicApiController;
use ChurchCMS\Modules\EducationDisclosures\EducationDisclosuresPublicController;

foreach ([
    'EducationDisclosureRecord.php',
    'EducationDisclosureRepository.php',
    'EducationDisclosureService.php',
    'EducationDisclosureOrganizationAccessService.php',
    'EducationDisclosureCatalogService.php',
    'EducationDisclosuresAdminController.php',
    'EducationDisclosuresPublicController.php',
    'EducationDisclosuresPublicApiController.php',
] as $file) {
    require_once __DIR__ . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'education_disclosures';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'education_disclosures',
            label: 'Обязательные сведения',
            route: 'admin_education_disclosures',
            permission: 'education_disclosures.read',
            priority: 50,
        );

        $router = Router::getInstance();
        $router->add('GET', '/admin/education/disclosures', [EducationDisclosuresAdminController::class, 'index'], [RequireAdminMiddleware::class], 'admin_education_disclosures');
        $router->add('POST', '/admin/education/disclosures', [EducationDisclosuresAdminController::class, 'create'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_disclosures_create');
        $router->add('POST', '/admin/education/disclosures/{publicId}', [EducationDisclosuresAdminController::class, 'update'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_disclosures_update');
        $router->add('POST', '/admin/education/disclosures/{publicId}/publish', [EducationDisclosuresAdminController::class, 'publish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_disclosures_publish');
        $router->add('POST', '/admin/education/disclosures/{publicId}/unpublish', [EducationDisclosuresAdminController::class, 'unpublish'], [RequireAdminMiddleware::class, CsrfMiddleware::class], 'admin_education_disclosures_unpublish');

        $router->add('GET', '/education/disclosures', [EducationDisclosuresPublicController::class, 'index'], [], 'education_disclosures_index');
        $router->add('GET', '/education/disclosures/{publicId}', [EducationDisclosuresPublicController::class, 'show'], [], 'education_disclosures_show');
        $router->add('GET', '/api/v1/education/disclosures', [EducationDisclosuresPublicApiController::class, 'index'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_disclosures');
        $router->add('GET', '/api/v1/education/disclosures/{publicId}', [EducationDisclosuresPublicApiController::class, 'show'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_education_disclosures_show');
    }
};
