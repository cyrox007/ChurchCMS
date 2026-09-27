<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_101000_add_organization_permissions';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $permissions = [
            'organizations.read' => 'Просмотр церковной структуры',
            'organizations.manage' => 'Управление церковной структурой',
        ];

        $find = $pdo->prepare(
            'SELECT id FROM permissions
             WHERE permission_key = :permission_key
             LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO permissions (
                permission_key,
                name
             ) VALUES (
                :permission_key,
                :name
             )'
        );

        foreach ($permissions as $key => $name) {
            $find->execute([
                'permission_key' => $key,
            ]);

            if ($find->fetchColumn() !== false) {
                continue;
            }

            $insert->execute([
                'permission_key' => $key,
                'name' => $name,
            ]);
        }
    }
};
