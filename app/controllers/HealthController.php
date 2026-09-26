<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\InstallationHealthCheck;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;

final class HealthController
{
    public function index(Request $request): never
    {
        ThemeRenderer::fromConfig()->page('page.home', [
            'title' => 'ChurchCMS',
            'siteName' => 'ChurchCMS',
            'heading' => 'Современная CMS для приходов и духовных школ',
            'lead' => 'PHP 8.3+, собственное ядро и сменные темы без обязательных внешних зависимостей.',
        ]);
    }

    public function health(Request $request): never
    {
        $health = (new InstallationHealthCheck(
            DatabaseManager::getInstance(),
            CHURCHCMS_ROOT,
        ))->check();

        Response::json([
            'status' => $health['ready'] ? 'ok' : 'not_ready',
            'version' => Config::get('app.version'),
            'php_min' => Config::get('app.php_min'),
            'theme' => Config::get('theme.active'),
            'checks' => $health['checks'],
        ], $health['ready'] ? 200 : 503);
    }
}
