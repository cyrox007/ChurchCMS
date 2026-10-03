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
use ChurchCMS\Modules\People\PeopleAdminController;
use ChurchCMS\Modules\People\PeopleAppointmentAdminController;
use ChurchCMS\Modules\People\PeoplePublicApiController;
use ChurchCMS\Modules\People\PeoplePublicController;

$moduleRoot = __DIR__;
foreach ([
    'Person.php',
    'PersonAppointment.php',
    'PeopleRepository.php',
    'PeopleService.php',
    'PeopleOrganizationAccessService.php',
    'PersonMediaService.php',
    'PersonAppointmentLifecycleService.php',
    'PeopleCatalogService.php',
    'PeopleAdminController.php',
    'PeopleAppointmentAdminController.php',
    'PeoplePublicController.php',
    'PeoplePublicApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'people';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'people',
            label: 'Люди и духовенство',
            route: 'admin_people',
            permission: 'people.read',
            priority: 38,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/people',
            [PeopleAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_people',
        );
        $router->add(
            'POST',
            '/admin/people',
            [PeopleAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_people_create',
        );
        $router->add(
            'POST',
            '/admin/people/{publicId}',
            [PeopleAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_people_update',
        );
        $router->add(
            'POST',
            '/admin/people/{publicId}/appointments',
            [PeopleAdminController::class, 'createAppointment'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_people_appointment_create',
        );
        $router->add(
            'POST',
            '/admin/people/appointments/{appointmentPublicId}',
            [PeopleAppointmentAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_people_appointment_update',
        );
        $router->add(
            'POST',
            '/admin/people/appointments/{appointmentPublicId}/end',
            [PeopleAppointmentAdminController::class, 'end'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_people_appointment_end',
        );
        $router->add(
            'POST',
            '/admin/people/appointments/{appointmentPublicId}/reactivate',
            [PeopleAppointmentAdminController::class, 'reactivate'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_people_appointment_reactivate',
        );

        $router->add(
            'GET',
            '/people',
            [PeoplePublicController::class, 'index'],
            [],
            'people_index',
        );
        $router->add(
            'GET',
            '/people/{publicId}',
            [PeoplePublicController::class, 'show'],
            [],
            'people_show',
        );

        $router->add(
            'GET',
            '/api/v1/people',
            [PeoplePublicApiController::class, 'index'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_people',
        );
        $router->add(
            'GET',
            '/api/v1/people/{publicId}',
            [PeoplePublicApiController::class, 'show'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_people_show',
        );
    }
};
