<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class MediaArchiveRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $sha256): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM media_archive_locations WHERE sha256 = :sha256 LIMIT 1'
        );
        $statement->execute(['sha256' => strtolower(trim($sha256))]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function markReady(
        string $sha256,
        string $backend,
        string $objectKey,
        int $bytes,
    ): void {
        $existing = $this->find($sha256);
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO media_archive_locations (
                    sha256, backend, object_key, bytes, state,
                    verified_at, archived_at, created_at, updated_at
                 ) VALUES (
                    :sha256, :backend, :object_key, :bytes, :state,
                    :verified_at, :archived_at, :created_at, :updated_at
                 )'
            );
            $statement->execute([
                'sha256' => $sha256,
                'backend' => $backend,
                'object_key' => $objectKey,
                'bytes' => $bytes,
                'state' => 'ready',
                'verified_at' => $now,
                'archived_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE media_archive_locations
             SET backend = :backend,
                 object_key = :object_key,
                 bytes = :bytes,
                 state = :state,
                 verified_at = :verified_at,
                 archived_at = :archived_at,
                 updated_at = :updated_at
             WHERE sha256 = :sha256'
        );
        $statement->execute([
            'backend' => $backend,
            'object_key' => $objectKey,
            'bytes' => $bytes,
            'state' => 'ready',
            'verified_at' => $now,
            'archived_at' => $now,
            'updated_at' => $now,
            'sha256' => $sha256,
        ]);
    }

    public function markRestored(string $sha256): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE media_archive_locations
             SET last_restored_at = :restored_at,
                 updated_at = :updated_at
             WHERE sha256 = :sha256'
        );
        $statement->execute([
            'restored_at' => $now,
            'updated_at' => $now,
            'sha256' => $sha256,
        ]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function allReady(): array
    {
        $statement = $this->pdo->query(
            "SELECT * FROM media_archive_locations
             WHERE state = 'ready'
             ORDER BY archived_at DESC, id DESC"
        );

        return $statement->fetchAll();
    }
}
