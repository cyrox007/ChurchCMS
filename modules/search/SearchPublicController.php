<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Search;

use ChurchCMS\Core\Request;
use ChurchCMS\Core\Response;
use ChurchCMS\Core\ThemeRenderer;
use InvalidArgumentException;

final class SearchPublicController
{
    public function index(Request $request): never
    {
        $query = (string) $request->get('q', '');
        $result = [
            'query' => trim($query),
            'items' => [],
        ];
        $error = null;

        if (trim($query) !== '') {
            try {
                $result = PublicSearchService::fromDatabase()
                    ->search($query);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        Response::html(
            ThemeRenderer::fromConfig()->render(
                'search.index',
                [
                    'search' => $result,
                    'searchError' => $error,
                ],
            ),
        );
    }
}
