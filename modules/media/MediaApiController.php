<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\ApiAccess;
use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class MediaApiController
{
    public function aggregated(Request $request): never
    {
        $limit = max(
            1,
            min(50, (int) $request->get('limit', 20)),
        );
        $items = FederatedMediaFeedService::fromDatabase()
            ->latest('default', $limit);
        $remote = count(array_filter(
            $items,
            static fn(array $item): bool =>
                ($item['source']['kind'] ?? null)
                === 'federation',
        ));

        ApiResponse::success(
            $items,
            [
                'aggregation' => [
                    'limit' => $limit,
                    'local' => count($items) - $remote,
                    'remote' => $remote,
                    'blob_available' => false,
                ],
            ],
            cacheSeconds: 60,
        );
    }

    public function partnerIndex(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        [$updatedSince, $updatedSinceRaw, $afterPublicId] =
            $this->cursor(
                $request,
                'invalid_media_cursor',
                'медиаматериала',
            );
        $limit = $this->limit($request);

        $assets = MediaRepository::fromDatabase()
            ->federatedUpdatedSince(
                $updatedSince,
                'default',
                $limit,
                $afterPublicId,
            );

        $items = array_map(
            static fn(MediaAsset $asset): array =>
                (new MediaApiResource($asset))->toApiArray(),
            $assets,
        );

        [$nextUpdatedSince, $nextAfter] =
            $this->nextAssetCursor($assets);

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== ''
                    ? $updatedSinceRaw
                    : null,
                'after' => $afterPublicId,
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'limit' => $limit,
                'has_more' => count($assets) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }

    public function partnerTombstones(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        [$updatedSince, $updatedSinceRaw, $afterPublicId] =
            $this->cursor(
                $request,
                'invalid_media_tombstone_cursor',
                'tombstone медиаматериала',
            );
        $limit = $this->limit($request);

        $tombstones =
            MediaPartnerTombstoneRepository::fromDatabase()
                ->updatedSince(
                    $updatedSince,
                    'default',
                    $limit,
                    $afterPublicId,
                );

        $items = array_map(
            static fn(array $tombstone): array =>
                (new MediaPartnerTombstoneApiResource(
                    $tombstone,
                ))->toApiArray(),
            $tombstones,
        );

        $nextUpdatedSince = null;
        $nextAfter = null;

        if ($tombstones !== []) {
            $last = $tombstones[array_key_last($tombstones)];
            $nextUpdatedSince = $last['updated_at']
                ->format(DATE_ATOM);
            $nextAfter = $last['media_public_id'];
        }

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== ''
                    ? $updatedSinceRaw
                    : null,
                'after' => $afterPublicId,
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'limit' => $limit,
                'has_more' => count($tombstones) === $limit,
            ],
            'partner' => ApiAccess::partnerId($request),
        ]);
    }

    private function limit(Request $request): int
    {
        return max(
            1,
            min(100, (int) $request->get('limit', 100)),
        );
    }

    /**
     * @return array{0:DateTimeImmutable,1:string,2:?string}
     */
    private function cursor(
        Request $request,
        string $errorCode,
        string $label,
    ): array {
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
                $errorCode,
                'after должен содержать корректный public ID '
                . $label . '.',
                400,
            );
        }

        if ($updatedSinceRaw === '') {
            return [
                new DateTimeImmutable(
                    '1970-01-01T00:00:00Z',
                ),
                '',
                $afterPublicId,
            ];
        }

        try {
            $updatedSince = new DateTimeImmutable(
                $updatedSinceRaw,
            );
        } catch (Exception) {
            ApiResponse::error(
                'invalid_updated_since',
                'updated_since должен содержать корректное '
                . 'время ISO-8601.',
                400,
            );
        }

        return [
            $updatedSince,
            $updatedSinceRaw,
            $afterPublicId,
        ];
    }

    /**
     * @param list<MediaAsset> $assets
     * @return array{0:?string,1:?string}
     */
    private function nextAssetCursor(array $assets): array
    {
        if ($assets === []) {
            return [null, null];
        }

        $last = $assets[array_key_last($assets)];

        return [
            (new DateTimeImmutable(
                $last->updatedAt,
                new DateTimeZone('UTC'),
            ))->format(DATE_ATOM),
            $last->publicId,
        ];
    }
}
