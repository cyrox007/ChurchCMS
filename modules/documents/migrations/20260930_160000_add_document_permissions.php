<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20260930_160000_add_document_permissions';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $permissions = [
            'documents.read' => 'Просмотр документов в админке',
            'documents.create' => 'Создание документов',
            'documents.edit' => 'Редактирование документов',
            'documents.publish' => 'Публикация и снятие документов',
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

    public function down(\PDO $pdo, string $driver): void
    {
        $keys = [
            'documents.read',
            'documents.create',
            'documents.edit',
            'documents.publish',
        ];

        $placeholders = implode(
            ', ',
            array_fill(0, count($keys), '?'),
        );

        $permissionIds = $pdo->prepare(
            'SELECT id
             FROM permissions
             WHERE permission_key IN (' . $placeholders . ')'
        );
        $permissionIds->execute($keys);
        $ids = array_map(
            'intval',
            $permissionIds->fetchAll(\PDO::FETCH_COLUMN),
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
