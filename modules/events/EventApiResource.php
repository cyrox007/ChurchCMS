<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Events;

use ChurchCMS\Core\ApiResource;
use DateTimeImmutable;
use DateTimeZone;

final class EventApiResource implements ApiResource
{
    public function __construct(
        private readonly Event $event,
    ) {
    }

    /**
     * Безопасная projection для federation/partner API.
     *
     * Сырой description_html намеренно не передаётся, пока Events
     * не получил отдельный HTML sanitizer/public rendering contract.
     *
     * @return array<string,mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->event->publicId,
            'type' => 'event',
            'title' => $this->event->title,
            'excerpt' => $this->event->excerpt,
            'organization_owner_id' =>
                $this->event->ownerOrganizationPublicId,
            'starts_at' => self::timestamp(
                $this->event->startsAt,
            ),
            'ends_at' => $this->event->endsAt !== null
                ? self::timestamp($this->event->endsAt)
                : null,
            'all_day' => $this->event->allDay,
            'location' => $this->event->locationName,
            'updated_at' => self::timestamp(
                $this->event->updatedAt,
            ),
            'url' => null,
        ];
    }

    private static function timestamp(string $value): string
    {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC'),
        ))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }
}
