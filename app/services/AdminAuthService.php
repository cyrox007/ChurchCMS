<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Csrf;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Request;
use ChurchCMS\Core\SessionSecurity;
use PDO;

final class AdminAuthService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @return array{id:int,public_id:string,username:string,display_name:string,email:?string}|null
     */
    public function authenticate(Request $request, string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, public_id, username, password_hash, display_name, email, status
             FROM admin_users
             WHERE username = :username
             LIMIT 1'
        );
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        if (
            !is_array($user)
            || (string) ($user['status'] ?? '') !== 'active'
            || !password_verify($password, (string) ($user['password_hash'] ?? ''))
        ) {
            return null;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $rehash = $this->pdo->prepare(
                'UPDATE admin_users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
            );
            $rehash->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => (int) $user['id'],
            ]);
        }

        SessionSecurity::regenerate();
        $request->setSession('admin_authenticated', true);
        $request->setSession('admin_user_id', (int) $user['id']);
        $request->setSession('admin_user_public_id', (string) $user['public_id']);
        $request->setSession('admin_authenticated_at', time());
        Csrf::rotate();

        $login = $this->pdo->prepare(
            'UPDATE admin_users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id'
        );
        $now = gmdate('Y-m-d H:i:s');
        $login->execute([
            'last_login_at' => $now,
            'updated_at' => $now,
            'id' => (int) $user['id'],
        ]);

        return [
            'id' => (int) $user['id'],
            'public_id' => (string) $user['public_id'],
            'username' => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
            'email' => isset($user['email']) ? (string) $user['email'] : null,
        ];
    }

    /**
     * @return array{id:int,public_id:string,username:string,display_name:string,email:?string}|null
     */
    public function current(Request $request): ?array
    {
        if ($request->session('admin_authenticated', false) !== true) {
            return null;
        }

        $id = (int) $request->session('admin_user_id', 0);
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, public_id, username, display_name, email, status
             FROM admin_users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        if (!is_array($user) || (string) $user['status'] !== 'active') {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'public_id' => (string) $user['public_id'],
            'username' => (string) $user['username'],
            'display_name' => (string) $user['display_name'],
            'email' => isset($user['email']) ? (string) $user['email'] : null,
        ];
    }

    public function logout(): void
    {
        SessionSecurity::destroy();
    }
}
