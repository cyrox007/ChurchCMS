<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\Request;

final class ApiCorsMiddleware
{
    public function handle(Request $request): bool
    {
        $origin = trim((string) $request->header('Origin', ''));
        if ($origin === '') {
            return true;
        }

        $allowed = Config::get('api.allowed_origins', []);
        if (!is_array($allowed) || !in_array($origin, $allowed, true)) {
            return true;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Max-Age: 600');
        header('Vary: Origin');

        return true;
    }
}
