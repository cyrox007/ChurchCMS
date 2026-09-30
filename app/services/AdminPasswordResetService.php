<?php

declare(strict_types=1);

namespace ChurchCMS\App\Services;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class AdminPasswordResetService
{
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly PDO $pdo,
        private readonly PasswordResetTransport $transport,
        private readonly string $baseUrl,
        private readonly int $ttlSeconds = 1800,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
            PasswordResetTransportFactory::fromConfig(),
            rtrim(
                (string) Config::get(
                    'app.url',
                    'http://localhost',
                ),
                '/',
            ),
            max(
                300,
                min(
                    86400,
                    (int) Config::get(
                        'security.password_reset.ttl_seconds',
                        1800,
                    ),
                ),
            ),
        );
    }

    public function requestReset(string $email): bool
    {
        $email = self::normalizeEmail($email);
        if ($email === null) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT
                id,
                public_id,
                display_name,
                email
             FROM admin_users
             WHERE LOWER(email) = LOWER(:email)
               AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'email' => $email,
            'status' => 'active',
        ]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return false;
        }

        $token = self::token();
        $tokenHash = hash('sha256', $token);
        $createdAt = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate(
            'Y-m-d H:i:s',
            time() + $this->ttlSeconds,
        );

        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $invalidate = $this->pdo->prepare(
                'UPDATE admin_password_resets
                 SET used_at = :used_at
                 WHERE user_id = :user_id
                   AND used_at IS NULL'
            );
            $invalidate->execute([
                'used_at' => $createdAt,
                'user_id' => (int) $user['id'],
            ]);

            $insert = $this->pdo->prepare(
                'INSERT INTO admin_password_resets (
                    user_id,
                    token_hash,
                    expires_at,
                    used_at,
                    created_at
                 ) VALUES (
                    :user_id,
                    :token_hash,
                    :expires_at,
                    NULL,
                    :created_at
                 )'
            );
            $insert->execute([
                'user_id' => (int) $user['id'],
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $error;
        }

        try {
            $this->transport->deliver(
                new PasswordResetMessage(
                    email: (string) $user['email'],
                    displayName: (string) $user['display_name'],
                    resetUrl: $this->resetUrl($token),
                    expiresAt: $expiresAt,
                ),
            );

            return true;
        } catch (Throwable $error) {
            $this->invalidateHash($tokenHash);

            throw new RuntimeException(
                'Не удалось доставить ссылку восстановления.',
                0,
                $error,
            );
        }
    }

    public function tokenIsValid(string $token): bool
    {
        $hash = self::tokenHash($token);
        if ($hash === null) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM admin_password_resets r
             INNER JOIN admin_users u
                ON u.id = r.user_id
             WHERE r.token_hash = :token_hash
               AND r.used_at IS NULL
               AND r.expires_at > :now
               AND u.status = :status
             LIMIT 1'
        );
        $statement->execute([
            'token_hash' => $hash,
            'now' => gmdate('Y-m-d H:i:s'),
            'status' => 'active',
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function resetPassword(
        string $token,
        string $newPassword,
        string $confirmation,
    ): void {
        $tokenHash = self::tokenHash($token);

        if ($tokenHash === null) {
            throw new InvalidArgumentException(
                'Ссылка восстановления недействительна.'
            );
        }

        self::validatePassword(
            $newPassword,
            $confirmation,
        );

        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    r.id AS reset_id,
                    r.user_id,
                    u.password_hash,
                    u.auth_version
                 FROM admin_password_resets r
                 INNER JOIN admin_users u
                    ON u.id = r.user_id
                 WHERE r.token_hash = :token_hash
                   AND r.used_at IS NULL
                   AND r.expires_at > :now
                   AND u.status = :status
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute([
                'token_hash' => $tokenHash,
                'now' => gmdate('Y-m-d H:i:s'),
                'status' => 'active',
            ]);
            $row = $statement->fetch();

            if (!is_array($row)) {
                throw new InvalidArgumentException(
                    'Ссылка восстановления недействительна или истекла.'
                );
            }

            if (
                password_verify(
                    $newPassword,
                    (string) $row['password_hash'],
                )
            ) {
                throw new InvalidArgumentException(
                    'Новый пароль должен отличаться от текущего.'
                );
            }

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT,
            );

            if (
                !is_string($passwordHash)
                || $passwordHash === ''
            ) {
                throw new RuntimeException(
                    'Не удалось безопасно подготовить новый пароль.'
                );
            }

            $now = gmdate('Y-m-d H:i:s');
            $newVersion = max(
                1,
                (int) $row['auth_version'] + 1,
            );

            $updateUser = $this->pdo->prepare(
                'UPDATE admin_users
                 SET password_hash = :password_hash,
                     auth_version = :auth_version,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $updateUser->execute([
                'password_hash' => $passwordHash,
                'auth_version' => $newVersion,
                'updated_at' => $now,
                'id' => (int) $row['user_id'],
            ]);

            $consume = $this->pdo->prepare(
                'UPDATE admin_password_resets
                 SET used_at = :used_at
                 WHERE id = :id
                   AND used_at IS NULL'
            );
            $consume->execute([
                'used_at' => $now,
                'id' => (int) $row['reset_id'],
            ]);

            if ($consume->rowCount() !== 1) {
                throw new RuntimeException(
                    'Reset-токен уже был использован.'
                );
            }

            $invalidate = $this->pdo->prepare(
                'UPDATE admin_password_resets
                 SET used_at = :used_at
                 WHERE user_id = :user_id
                   AND used_at IS NULL'
            );
            $invalidate->execute([
                'used_at' => $now,
                'user_id' => (int) $row['user_id'],
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    private function resetUrl(string $token): string
    {
        return $this->baseUrl
            . '/admin/password/reset?token='
            . rawurlencode($token);
    }

    private function invalidateHash(
        string $tokenHash,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE admin_password_resets
             SET used_at = :used_at
             WHERE token_hash = :token_hash
               AND used_at IS NULL'
        );
        $statement->execute([
            'used_at' => gmdate('Y-m-d H:i:s'),
            'token_hash' => $tokenHash,
        ]);
    }

    private static function token(): string
    {
        return rtrim(
            strtr(
                base64_encode(
                    random_bytes(self::TOKEN_BYTES)
                ),
                '+/',
                '-_',
            ),
            '=',
        );
    }

    private static function tokenHash(
        string $token,
    ): ?string {
        $token = trim($token);

        if (
            preg_match(
                '/^[A-Za-z0-9_-]{43}$/D',
                $token,
            ) !== 1
        ) {
            return null;
        }

        return hash('sha256', $token);
    }

    private static function normalizeEmail(
        string $email,
    ): ?string {
        $email = trim($email);

        if (
            $email === ''
            || strlen($email) > 255
            || filter_var(
                $email,
                FILTER_VALIDATE_EMAIL,
            ) === false
        ) {
            return null;
        }

        return $email;
    }

    private static function validatePassword(
        string $password,
        string $confirmation,
    ): void {
        if (
            strlen($password) < 12
            || strlen($password) > 4096
        ) {
            throw new InvalidArgumentException(
                'Новый пароль должен содержать не менее 12 символов.'
            );
        }

        if (!hash_equals(
            $password,
            $confirmation,
        )) {
            throw new InvalidArgumentException(
                'Новый пароль и подтверждение не совпадают.'
            );
        }
    }
}
