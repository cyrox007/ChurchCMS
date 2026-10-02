<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261001_090000_add_worship_permissions';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $permissions = [
            'worship.read' => 'Просмотр расписания богослужений в админке',
            'worship.create' => 'Создание записей расписания',
            'worship.edit' => 'Редактирование и отмена богослужений',
        ];

        $find = $pdo->prepare(
            'SELECT id FROM permissions
             WHERE permission_key = :permission_key
             LIMIT 1'
        );
        $insert = $pdo->prepare(
            'INSERT INTO permissions (permission_key, name)
             VALUES (:permission_key, :name)'
        );

        foreach ($permissions as $key => $name) {
            $find->execute(['permission_key' => $key]);

            if ($find->fetchColumn() !== false) {
                continue;
            }

            $insert->execute([
                'permission_key' => $key,
                'name' => $name,
            ]);
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        $keys = ['worship.read', 'worship.create', 'worship.edit'];
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $idsQuery = $pdo->prepare(
            'SELECT id FROM permissions
             WHERE permission_key IN (' . $placeholders . ')'
        );
        $idsQuery->execute($keys);
        $ids = array_map(
            'intval',
            $idsQuery->fetchAll(\PDO::FETCH_COLUMN),
        );

        if ($ids !== []) {
            $idPlaceholders = implode(
                ', ',
                array_fill(0, count($ids), '?'),
            );
            $deleteLinks = $pdo->prepare(
                'DELETE FROM role_permissions
                 WHERE permission_id IN (' . $idPlaceholders . ')'
            );
            $deleteLinks->execute($ids);
        }

        $delete = $pdo->prepare(
            'DELETE FROM permissions
             WHERE permission_key IN (' . $placeholders . ')'
        );
        $delete->execute($keys);
    }
};
