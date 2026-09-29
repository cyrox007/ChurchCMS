<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\ApiResource;
use DateTimeImmutable;
use DateTimeZone;

final class WorshipApiResource implements ApiResource
{
    public function __construct(
        private readonly WorshipService $service,
    ) {
    }

    /** @return array<string,mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->service->publicId,
            'type' => 'worship',
            'status' => $this->service->status,
            'title' => $this->service->title,
            'service_type' => $this->service->serviceType,
            'organization_owner_id' =>
                $this->service->ownerOrganizationPublicId,
            'starts_at' => self::timestamp(
                $this->service->startsAt,
            ),
            'ends_at' => $this->service->endsAt !== null
                ? self::timestamp($this->service->endsAt)
                : null,
            'location' => $this->service->locationName,
            'updated_at' => self::timestamp(
                $this->service->updatedAt,
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
