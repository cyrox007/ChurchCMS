<?php

declare(strict_types=1);

use ChurchCMS\App\Controllers\HealthController;
use ChurchCMS\Core\Router;

$router = Router::getInstance();

$router->add('GET', '/', [HealthController::class, 'index'], [], 'home');
$router->add('GET', '/health', [HealthController::class, 'health'], [], 'health');
