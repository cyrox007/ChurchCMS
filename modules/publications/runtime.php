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
use ChurchCMS\Modules\Publications\PublicationSyndicationProvider;
use ChurchCMS\Modules\Publications\PublicationsApiController;
use ChurchCMS\Modules\Publications\PublicationsCapability;
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
    'PublicationsCapability.php',
    'PublicationsController.php',
    'PublicationsApiController.php',
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
            'syndication.publications' => $capability,
        ];
    }

    public function boot(): void
    {
        $this->capability = new PublicationsCapability();

        // Registration is lazy: no DB connection is made during module boot.
        SyndicationRegistry::register(
            'publications',
            new PublicationSyndicationProvider(),
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
