<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\ApiResource;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Минимальная внешняя проекция удаления публикации.
 *
 * @phpstan-type Tombstone array{
 *     publication_public_id:string,
 *     organization_owner_public_id:?string,
 *     reason:string,
 *     withdrawn_at:DateTimeImmutable,
 *     updated_at:DateTimeImmutable
 * }
 */
final class PublicationPartnerTombstoneApiResource implements ApiResource
{
    /**
     * @param array{
     *     publication_public_id:string,
     *     organization_owner_public_id:?string,
     *     reason:string,
     *     withdrawn_at:DateTimeImmutable,
     *     updated_at:DateTimeImmutable
     * } $tombstone
     */
    public function __construct(
        private readonly array $tombstone,
    ) {
    }

    public function toApiArray(): array
    {
        return [
            'action' => 'delete',
            'id' => $this->tombstone['publication_public_id'],
            'organization_owner_id' =>
                $this->tombstone['organization_owner_public_id'],
            'reason' => $this->tombstone['reason'],
            'deleted_at' => $this->utc(
                $this->tombstone['withdrawn_at'],
            ),
            'updated_at' => $this->utc(
                $this->tombstone['updated_at'],
            ),
        ];
    }

    private function utc(DateTimeImmutable $value): string
    {
        return $value
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }
}
