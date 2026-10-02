<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;

final class WorshipOrganizationAccessService
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

    /** @return list<OrganizationUnit> */
    public function availableOwners(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): array {
        return array_values(array_filter(
            $this->access->visibleTree(
                $userId,
                $permission,
                $siteKey,
            ),
            static fn(OrganizationUnit $unit): bool =>
                $unit->status === 'active',
        ));
    }

    /** @return list<string>|null */
    public function visibleOwnerPublicIds(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): ?array {
        if ($this->access->isGlobal(
            $userId,
            $permission,
            $siteKey,
        )) {
            return null;
        }

        return array_map(
            static fn(OrganizationUnit $unit): string =>
                $unit->publicId,
            $this->availableOwners(
                $userId,
                $permission,
                $siteKey,
            ),
        );
    }

    public function defaultOwnerPublicId(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): string {
        $owner = $this->access->defaultCreateParent(
            $userId,
            $permission,
            $siteKey,
        );

        return $owner !== null && $owner->status === 'active'
            ? $owner->publicId
            : '';
    }

    public function assignableOwner(
        int $userId,
        string $permission,
        string $ownerPublicId,
        string $siteKey = 'default',
    ): ?OrganizationUnit {
        $owner = $this->organizations->findByPublicId(
            trim($ownerPublicId),
            $siteKey,
        );

        if (
            $owner === null
            || $owner->status !== 'active'
            || !$this->access->can(
                $userId,
                $permission,
                $owner,
            )
        ) {
            return null;
        }

        return $owner;
    }

    public function canAccess(
        int $userId,
        string $permission,
        WorshipService $service,
    ): bool {
        $owner = $this->organizations->findByPublicId(
            $service->ownerOrganizationPublicId,
            $service->siteKey,
        );

        return $owner !== null
            && $this->access->can(
                $userId,
                $permission,
                $owner,
            );
    }
}
