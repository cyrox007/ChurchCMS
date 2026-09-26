<?php

declare(strict_types=1);

use ChurchCMS\App\Controllers\HealthController;
use ChurchCMS\App\Controllers\ThemeAssetController;
use ChurchCMS\Core\Router;

$router = Router::getInstance();

$router->add('GET', '/', [HealthController::class, 'index'], [], 'home');
$router->add('GET', '/health', [HealthController::class, 'health'], [], 'health');
$router->add('GET', '/_theme-asset', [ThemeAssetController::class, 'asset'], [], 'theme_asset');
