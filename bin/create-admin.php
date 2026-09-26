#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;

$root = dirname(__DIR__);
require $root . '/core.php';

$username = trim((string) ($argv[1] ?? ''));
$displayName = trim((string) ($argv[2] ?? $username));
$password = (string) (getenv('CHURCHCMS_ADMIN_PASSWORD') ?: '');

if (
    $username === ''
    || preg_match('/^[A-Za-z0-9_.-]{3,100}$/D', $username) !== 1
    || $displayName === ''
) {
    fwrite(STDERR, "Usage: CHURCHCMS_ADMIN_PASSWORD='...' php bin/create-admin.php <username> [display-name]\n");
    exit(2);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "CHURCHCMS_ADMIN_PASSWORD must contain at least 12 characters.\n");
    exit(2);
}

$pdo = DatabaseManager::getInstance()->connection();
$now = gmdate('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        'INSERT INTO admin_users (
            public_id, username, password_hash, display_name, email, status,
            last_login_at, created_at, updated_at
         ) VALUES (
            :public_id, :username, :password_hash, :display_name, NULL, :status,
            NULL, :created_at, :updated_at
         )'
    );
    $insert->execute([
        'public_id' => Uuid::v4(),
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'display_name' => $displayName,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $userId = (int) $pdo->lastInsertId();

    if ($userId <= 0) {
        $find = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
        $find->execute(['username' => $username]);
        $userId = (int) $find->fetchColumn();
    }

    $role = $pdo->prepare('SELECT id FROM roles WHERE role_key = :role_key LIMIT 1');
    $role->execute(['role_key' => 'superadmin']);
    $roleId = (int) $role->fetchColumn();

    if ($userId <= 0 || $roleId <= 0) {
        throw new RuntimeException('Unable to resolve created user or superadmin role.');
    }

    $assign = $pdo->prepare(
        'INSERT INTO admin_user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assign->execute([
        'user_id' => $userId,
        'role_id' => $roleId,
    ]);

    $pdo->commit();

    echo "Created superadmin: {$username}\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "Unable to create admin: {$e->getMessage()}\n");
    exit(1);
}
