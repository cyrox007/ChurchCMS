<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Redirects;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;

final class RedirectRequestInterceptor
{
    public function handle(
        Request $request,
        string $requestPath,
    ): void {
        if (!in_array(
            $request->method(),
            ['GET', 'HEAD'],
            true,
        )) {
            return;
        }

        $service = RedirectService::fromDatabase();
        $rule = $service->match($requestPath);

        if ($rule === null) {
            return;
        }

        $service->recordHit($rule);

        Response::redirectLocal(
            $rule->targetPath,
            $rule->statusCode,
        );
    }
}
