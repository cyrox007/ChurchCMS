<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\ApiAccess;
use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;
use DateTimeImmutable;
use Exception;

final class PublicationsApiController
{
    public function __construct(
        private readonly PublicationRepository $repository,
    ) {
    }

    public function index(Request $request): never
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $items = array_map(
            static fn(Publication $publication): array =>
                (new PublicationApiResource($publication))->toApiArray(),
            $this->repository->published('default', $perPage, $offset),
        );

        $total = $this->repository->countPublished('default');

        ApiResponse::success($items, [
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $offset + count($items) < $total,
            ],
        ], cacheSeconds: 60);
    }

    public function show(Request $request, string $slug): never
    {
        $publication = $this->repository->findPublishedBySlug($slug);
        if ($publication === null) {
            ApiResponse::error('publication_not_found', 'Publication not found.', 404);
        }

        ApiResponse::success(
            (new PublicationApiResource($publication))->toApiArray(),
            cacheSeconds: 60,
        );
    }

    public function partnerIndex(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        $limit = max(1, min(100, (int) $request->get('limit', 100)));
        $updatedSinceRaw = trim((string) $request->get('updated_since', ''));

        if ($updatedSinceRaw === '') {
            $publications = $this->repository->published('default', $limit, 0);
        } else {
            try {
                $updatedSince = new DateTimeImmutable($updatedSinceRaw);
            } catch (Exception) {
                ApiResponse::error(
                    'invalid_updated_since',
                    'updated_since must be a valid ISO-8601 timestamp.',
                    400,
                );
            }

            $publications = $this->repository->publishedUpdatedSince($updatedSince, 'default', $limit);
        }

        $items = array_map(
            static fn(Publication $publication): array =>
                (new PublicationApiResource($publication))->toApiArray(),
            $publications,
        );

        $lastUpdatedAt = null;
        if ($publications !== []) {
            $last = $publications[array_key_last($publications)];
            $lastUpdatedAt = $last->updatedAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        }

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== '' ? $updatedSinceRaw : null,
                'next_updated_since' => $lastUpdatedAt,
                'limit' => $limit,
                'has_more' => count($items) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }
}
