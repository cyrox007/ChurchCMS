<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\CsrfMiddleware;
use ChurchCMS\App\Middlewares\RequireAdminMiddleware;
use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\App\Services\AdminSearchRegistry;
use ChurchCMS\App\Services\AdminTaskRegistry;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\SyndicationRegistry;
use ChurchCMS\Modules\Publications\PublicationSyndicationProvider;
use ChurchCMS\Modules\Publications\FederatedPublicationSyndicationProvider;
use ChurchCMS\Modules\Publications\PublicationAdminSearchProvider;
use ChurchCMS\Modules\Publications\PublicationAdminTaskProvider;
use ChurchCMS\Modules\Publications\PublicationsApiController;
use ChurchCMS\Modules\Publications\PublicationsCapability;
use ChurchCMS\Modules\Publications\PublicationsController;
use ChurchCMS\Modules\Publications\PublicationsAdminController;

$moduleRoot = __DIR__;
foreach ([
    'PublicationStatus.php',
    'PublicationType.php',
    'Publication.php',
    'PublicationRepository.php',
    'PublicationPartnerTombstoneRepository.php',
    'PublicationPartnerTombstoneApiResource.php',
    'PublicationTaxonomyRepository.php',
    'PublicationTaxonomyService.php',
    'PublicationRevisionService.php',
    'PublicationService.php',
    'PublicationOrganizationAccessService.php',
    'PublicationScheduleWorker.php',
    'PublicationApiResource.php',
    'FederatedPublicationFeedService.php',
    'FederatedPublicationSyndicationProvider.php',
    'PublicationSyndicationProvider.php',
    'PublicationAdminSearchProvider.php',
    'PublicationAdminTaskProvider.php',
    'PublicationsCapability.php',
    'PublicationsController.php',
    'PublicationsApiController.php',
    'PublicationsAdminController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    private ?PublicationsCapability $capability = null;

    public function moduleId(): string
    {
        return 'publications';
    }

    public function capabilities(): array
    {
        $capability = $this->capability ??= new PublicationsCapability();

        return [
            'publications.read' => $capability,
            'publications.external-import' => $capability,
            'syndication.publications' => $capability,
        ];
    }

    public function boot(): void
    {
        $this->capability = new PublicationsCapability();

        // Регистрация остаётся ленивой: boot модуля не открывает соединение с БД.
        SyndicationRegistry::register(
            'publications',
            new PublicationSyndicationProvider(),
        );
        SyndicationRegistry::register(
            'federated-publications',
            new FederatedPublicationSyndicationProvider(),
        );

        AdminNavigationRegistry::register(
            id: 'publications',
            label: 'Публикации',
            route: 'admin_publications',
            permission: 'publications.read',
            priority: 20,
        );

        AdminSearchRegistry::register(
            new PublicationAdminSearchProvider(),
        );

        AdminTaskRegistry::register(
            new PublicationAdminTaskProvider(),
        );

        $router = Router::getInstance();

        $router->add(
            'GET',
            '/publications',
            [PublicationsController::class, 'index'],
            [],
            'publication_index',
        );
        $router->add(
            'GET',
            '/publications/{slug}',
            [PublicationsController::class, 'show'],
            [],
            'publication_show',
        );

        $router->add(
            'GET',
            '/admin/publications',
            [PublicationsAdminController::class, 'index'],
            [RequireAdminMiddleware::class],
            'admin_publications',
        );
        $router->add(
            'GET',
            '/admin/publications/new',
            [PublicationsAdminController::class, 'createForm'],
            [RequireAdminMiddleware::class],
            'admin_publication_new',
        );
        $router->add(
            'POST',
            '/admin/publications',
            [PublicationsAdminController::class, 'create'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_create',
        );
        $router->add(
            'GET',
            '/admin/publications/{publicId}',
            [PublicationsAdminController::class, 'edit'],
            [RequireAdminMiddleware::class],
            'admin_publication_edit',
        );
        $router->add(
            'POST',
            '/admin/publications/{publicId}',
            [PublicationsAdminController::class, 'update'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_update',
        );
        $router->add(
            'POST',
            '/admin/publications/{publicId}/revisions/{revisionPublicId}/restore',
            [PublicationsAdminController::class, 'restoreRevision'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_revision_restore',
        );
        $router->add(
            'POST',
            '/admin/publications/{publicId}/publish',
            [PublicationsAdminController::class, 'publish'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_publish',
        );
        $router->add(
            'POST',
            '/admin/publications/{publicId}/schedule',
            [PublicationsAdminController::class, 'schedule'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_schedule',
        );
        $router->add(
            'POST',
            '/admin/publications/{publicId}/unschedule',
            [PublicationsAdminController::class, 'unschedule'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_unschedule',
        );
        $router->add(
            'POST',
            '/admin/publications/{publicId}/withdraw',
            [PublicationsAdminController::class, 'withdraw'],
            [RequireAdminMiddleware::class, CsrfMiddleware::class],
            'admin_publication_withdraw',
        );

        $router->add(
            'GET',
            '/api/v1/publications',
            [PublicationsApiController::class, 'index'],
            [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class],
            'api_v1_publications',
        );
        $router->add(
            'GET',
            '/api/v1/publications/aggregated',
            [PublicationsApiController::class, 'aggregated'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                ApiPublicRateLimitMiddleware::class,
            ],
            'api_v1_publications_aggregated',
        );

        $router->add(
            'GET',
            '/api/v1/publications/{slug}',
            [PublicationsApiController::class, 'show'],
            [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class],
            'api_v1_publication_show',
        );
        $router->add(
            'GET',
            '/api/v1/partner/publications',
            [PublicationsApiController::class, 'partnerIndex'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_publications',
        );

        $router->add(
            'GET',
            '/api/v1/partner/publications/tombstones',
            [PublicationsApiController::class, 'partnerTombstones'],
            [
                ApiEnabledMiddleware::class,
                ApiCorsMiddleware::class,
                PartnerApiMiddleware::class,
                ApiPartnerRateLimitMiddleware::class,
            ],
            'api_v1_partner_publication_tombstones',
        );
    }
};
