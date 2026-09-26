<?php

declare(strict_types=1);

namespace ChurchCMS\App\Controllers;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class HealthController
{
    public function index(Request $request): never
    {
        Response::json([
            'name' => Config::get('app.name'),
            'version' => Config::get('app.version'),
            'php_min' => Config::get('app.php_min'),
            'status' => 'development',
        ]);
    }

    public function health(Request $request): never
    {
        Response::json(['status' => 'ok']);
    }
}
