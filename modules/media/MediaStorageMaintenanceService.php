<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class MediaStorageMaintenanceService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MediaBlobStorage $storage,
        private readonly MediaArchiveService $archive,
        private readonly MediaArchiveRepository $locations,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
            MediaBlobStorage::fromConfig(),
            MediaArchiveService::fromConfig(),
            MediaArchiveRepository::fromDatabase(),
        );
    }

    /** @return list<array{sha256:string,bytes:int,mime_type:string,oldest_created_at:string}> */
    public function archivePlan(int $olderThanDays = 90, int $limit = 100): array
    {
        $olderThanDays = max(1, min(36500, $olderThanDays));
        $limit = max(1, min(1000, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($olderThanDays * 86400));

        $statement = $this->pdo->prepare(
            "SELECT a.sha256,
                    MAX(a.bytes) AS bytes,
                    MAX(a.mime_type) AS mime_type,
                    MIN(a.created_at) AS oldest_created_at
             FROM media_assets a
             LEFT JOIN media_archive_locations l
               ON l.sha256 = a.sha256
              AND l.state = 'ready'
             WHERE a.created_at <= :cutoff
               AND l.id IS NULL
             GROUP BY a.sha256
             ORDER BY MIN(a.created_at), a.sha256
             LIMIT {$limit}"
        );
        $statement->execute(['cutoff' => $cutoff]);

        return array_map(
            static fn(array $row): array => [
                'sha256' => (string) $row['sha256'],
                'bytes' => (int) $row['bytes'],
                'mime_type' => (string) $row['mime_type'],
                'oldest_created_at' => (string) $row['oldest_created_at'],
            ],
            $statement->fetchAll(),
        );
    }

    /** @return array{planned:int,archived:int,removed_local:int,bytes:int,errors:list<string>} */
    public function archiveOld(
        int $olderThanDays = 90,
        int $limit = 100,
        bool $removeLocal = false,
        bool $apply = false,
    ): array {
        $plan = $this->archivePlan($olderThanDays, $limit);
        $summary = [
            'planned' => count($plan),
            'archived' => 0,
            'removed_local' => 0,
            'bytes' => array_sum(array_column($plan, 'bytes')),
            'errors' => [],
        ];

        if (!$apply) {
            return $summary;
        }

        foreach ($plan as $item) {
            try {
                $this->archive->archive(
                    $item['sha256'],
                    $item['bytes'],
                    $item['mime_type'],
                    $removeLocal,
                );
                $summary['archived']++;
                if ($removeLocal) {
                    $summary['removed_local']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = self::safeError($error);
            }
        }

        return $summary;
    }

    /** @return list<array{public_id:string,site_key:string,bytes:int}> */
    public function imageOptimizationPlan(int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        $statement = $this->pdo->query(
            "SELECT a.public_id, a.site_key, a.bytes
             FROM media_assets a
             LEFT JOIN media_derivatives d
               ON d.site_key = a.site_key
              AND d.media_public_id = a.public_id
              AND d.variant = 'web'
             WHERE a.status <> 'archived'
               AND a.media_type = 'image'
               AND a.mime_type IN ('image/jpeg', 'image/png', 'image/webp')
               AND a.pixel_width IS NOT NULL
               AND a.pixel_height IS NOT NULL
               AND d.id IS NULL
             ORDER BY a.created_at, a.id
             LIMIT {$limit}"
        );

        return array_map(
            static fn(array $row): array => [
                'public_id' => (string) $row['public_id'],
                'site_key' => (string) $row['site_key'],
                'bytes' => (int) $row['bytes'],
            ],
            $statement->fetchAll(),
        );
    }

    /** @return array{planned:int,optimized:int,source_bytes:int,web_bytes:int,saved_bytes:int,errors:list<string>} */
    public function optimizeImages(int $limit = 100, bool $apply = false): array
    {
        $plan = $this->imageOptimizationPlan($limit);
        $summary = [
            'planned' => count($plan),
            'optimized' => 0,
            'source_bytes' => array_sum(array_column($plan, 'bytes')),
            'web_bytes' => 0,
            'saved_bytes' => 0,
            'errors' => [],
        ];

        if (!$apply) {
            return $summary;
        }

        $optimizer = MediaImageOptimizationService::fromConfig();
        foreach ($plan as $item) {
            try {
                $derivative = $optimizer->generateWeb(
                    $item['public_id'],
                    $item['site_key'],
                );
                $summary['optimized']++;
                $summary['web_bytes'] += $derivative->bytes;
                $summary['saved_bytes'] += max(0, $item['bytes'] - $derivative->bytes);
            } catch (\Throwable $error) {
                $summary['errors'][] = self::safeError($error);
            }
        }

        return $summary;
    }

    /**
     * Удаляет только локальные копии blob, которые уже подтверждены во
     * внешнем архиве. Сам архив и логическая Media-запись не затрагиваются.
     *
     * @return array{planned:int,removed:int,bytes:int,errors:list<string>}
     */
    public function pruneArchiveCache(
        int $olderThanDays = 30,
        int $limit = 500,
        bool $apply = false,
    ): array {
        $olderThanDays = max(1, min(36500, $olderThanDays));
        $limit = max(1, min(5000, $limit));
        $cutoff = time() - ($olderThanDays * 86400);
        $planned = [];

        foreach ($this->locations->allReady() as $location) {
            if (count($planned) >= $limit) {
                break;
            }

            $sha = (string) $location['sha256'];
            $bytes = (int) $location['bytes'];
            $path = $this->storage->readablePath($sha, $bytes);
            if ($path === null) {
                continue;
            }

            $modified = filemtime($path);
            if (!is_int($modified) || $modified > $cutoff) {
                continue;
            }

            $planned[] = ['path' => $path, 'bytes' => $bytes];
        }

        $summary = [
            'planned' => count($planned),
            'removed' => 0,
            'bytes' => array_sum(array_column($planned, 'bytes')),
            'errors' => [],
        ];

        if (!$apply) {
            return $summary;
        }

        foreach ($planned as $item) {
            if (@unlink($item['path'])) {
                $summary['removed']++;
                continue;
            }
            $summary['errors'][] = 'Не удалось удалить одну из локальных архивных копий.';
        }

        return $summary;
    }

    private static function safeError(\Throwable $error): string
    {
        return substr(
            str_replace(["\r", "\n", "\0"], [' ', ' ', ''], trim($error->getMessage())),
            0,
            300,
        );
    }
}
