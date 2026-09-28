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
use ChurchCMS\Modules\Organizations\FederationAdminController;
use ChurchCMS\Modules\Organizations\FederationApiController;
use ChurchCMS\Modules\Organizations\OrganizationAdminController;

$moduleRoot = __DIR__;
foreach ([
    'OrganizationUnit.php',
    'OrganizationTypeCatalog.php',
    'FederationLink.php',
    'OrganizationRepository.php',
    'OrganizationAccessService.php',
    'FederationRepository.php',
    'OrganizationService.php',
    'FederationService.php',
    'FederationDiscoveryClient.php',
    'OrganizationAdminController.php',
    'FederationAdminController.php',
    'FederationApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'organizations';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'organizations',
            label: 'Структура',
            route: 'admin_organizations',
            permission: 'organizations.manage',
            priority: 30,
        );
        AdminNavigationRegistry::register(
            id: 'federation',
            label: 'Связи',
            route: 'admin_federation',
            permission: 'settings.manage',
            priority: 35,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/organizations',
            [OrganizationAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_organizations',
        );
        $router->add(
            'GET',
            '/admin/organizations/new',
            [OrganizationAdminController::class, 'createForm'],
            [RequireAdminMiddleware::class],
            'admin_organization_new',
        );
        $router->add(
            'POST',
            '/admin/organizations',
            [OrganizationAdminController::class, 'create'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_organization_create',
        );
        $router->add(
            'GET',
            '/admin/organizations/{publicId}',
            [OrganizationAdminController::class, 'edit'],
            [RequireAdminMiddleware::class],
            'admin_organization_edit',
        );
        $router->add(
            'POST',
            '/admin/organizations/{publicId}',
            [OrganizationAdminController::class, 'update'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_organization_update',
        );
        $router->add(
            'POST',
            '/admin/organizations/{publicId}/archive',
            [OrganizationAdminController::class, 'archive'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_organization_archive',
        );
        $router->add(
            'POST',
            '/admin/organizations/{publicId}/restore',
            [OrganizationAdminController::class, 'restore'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_organization_restore',
        );

        $router->add(
            'GET',
            '/admin/federation',
            [FederationAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_federation',
        );
        $router->add(
            'POST',
            '/admin/federation/discover',
            [FederationAdminController::class, 'discover'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_federation_discover',
        );
        $router->add(
            'POST',
            '/admin/federation/connect',
            [FederationAdminController::class, 'connect'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_federation_connect',
        );
        $router->add(
            'POST',
            '/admin/federation/{publicId}/check',
            [FederationAdminController::class, 'check'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_federation_check',
        );

        $router->add(
            'POST',
            '/admin/federation/{publicId}/revoke',
            [FederationAdminController::class, 'revoke'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_federation_revoke',
        );

        $router->add(
            'GET',
            '/api/v1/federation/meta',
            [FederationApiController::class, 'meta'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_federation_meta',
        );
    }
};
