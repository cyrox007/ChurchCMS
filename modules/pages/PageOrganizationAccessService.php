<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Modules\Organizations\OrganizationAccessService;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;

final class PageOrganizationAccessService
{
    public function __construct(
        private readonly OrganizationAccessService $access,
        private readonly OrganizationRepository $organizations,
        private readonly PageRepository $pages,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            OrganizationAccessService::fromDatabase(),
            OrganizationRepository::fromDatabase(),
            PageRepository::fromDatabase(),
        );
    }

    /**
     * @return list<OrganizationUnit>
     */
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

    /**
     * null означает глобальный доступ без owner-фильтра.
     *
     * @return list<string>|null
     */
    public function visibleOwnerPublicIds(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): ?array {
        if ($this->access->isGlobal($userId, $permission, $siteKey)) {
            return null;
        }

        return array_map(
            static fn(OrganizationUnit $unit): string =>
                $unit->publicId,
            $this->access->visibleTree(
                $userId,
                $permission,
                $siteKey,
            ),
        );
    }

    public function assignableOwner(
        int $userId,
        string $permission,
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
        Page $page,
    ): bool {
        if ($page->ownerOrganizationPublicId === null) {
            return $this->access->isGlobal(
                $userId,
                $permission,
                $page->siteKey,
            );
        }

        $owner = $this->organizations->findByPublicId(
            $page->ownerOrganizationPublicId,
            $page->siteKey,
        );

        return $owner !== null
            && $this->access->can(
                $userId,
                $permission,
                $owner,
            );
    }

    /**
     * Перемещение и снятие с публикации затрагивают всё поддерево,
     * поэтому полномочия должны покрывать каждый его узел.
     */
    public function canAccessSubtree(
        int $userId,
        string $permission,
        Page $root,
    ): bool {
        foreach ($this->pages->subtree($root) as $page) {
            if (!$this->canAccess($userId, $permission, $page)) {
                return false;
            }
        }

        return true;
    }
}
