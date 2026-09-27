<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\App\Services\AuthorizationService;

final class OrganizationAccessService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            AuthorizationService::fromDatabase(),
            OrganizationRepository::fromDatabase(),
        );
    }

    /**
     * null означает глобальный доступ.
     *
     * @return list<OrganizationUnit>|null
     */
    public function scopeRoots(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): ?array {
        $keys = $this->authorization->permissionScopeKeys(
            $userId,
            $permission,
            $siteKey,
            'organization',
        );

        if ($keys === null) {
            return null;
        }

        $roots = [];

        foreach ($keys as $publicId) {
            $unit = $this->organizations->findByPublicId(
                $publicId,
                $siteKey,
            );

            if ($unit !== null) {
                $roots[$unit->id] = $unit;
            }
        }

        return array_values($roots);
    }

    public function can(
        int $userId,
        string $permission,
        OrganizationUnit $unit,
    ): bool {
        $roots = $this->scopeRoots(
            $userId,
            $permission,
            $unit->siteKey,
        );

        if ($roots === null) {
            return true;
        }

        foreach ($roots as $root) {
            if (self::contains($root, $unit)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<OrganizationUnit>
     */
    public function visibleTree(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): array {
        $tree = $this->organizations->tree($siteKey);
        $roots = $this->scopeRoots(
            $userId,
            $permission,
            $siteKey,
        );

        if ($roots === null) {
            return $tree;
        }

        if ($roots === []) {
            return [];
        }

        return array_values(array_filter(
            $tree,
            static function (
                OrganizationUnit $unit
            ) use ($roots): bool {
                foreach ($roots as $root) {
                    if (self::contains($root, $unit)) {
                        return true;
                    }
                }

                return false;
            },
        ));
    }

    public function defaultCreateParent(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): ?OrganizationUnit {
        $roots = $this->scopeRoots(
            $userId,
            $permission,
            $siteKey,
        );

        if ($roots === null) {
            return $this->organizations->siteRoot(
                $siteKey,
            );
        }

        return count($roots) === 1
            ? $roots[0]
            : null;
    }

    public function isGlobal(
        int $userId,
        string $permission,
        string $siteKey = 'default',
    ): bool {
        return $this->scopeRoots(
            $userId,
            $permission,
            $siteKey,
        ) === null;
    }

    private static function contains(
        OrganizationUnit $root,
        OrganizationUnit $candidate,
    ): bool {
        if ($root->siteKey !== $candidate->siteKey) {
            return false;
        }

        return $candidate->id === $root->id
            || str_starts_with(
                $candidate->path,
                rtrim($root->path, '/') . '/',
            );
    }
}
