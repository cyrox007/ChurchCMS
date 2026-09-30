<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Modules\Documents\DocumentsAdminController;
use ChurchCMS\Modules\Documents\DocumentsApiController;
use ChurchCMS\Modules\Documents\DocumentsPublicApiController;
use ChurchCMS\Modules\Documents\DocumentsPublicController;

$moduleRoot = __DIR__;
foreach ([
    'DocumentRecord.php',
    'DocumentRepository.php',
    'DocumentApiResource.php',
    'FederatedDocumentFeedService.php',
    'DocumentPartnerTombstoneRepository.php',
    'DocumentPartnerTombstoneApiResource.php',
    'DocumentCategoryRepository.php',
    'DocumentCategoryService.php',
    'DocumentService.php',
    'DocumentMediaService.php',
    'DocumentOrganizationAccessService.php',
    'DocumentCatalogService.php',
    'DocumentsAdminController.php',
    'DocumentsPublicController.php',
    'DocumentsPublicApiController.php',
    'DocumentsApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    public function moduleId(): string
    {
        return 'documents';
    }

    public function capabilities(): array
    {
        return [];
    }

    public function boot(): void
    {
        AdminNavigationRegistry::register(
            id: 'documents',
            label: 'Документы',
            route: 'admin_documents',
            permission: 'documents.read',
            priority: 37,
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/admin/documents',
            [DocumentsAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_documents',
        );

        $router->add(
            'POST',
            '/admin/documents',
            [DocumentsAdminController::class, 'create'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_documents_create',
        );

        $router->add(
            'POST',
            '/admin/documents/{publicId}',
            [DocumentsAdminController::class, 'update'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_documents_update',
        );

        $router->add(
            'POST',
            '/admin/documents/{publicId}/publish',
            [DocumentsAdminController::class, 'publish'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_documents_publish',
        );

        $router->add(
            'POST',
            '/admin/documents/{publicId}/withdraw',
            [DocumentsAdminController::class, 'withdraw'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_documents_withdraw',
        );

        $router->add(
            'POST',
            '/admin/documents/{publicId}/archive',
            [DocumentsAdminController::class, 'archive'],
            [
                RequireAdminMiddleware::class,
                CsrfMiddleware::class,
            ],
            'admin_documents_archive',
        );

        $router->add(
            'GET',
            '/documents',
            [DocumentsPublicController::class, 'index'],
            [],
            'document_index',
        );

        $router->add(
            'GET',
            '/documents/{publicId}',
            [DocumentsPublicController::class, 'show'],
            [],
            'document_show',
        );

        $router->add(
            'GET',
            '/api/v1/documents',
            [DocumentsPublicApiController::class, 'index'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_documents',
        );

        $router->add(
            'GET',
            '/api/v1/documents/aggregated',
            [DocumentsApiController::class, 'aggregated'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_documents_aggregated',
        );

        $router->add(
            'GET',
            '/api/v1/documents/{publicId}',
            [DocumentsPublicApiController::class, 'show'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_document_show',
        );

        $router->add(
            'GET',
            '/api/v1/partner/documents',
            [DocumentsApiController::class, 'partnerIndex'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_documents',
        );

        $router->add(
            'GET',
            '/api/v1/partner/documents/tombstones',
            [DocumentsApiController::class, 'partnerTombstones'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_document_tombstones',
        );
    }
};
