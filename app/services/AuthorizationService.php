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

        $superadmin = $this->pdo->prepare(
            'SELECT 1
             FROM admin_user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND r.role_key = :role
             LIMIT 1'
        );
        $superadmin->execute([
            'user_id' => $userId,
            'role' => 'superadmin',
        ]);

        if ($superadmin->fetchColumn() !== false) {
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
