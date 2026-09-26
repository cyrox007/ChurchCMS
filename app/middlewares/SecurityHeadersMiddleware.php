<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\SecurityHeaders;

final class SecurityHeadersMiddleware
{
    public function handle(Request $request): bool
    {
        SecurityHeaders::apply();
        return true;
    }
}
