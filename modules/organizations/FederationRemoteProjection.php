<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use DateTimeImmutable;

final readonly class FederationRemoteProjection
{
    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public int $federationLinkId,
        public string $objectType,
        public string $remotePublicId,
        public ?string $remoteOwnerOrganizationPublicId,
        public ?string $canonicalUrl,
        public string $state,
        public ?string $deleteReason,
        public array $payload,
        public DateTimeImmutable $remoteUpdatedAt,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isDeleted(): bool
    {
        return $this->state === 'deleted';
    }
}
