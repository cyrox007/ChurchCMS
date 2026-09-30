<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;
use Throwable;

final class MediaUsageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * @return list<MediaUsageReference>
     */
    public function forAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): array {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM media_usage_references
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id
             ORDER BY consumer_type, consumer_public_id, usage_key, id'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'media_public_id' => $mediaPublicId,
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    public function countForAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM media_usage_references
             WHERE site_key = :site_key
               AND media_public_id = :media_public_id'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'media_public_id' => $mediaPublicId,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string,string> $references usage_key => media_public_id
     */
    public function replaceConsumer(
        string $consumerType,
        string $consumerPublicId,
        array $references,
        string $siteKey = 'default',
    ): void {
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM media_usage_references
                 WHERE site_key = :site_key
                   AND consumer_type = :consumer_type
                   AND consumer_public_id = :consumer_public_id'
            );
            $delete->execute([
                'site_key' => $siteKey,
                'consumer_type' => $consumerType,
                'consumer_public_id' => $consumerPublicId,
            ]);

            if ($references !== []) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO media_usage_references (
                        site_key,
                        media_public_id,
                        consumer_type,
                        consumer_public_id,
                        usage_key,
                        created_at,
                        updated_at
                     ) VALUES (
                        :site_key,
                        :media_public_id,
                        :consumer_type,
                        :consumer_public_id,
                        :usage_key,
                        :created_at,
                        :updated_at
                     )'
                );
                $now = gmdate('Y-m-d H:i:s');

                foreach ($references as $usageKey => $mediaPublicId) {
                    $insert->execute([
                        'site_key' => $siteKey,
                        'media_public_id' => $mediaPublicId,
                        'consumer_type' => $consumerType,
                        'consumer_public_id' => $consumerPublicId,
                        'usage_key' => $usageKey,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

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

    private static function hydrate(array $row): MediaUsageReference
    {
        return new MediaUsageReference(
            id: (int) $row['id'],
            siteKey: (string) $row['site_key'],
            mediaPublicId: (string) $row['media_public_id'],
            consumerType: (string) $row['consumer_type'],
            consumerPublicId: (string) $row['consumer_public_id'],
            usageKey: (string) $row['usage_key'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
