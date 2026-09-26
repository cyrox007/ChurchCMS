<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class ChannelSyncStateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function failedCount(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM external_channel_sync_state
             WHERE last_error IS NOT NULL
               AND last_error <> ''"
        );

        return (int) $statement->fetchColumn();
    }

    public function cursor(int $connectionId): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT cursor_value FROM external_channel_sync_state WHERE connection_id = :id LIMIT 1'
        );
        $statement->execute(['id' => $connectionId]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function success(int $connectionId, ?string $cursor): void
    {
        $this->upsert($connectionId, $cursor, null);
    }

    public function failure(int $connectionId, string $error): void
    {
        $this->upsert($connectionId, $this->cursor($connectionId), substr($error, 0, 500));
    }

    private function upsert(int $connectionId, ?string $cursor, ?string $error): void
    {
        $existing = $this->pdo->prepare(
            'SELECT connection_id FROM external_channel_sync_state WHERE connection_id = :id'
        );
        $existing->execute(['id' => $connectionId]);
        $now = gmdate('Y-m-d H:i:s');

        if ($existing->fetchColumn() !== false) {
            $update = $this->pdo->prepare(
                'UPDATE external_channel_sync_state
                 SET cursor_value = :cursor,
                     last_sync_at = :last_sync_at,
                     last_error = :last_error,
                     updated_at = :updated_at
                 WHERE connection_id = :id'
            );
            $update->execute([
                'cursor' => $cursor,
                'last_sync_at' => $now,
                'last_error' => $error,
                'updated_at' => $now,
                'id' => $connectionId,
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO external_channel_sync_state (
                connection_id, cursor_value, last_sync_at, last_error, updated_at
             ) VALUES (:id, :cursor, :last_sync_at, :last_error, :updated_at)'
        );
        $insert->execute([
            'id' => $connectionId,
            'cursor' => $cursor,
            'last_sync_at' => $now,
            'last_error' => $error,
            'updated_at' => $now,
        ]);
    }
}
