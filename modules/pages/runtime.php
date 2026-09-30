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
use ChurchCMS\Core\ThemeGlobalDataRegistry;
use ChurchCMS\Modules\Pages\PagesApiController;
use ChurchCMS\Modules\Pages\PagesController;
use ChurchCMS\Modules\Pages\PagesAdminController;
use ChurchCMS\Modules\Pages\NavigationMenuAdminController;
use ChurchCMS\Modules\Pages\NavigationMenuCapability;
use ChurchCMS\Modules\Pages\NavigationThemeDataProvider;

$moduleRoot = __DIR__;
foreach ([
    'PageStatus.php',
    'Page.php',
    'PageRepository.php',
    'PageRevisionService.php',
    'PageService.php',
    'PageOrganizationAccessService.php',
    'PageApiResource.php',
    'NavigationMenu.php',
    'NavigationMenuItem.php',
    'NavigationMenuRepository.php',
    'NavigationMenuService.php',
    'NavigationMenuCapability.php',
    'NavigationThemeDataProvider.php',
    'NavigationMenuAdminController.php',
    'PagesController.php',
    'PagesApiController.php',
    'PagesAdminController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'pages';
    }

    public function capabilities(): array
    {
        return [
            'pages.navigation' =>
                new NavigationMenuCapability(),
        ];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'pages',
            label: 'Страницы',
            route: 'admin_pages',
            permission: 'pages.read',
            priority: 25,
        );
        AdminNavigationRegistry::register(
            id: 'navigation',
            label: 'Меню',
            route: 'admin_navigation',
            permission: 'pages.edit',
            priority: 26,
        );

        ThemeGlobalDataRegistry::register(
            'pages.navigation',
            new NavigationThemeDataProvider(),
        );

        $router = Router::getInstance();
        $middlewares = [
            ApiEnabledMiddleware::class,
            ApiCorsMiddleware::class,
            ApiPublicRateLimitMiddleware::class,
        ];

        $router->add(
            'GET',
            '/admin/pages',
            [PagesAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_pages',
        );
        $router->add(
            'GET',
            '/admin/pages/new',
            [PagesAdminController::class, 'createForm'],
            [RequireAdminMiddleware::class],
            'admin_page_new',
        );
        $router->add(
            'POST',
            '/admin/pages',
            [PagesAdminController::class, 'create'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_page_create',
        );
        $router->add(
            'GET',
            '/admin/pages/{publicId}',
            [PagesAdminController::class, 'edit'],
            [RequireAdminMiddleware::class],
            'admin_page_edit',
        );
        $router->add(
            'POST',
            '/admin/pages/{publicId}',
            [PagesAdminController::class, 'update'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_page_update',
        );
        $router->add(
            'POST',
            '/admin/pages/{publicId}/revisions/{revisionPublicId}/restore',
            [PagesAdminController::class, 'restoreRevision'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_page_revision_restore',
        );
        $router->add(
            'POST',
            '/admin/pages/{publicId}/publish',
            [PagesAdminController::class, 'publish'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_page_publish',
        );
        $router->add(
            'POST',
            '/admin/pages/{publicId}/unpublish',
            [PagesAdminController::class, 'unpublish'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_page_unpublish',
        );

        $router->add(
            'GET',
            '/admin/navigation',
            [NavigationMenuAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_navigation',
        );
        $router->add(
            'POST',
            '/admin/navigation/items',
            [NavigationMenuAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_navigation_item_create',
        );
        $router->add(
            'POST',
            '/admin/navigation/items/{publicId}',
            [NavigationMenuAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_navigation_item_update',
        );
        $router->add(
            'POST',
            '/admin/navigation/items/{publicId}/delete',
            [NavigationMenuAdminController::class, 'delete'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_navigation_item_delete',
        );

        $router->add(
            'GET',
            '/pages/{path*}',
            [PagesController::class, 'show'],
            [],
            'page_show',
        );

        $router->add(
            'GET',
            '/api/v1/pages',
            [PagesApiController::class, 'index'],
            $middlewares,
            'api_v1_pages',
        );
        $router->add(
            'GET',
            '/api/v1/pages/{publicId}',
            [PagesApiController::class, 'show'],
            $middlewares,
            'api_v1_page_show',
        );
    }
};
