<?php

declare(strict_types=1);

use ChurchCMS\App\Controllers\HealthController;
use ChurchCMS\App\Controllers\ThemeAssetController;
use ChurchCMS\App\Controllers\Api\V1\ExternalApiController;
use ChurchCMS\App\Middlewares\ApiCorsMiddleware;
use ChurchCMS\App\Middlewares\ApiEnabledMiddleware;
use ChurchCMS\App\Middlewares\ApiPublicRateLimitMiddleware;
use ChurchCMS\App\Middlewares\PartnerApiMiddleware;
use ChurchCMS\App\Middlewares\ApiPartnerRateLimitMiddleware;
use ChurchCMS\Core\Router;

$router = Router::getInstance();

$router->add('GET', '/', [HealthController::class, 'index'], [], 'home');
$router->add('GET', '/health', [HealthController::class, 'health'], [], 'health');
$router->add('GET', '/_theme-asset', [ThemeAssetController::class, 'asset'], [], 'theme_asset');


$router->group('/api/v1')
    ->add('GET', '/meta', [ExternalApiController::class, 'meta'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, ApiPublicRateLimitMiddleware::class], 'api_v1_meta')
    ->add('OPTIONS', '/meta', [ExternalApiController::class, 'preflight'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class], 'api_v1_meta_options')
    ->add('GET', '/partner/ping', [ExternalApiController::class, 'partnerPing'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class, PartnerApiMiddleware::class, ApiPartnerRateLimitMiddleware::class], 'api_v1_partner_ping')
    ->add('OPTIONS', '/partner/ping', [ExternalApiController::class, 'preflight'], [ApiEnabledMiddleware::class, ApiCorsMiddleware::class], 'api_v1_partner_ping_options')
    ->endGroup();
