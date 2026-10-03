<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Search;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;
use InvalidArgumentException;

final class SearchPublicApiController
{
    public function index(Request $request): never
    {
        try {
            ApiResponse::success(
                PublicSearchService::fromDatabase()->search(
                    (string) $request->get('q', ''),
                ),
                cacheSeconds: 30,
            );
        } catch (InvalidArgumentException $exception) {
            ApiResponse::error(
                'invalid_search_query',
                $exception->getMessage(),
                422,
            );
        }
    }
}
