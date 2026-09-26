<?php

declare(strict_types=1);

use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\Core\ModuleRuntimeProvider;
use ChurchCMS\Core\Router;
use ChurchCMS\Core\SyndicationRegistry;
use ChurchCMS\Modules\Publications\PublicationRepository;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Publications\PublicationSyndicationProvider;
use ChurchCMS\Modules\Publications\PublicationsApiController;
use ChurchCMS\Modules\Publications\PublicationsController;

$moduleRoot = __DIR__;
foreach ([
    'PublicationStatus.php',
    'PublicationType.php',
    'Publication.php',
    'PublicationRepository.php',
    'PublicationService.php',
    'PublicationApiResource.php',
    'PublicationSyndicationProvider.php',
    'PublicationsController.php',
    'PublicationsApiController.php',
] as $file) {
    require_once $moduleRoot . '/' . $file;
}

return new class implements ModuleRuntimeProvider {
    private ?PublicationRepository $repository = null;
    private ?PublicationService $service = null;

    public function moduleId(): string
    {
        return 'publications';
    }

    public function capabilities(): array
    {
        return [
            'publications.read' => $this->repository ?? PublicationRepository::fromDatabase(),
            'syndication.publications' => $this->service ?? PublicationService::fromDatabase(),
        ];
    }

    public function boot(): void
    {
        $this->repository = PublicationRepository::fromDatabase();
        $this->service = PublicationService::fromDatabase();

        SyndicationRegistry::register(
            'publications',
            new PublicationSyndicationProvider($this->repository),
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
            '/api/v1/publications',
            [PublicationsApiController::class, 'index'],
            [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class],
            'api_v1_publications',
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
    }
};
