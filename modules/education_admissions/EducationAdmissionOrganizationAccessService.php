<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\EducationAdmissions;

use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;

final class EducationAdmissionOrganizationAccessService
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

    /** @return list<string>|null */
    public function visibleOwnerPublicIds(int $userId, string $permission, string $siteKey = 'default'): ?array
    {
        if ($this->access->isGlobal($userId, $permission, $siteKey)) {
            return null;
        }

        return array_values(array_map(
            static fn($unit): string => $unit->publicId,
            array_filter(
                $this->access->visibleTree($userId, $permission, $siteKey),
                static fn($unit): bool => $unit->status === 'active',
            ),
        ));
    }

    public function canAccess(int $userId, string $permission, EducationAdmissionRecord $admission): bool
    {
        $owner = $this->organizations->findByPublicId(
            $admission->programOwnerOrganizationPublicId,
            $admission->siteKey,
        );

        return $owner !== null && $this->access->can($userId, $permission, $owner);
    }
}
