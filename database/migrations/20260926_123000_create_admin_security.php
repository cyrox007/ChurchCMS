<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_123000_create_admin_security';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE admin_users (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
)
SQL,
                <<<'SQL'
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    role_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL
)
SQL,
                <<<'SQL'
CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL
)
SQL,
                <<<'SQL'
CREATE TABLE admin_user_roles (
    user_id BIGINT NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_id)
)
SQL,
                <<<'SQL'
CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
)
SQL,
                <<<'SQL'
CREATE TABLE admin_role_scopes (
    user_id BIGINT NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    scope_type VARCHAR(32) NOT NULL,
    scope_key VARCHAR(128) NOT NULL,
    PRIMARY KEY (user_id, role_id, site_key, scope_type, scope_key)
)
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL UNIQUE,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    permission_key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE admin_user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_admin_user_roles_user FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
CREATE TABLE admin_role_scopes (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    site_key VARCHAR(64) NOT NULL DEFAULT 'default',
    scope_type VARCHAR(32) NOT NULL,
    scope_key VARCHAR(128) NOT NULL,
    PRIMARY KEY (user_id, role_id, site_key, scope_type, scope_key),
    CONSTRAINT fk_admin_role_scopes_user FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_role_scopes_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $roles = [
            'superadmin' => 'Суперадминистратор',
            'administrator' => 'Администратор',
            'editor' => 'Редактор',
            'author' => 'Автор',
            'media_manager' => 'Медиаменеджер',
        ];

        $permissions = [
            'admin.access' => 'Доступ в административную панель',
            'publications.read' => 'Просмотр материалов в админке',
            'publications.create' => 'Создание публикаций',
            'publications.edit' => 'Редактирование публикаций',
            'publications.publish' => 'Публикация и снятие с публикации',
            'publications.syndicate' => 'Управление внешним распространением',
            'media.manage' => 'Управление медиатекой',
            'users.manage' => 'Управление администраторами и ролями',
            'settings.manage' => 'Управление настройками сайта',
        ];

        $roleStatement = $pdo->prepare('INSERT INTO roles (role_key, name) VALUES (:role_key, :name)');
        foreach ($roles as $key => $name) {
            $roleStatement->execute(['role_key' => $key, 'name' => $name]);
        }

        $permissionStatement = $pdo->prepare(
            'INSERT INTO permissions (permission_key, name) VALUES (:permission_key, :name)'
        );
        foreach ($permissions as $key => $name) {
            $permissionStatement->execute(['permission_key' => $key, 'name' => $name]);
        }
    }
};
