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
    public function index(Request $request): never
    {
        $repository = PublicationRepository::fromDatabase();
        $page = max(1, (int) $request->get('page', 1));
        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $publications = $repository->published(
            'default',
            $perPage,
            $offset,
        );
        $items = $this->resources($publications);

        $total = $repository->countPublished('default');

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
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1) {
            ApiResponse::error('publication_not_found', 'Publication not found.', 404);
        }

        $publication = PublicationRepository::fromDatabase()->findPublishedBySlug($slug);
        if ($publication === null) {
            ApiResponse::error('publication_not_found', 'Publication not found.', 404);
        }

        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublication($publication->id);

        ApiResponse::success(
            (new PublicationApiResource(
                $publication,
                $taxonomy,
            ))->toApiArray(),
            cacheSeconds: 60,
        );
    }

    public function partnerIndex(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        $repository = PublicationRepository::fromDatabase();
        $limit = max(1, min(100, (int) $request->get('limit', 100)));
        $updatedSinceRaw = trim((string) $request->get('updated_since', ''));

        if ($updatedSinceRaw === '') {
            $scan = $repository->published('default', $limit, 0);
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

            $scan = $repository->publishedUpdatedSince($updatedSince, 'default', $limit);
        }

        $publications = array_values(array_filter(
            $scan,
            static fn(Publication $publication): bool =>
                in_array('diocese', $publication->syndicationTargets, true),
        ));

        $items = $this->resources($publications);

        $lastUpdatedAt = null;
        if ($scan !== []) {
            $last = $scan[array_key_last($scan)];
            $lastUpdatedAt = $last->updatedAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM);
        }

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== '' ? $updatedSinceRaw : null,
                'next_updated_since' => $lastUpdatedAt,
                'limit' => $limit,
                'has_more' => count($scan) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }

    public function partnerTombstones(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        $limit = max(
            1,
            min(100, (int) $request->get('limit', 100)),
        );
        $updatedSinceRaw = trim(
            (string) $request->get('updated_since', '')
        );
        $afterPublicId = trim(
            (string) $request->get('after', '')
        );
        $afterPublicId = $afterPublicId !== ''
            ? $afterPublicId
            : null;

        if (
            $afterPublicId !== null
            && preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $afterPublicId,
            ) !== 1
        ) {
            ApiResponse::error(
                'invalid_tombstone_cursor',
                'after must be a valid publication public ID.',
                400,
            );
        }

        if ($updatedSinceRaw === '') {
            $updatedSince = new DateTimeImmutable(
                '1970-01-01T00:00:00Z',
            );
        } else {
            try {
                $updatedSince = new DateTimeImmutable(
                    $updatedSinceRaw,
                );
            } catch (Exception) {
                ApiResponse::error(
                    'invalid_updated_since',
                    'updated_since must be a valid ISO-8601 timestamp.',
                    400,
                );
            }
        }

        $tombstones = PublicationPartnerTombstoneRepository
            ::fromDatabase()
            ->updatedSince(
                $updatedSince,
                'default',
                $limit,
                $afterPublicId,
            );

        $items = array_map(
            static fn(array $tombstone): array => [
                'action' => 'delete',
                'id' => $tombstone['publication_public_id'],
                'organization_owner_id' =>
                    $tombstone['organization_owner_public_id'],
                'reason' => $tombstone['reason'],
                'deleted_at' => $tombstone['withdrawn_at']
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM),
                'updated_at' => $tombstone['updated_at']
                    ->setTimezone(new \DateTimeZone('UTC'))
                    ->format(DATE_ATOM),
            ],
            $tombstones,
        );

        $nextUpdatedSince = null;
        $nextAfter = null;
        if ($tombstones !== []) {
            $last = $tombstones[array_key_last($tombstones)];
            $nextUpdatedSince = $last['updated_at']
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format(DATE_ATOM);
            $nextAfter = $last['publication_public_id'];
        }

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== ''
                    ? $updatedSinceRaw
                    : null,
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'limit' => $limit,
                'has_more' => count($tombstones) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }

    /**
     * @param list<Publication> $publications
     * @return list<array<string,mixed>>
     */
    private function resources(array $publications): array
    {
        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublications(array_map(
                static fn(Publication $publication): int =>
                    $publication->id,
                $publications,
            ));

        return array_map(
            static fn(Publication $publication): array =>
                (new PublicationApiResource(
                    $publication,
                    $taxonomy[$publication->id] ?? [],
                ))->toApiArray(),
            $publications,
        );
    }

}
