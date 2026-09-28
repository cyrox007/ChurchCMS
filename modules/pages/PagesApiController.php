<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;

final class PagesApiController
{
    public function index(Request $request): never
    {
        $repository = PageRepository::fromDatabase();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(
            1,
            min(100, (int) $request->get('per_page', 50)),
        );
        $offset = ($page - 1) * $perPage;
        $items = $this->resources($repository->published(
            limit: $perPage,
            offset: $offset,
        ));
        $total = $repository->countPublished();

        ApiResponse::success($items, [
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $offset + count($items) < $total,
            ],
        ], cacheSeconds: 60);
    }

    public function show(Request $request, string $publicId): never
    {
        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di',
                $publicId,
            ) !== 1
        ) {
            ApiResponse::error(
                'page_not_found',
                'Page not found.',
                404,
            );
        }

        $repository = PageRepository::fromDatabase();
        $page = $repository->findPublishedByPublicId($publicId);
        if ($page === null) {
            ApiResponse::error(
                'page_not_found',
                'Page not found.',
                404,
            );
        }

        ApiResponse::success(
            $this->resource($repository, $page)->toApiArray(),
            cacheSeconds: 60,
        );
    }

    /**
     * @param list<Page> $pages
     * @return list<array<string,mixed>>
     */
    private function resources(array $pages): array
    {
        $repository = PageRepository::fromDatabase();
        $parentIds = array_values(array_filter(array_map(
            static fn(Page $page): ?int => $page->parentId,
            $pages,
        )));
        $parentPublicIds = $repository->publicIdsByIds(
            $parentIds,
        );

        return array_map(
            static fn(Page $page): array =>
                (new PageApiResource(
                    $page,
                    $page->parentId !== null
                        ? ($parentPublicIds[$page->parentId] ?? null)
                        : null,
                ))->toApiArray(),
            $pages,
        );
    }

    private function resource(
        PageRepository $repository,
        Page $page,
    ): PageApiResource {
        $parentPublicId = $page->parentId !== null
            ? ($repository->publicIdsByIds([
                $page->parentId,
            ])[$page->parentId] ?? null)
            : null;

        return new PageApiResource($page, $parentPublicId);
    }
}
