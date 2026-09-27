<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class AuthorizationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function hasPermission(int $userId, string $permission): bool
    {
        if ($userId <= 0 || $permission === '') {
            return false;
        }

        if ($this->isSuperadmin($userId)) {
            return true;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM admin_user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = :user_id
               AND p.permission_key = :permission
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Возвращает scope keys для разрешения в конкретном измерении.
     *
     * null означает глобальное разрешение без scope-ограничений.
     * Пустой массив означает, что разрешения в указанном scope нет.
     *
     * Если у назначения роли нет ни одной scope-записи, оно глобальное.
     * Если хотя бы один scope у назначения есть, действуют только явно
     * совпавшие site/type scope-записи.
     *
     * @return list<string>|null
     */
    public function permissionScopeKeys(
        int $userId,
        string $permission,
        string $siteKey,
        string $scopeType,
    ): ?array {
        $permission = trim($permission);
        $siteKey = trim($siteKey);
        $scopeType = trim($scopeType);

        if (
            $userId <= 0
            || $permission === ''
            || $siteKey === ''
            || $scopeType === ''
        ) {
            return [];
        }

        if ($this->isSuperadmin($userId)) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT
                ur.role_id,
                s.site_key,
                s.scope_type,
                s.scope_key
             FROM admin_user_roles ur
             INNER JOIN role_permissions rp
                ON rp.role_id = ur.role_id
             INNER JOIN permissions p
                ON p.id = rp.permission_id
             LEFT JOIN admin_role_scopes s
                ON s.user_id = ur.user_id
               AND s.role_id = ur.role_id
             WHERE ur.user_id = :user_id
               AND p.permission_key = :permission
             ORDER BY ur.role_id, s.site_key, s.scope_type, s.scope_key'
        );
        $statement->execute([
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        $roles = [];

        foreach ($statement->fetchAll() as $row) {
            $roleId = (int) ($row['role_id'] ?? 0);
            if ($roleId <= 0) {
                continue;
            }

            $roles[$roleId] ??= [
                'has_any_scope' => false,
                'keys' => [],
            ];

            $scopeKey = $row['scope_key'] ?? null;
            if (!is_string($scopeKey) || $scopeKey === '') {
                continue;
            }

            $roles[$roleId]['has_any_scope'] = true;

            if (
                (string) ($row['site_key'] ?? '') !== $siteKey
                || (string) ($row['scope_type'] ?? '')
                    !== $scopeType
            ) {
                continue;
            }

            $roles[$roleId]['keys'][$scopeKey] = true;
        }

        $keys = [];

        foreach ($roles as $role) {
            if ($role['has_any_scope'] === false) {
                return null;
            }

            foreach ($role['keys'] as $key => $_) {
                $keys[$key] = true;
            }
        }

        $result = array_keys($keys);
        sort($result, SORT_STRING);

        return $result;
    }

    private function isSuperadmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM admin_user_roles ur
             INNER JOIN roles r
                ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND r.role_key = :role
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'role' => 'superadmin',
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return list<string>
     */
    public function roles(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.role_key
             FROM admin_user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.role_key'
        );
        $statement->execute(['user_id' => $userId]);

        return array_values(array_map(
            static fn(array $row): string => (string) $row['role_key'],
            $statement->fetchAll(),
        ));
    }
}
