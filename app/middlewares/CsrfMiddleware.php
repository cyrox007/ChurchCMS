<?php

declare(strict_types=1);

namespace ChurchCMS\App\Middlewares;

use ChurchCMS\Core\Csrf;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class CsrfMiddleware
{
    public function handle(Request $request): bool
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $token = $request->post('csrf_token')
            ?? $request->header('X-CSRF-Token')
            ?? $request->header('X-XSRF-Token');

        if (!Csrf::validate($token)) {
            if (str_contains(strtolower((string) $request->header('Accept', '')), 'application/json')) {
                Response::json([
                    'error' => 'csrf_failed',
                    'message' => 'Invalid or expired CSRF token.',
                ], 403);
            }

            Response::text('403 CSRF validation failed', 403);
        }

        return true;
    }
}
