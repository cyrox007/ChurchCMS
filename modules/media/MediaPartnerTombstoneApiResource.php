<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\ApiResource;
use DateTimeImmutable;
use DateTimeZone;

final class MediaPartnerTombstoneApiResource implements ApiResource
{
    /**
     * @param array{
     *     media_public_id:string,
     *     organization_owner_public_id:string,
     *     reason:string,
     *     withdrawn_at:DateTimeImmutable,
     *     updated_at:DateTimeImmutable
     * } $tombstone
     */
    public function __construct(
        private readonly array $tombstone,
    ) {
    }

    /** @return array<string,mixed> */
    public function toApiArray(): array
    {
        return [
            'id' => $this->tombstone['media_public_id'],
            'type' => 'media',
            'action' => 'delete',
            'reason' => $this->tombstone['reason'],
            'organization_owner_id' =>
                $this->tombstone[
                    'organization_owner_public_id'
                ],
            'withdrawn_at' => $this->tombstone['withdrawn_at']
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM),
            'updated_at' => $this->tombstone['updated_at']
                ->setTimezone(new DateTimeZone('UTC'))
                ->format(DATE_ATOM),
        ];
    }
}
