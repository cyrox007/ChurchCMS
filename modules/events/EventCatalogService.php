<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use DateTimeImmutable;
use DateTimeZone;

final class EventCatalogService
{
    public function __construct(
        private readonly EventRepository $events,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            EventRepository::fromDatabase(),
        );
    }

    /** @return list<array<string,mixed>> */
    public function upcoming(
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        return array_map(
            $this->project(...),
            $this->events->publishedUpcoming(
                $siteKey,
                $limit,
            ),
        );
    }

    /** @return array<string,mixed>|null */
    public function detail(
        string $publicId,
        string $siteKey = 'default',
    ): ?array {
        $event = $this->events->findByPublicId(
            trim($publicId),
            $siteKey,
        );

        if ($event === null || $event->status !== 'published') {
            return null;
        }

        return $this->project($event);
    }

    /**
     * @return array{
     *   month:string,
     *   days:array<string,list<array<string,mixed>>>
     * }
     */
    public function month(
        string $month,
        string $siteKey = 'default',
    ): array {
        $start = DateTimeImmutable::createFromFormat(
            '!Y-m',
            trim($month),
            new DateTimeZone('UTC'),
        );

        if (
            $start === false
            || $start->format('Y-m') !== trim($month)
        ) {
            $start = new DateTimeImmutable(
                'first day of this month 00:00:00',
                new DateTimeZone('UTC'),
            );
        }

        $end = $start->modify('first day of next month');
        $rows = $this->events->publishedBetween(
            $start,
            $end,
            $siteKey,
            500,
        );
        $days = [];

        foreach ($rows as $event) {
            $date = substr($event->startsAt, 0, 10);
            $days[$date] ??= [];
            $days[$date][] = $this->project($event);
        }

        return [
            'month' => $start->format('Y-m'),
            'days' => $days,
        ];
    }

    /** @return array<string,mixed> */
    private function project(Event $event): array
    {
        return [
            'id' => $event->publicId,
            'type' => 'event',
            'title' => $event->title,
            'starts_at' => $event->startsAt,
            'ends_at' => $event->endsAt,
            'all_day' => $event->allDay,
            'location_name' => $event->locationName,
            'excerpt' => $event->excerpt,
            'description' => $event->descriptionHtml,
            'organization_owner_id' =>
                $event->ownerOrganizationPublicId,
            'url' => '/events/' . rawurlencode(
                $event->publicId,
            ),
            'updated_at' => $event->updatedAt,
        ];
    }
}
