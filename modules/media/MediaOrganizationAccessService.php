<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;

final class MediaOrganizationAccessService
{
    public function __construct(
        private readonly OrganizationAccessService $access,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            OrganizationAccessService::fromDatabase(),
            OrganizationRepository::fromDatabase(),
        );
    }

    /**
     * @return list<OrganizationUnit>
     */
    public function availableOwners(
        int $userId,
        string $siteKey = 'default',
    ): array {
        return array_values(array_filter(
            $this->access->visibleTree(
                $userId,
                'media.manage',
                $siteKey,
            ),
            static fn(OrganizationUnit $unit): bool =>
                $unit->status === 'active',
        ));
    }

    public function defaultOwnerPublicId(
        int $userId,
        string $siteKey = 'default',
    ): string {
        $owner = $this->access->defaultCreateParent(
            $userId,
            'media.manage',
            $siteKey,
        );

        return $owner !== null && $owner->status === 'active'
            ? $owner->publicId
            : '';
    }

    /**
     * null означает глобальный доступ.
     *
     * @return list<string>|null
     */
    public function visibleOwnerPublicIds(
        int $userId,
        string $siteKey = 'default',
    ): ?array {
        if ($this->access->isGlobal(
            $userId,
            'media.manage',
            $siteKey,
        )) {
            return null;
        }

        return array_map(
            static fn(OrganizationUnit $unit): string =>
                $unit->publicId,
            $this->availableOwners(
                $userId,
                $siteKey,
            ),
        );
    }

    public function assignableOwner(
        int $userId,
        string $ownerPublicId,
        string $siteKey = 'default',
    ): ?OrganizationUnit {
        $ownerPublicId = trim($ownerPublicId);
        if ($ownerPublicId === '') {
            return null;
        }

        $owner = $this->organizations->findByPublicId(
            $ownerPublicId,
            $siteKey,
        );

        if (
            $owner === null
            || $owner->status !== 'active'
            || !$this->access->can(
                $userId,
                'media.manage',
                $owner,
            )
        ) {
            return null;
        }

        return $owner;
    }
}
