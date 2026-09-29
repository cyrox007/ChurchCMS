<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\Core\ApiAccess;
use ChurchCMS\Core\ApiResponse;
use ChurchCMS\Core\Request;
use DateTimeImmutable;
use Exception;

final class EventsApiController
{
    public function partnerIndex(Request $request): never
    {
        ApiAccess::requireScope($request, 'content.read');

        [$updatedSince, $updatedSinceRaw, $afterPublicId] =
            $this->cursor(
                $request,
                'invalid_event_cursor',
                'события',
            );
        $limit = $this->limit($request);

        $events = EventRepository::fromDatabase()
            ->publishedUpdatedSince(
                $updatedSince,
                'default',
                $limit,
                $afterPublicId,
            );

        $items = array_map(
            static fn(Event $event): array =>
                (new EventApiResource($event))->toApiArray(),
            $events,
        );

        [$nextUpdatedSince, $nextAfter] =
            $this->nextEventCursor($events);

        ApiResponse::success($items, [
            'sync' => [
                'updated_since' => $updatedSinceRaw !== ''
                    ? $updatedSinceRaw
                    : null,
                'after' => $afterPublicId,
                'next_updated_since' => $nextUpdatedSince,
                'next_after' => $nextAfter,
                'limit' => $limit,
                'has_more' => count($events) === $limit,
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
                'invalid_event_tombstone_cursor',
                'tombstone события',
            );
        $limit = $this->limit($request);

        $tombstones =
            EventPartnerTombstoneRepository::fromDatabase()
                ->updatedSince(
                    $updatedSince,
                    'default',
                    $limit,
                    $afterPublicId,
                );

        $items = array_map(
            static fn(array $tombstone): array =>
                (new EventPartnerTombstoneApiResource(
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
            $nextAfter = $last['event_public_id'];
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
     * @param list<Event> $events
     * @return array{0:?string,1:?string}
     */
    private function nextEventCursor(array $events): array
    {
        if ($events === []) {
            return [null, null];
        }

        $last = $events[array_key_last($events)];

        return [
            (new DateTimeImmutable($last->updatedAt))
                ->format(DATE_ATOM),
            $last->publicId,
        ];
    }
}
