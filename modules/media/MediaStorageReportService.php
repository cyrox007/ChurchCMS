<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class MediaStorageReportService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly MediaBlobStorage $storage,
        private readonly string $storageRoot,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
            MediaBlobStorage::fromConfig(),
            (string) Config::get('media.storage_path', ''),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function report(bool $recordSnapshot = false): array
    {
        $assets = $this->pdo->query(
            'SELECT sha256, bytes, media_type, mime_type
             FROM media_assets'
        )->fetchAll();
        $derivatives = $this->pdo->query(
            'SELECT sha256, bytes, mime_type
             FROM media_derivatives'
        )->fetchAll();
        $archives = $this->pdo->query(
            "SELECT sha256, bytes FROM media_archive_locations WHERE state = 'ready'"
        )->fetchAll();

        $logicalBytes = 0;
        $byType = [];
        $references = [];
        foreach ($assets as $row) {
            $sha = strtolower((string) $row['sha256']);
            $bytes = (int) $row['bytes'];
            $type = (string) $row['media_type'];
            $logicalBytes += $bytes;
            $byType[$type] = ($byType[$type] ?? 0) + $bytes;
            $references[$sha] = max($references[$sha] ?? 0, $bytes);
        }

        $derivativeBytes = 0;
        foreach ($derivatives as $row) {
            $sha = strtolower((string) $row['sha256']);
            $bytes = (int) $row['bytes'];
            $derivativeBytes += $bytes;
            $references[$sha] = max($references[$sha] ?? 0, $bytes);
        }

        $archiveSet = [];
        $archiveBytes = 0;
        foreach ($archives as $row) {
            $sha = strtolower((string) $row['sha256']);
            $archiveSet[$sha] = true;
            $archiveBytes += (int) $row['bytes'];
        }

        $uniqueBlobBytes = array_sum($references);
        $dedupSavedBytes = max(0, $logicalBytes + $derivativeBytes - $uniqueBlobBytes);
        $local = $this->scanLocal($references);

        $missing = 0;
        $missingButArchived = 0;
        foreach ($references as $sha => $bytes) {
            if ($this->storage->readablePath($sha, $bytes) !== null) {
                continue;
            }
            if (isset($archiveSet[$sha])) {
                $missingButArchived++;
            } else {
                $missing++;
            }
        }

        $diskTotal = @disk_total_space($this->storageRoot);
        $diskFree = @disk_free_space($this->storageRoot);
        $diskTotal = is_float($diskTotal) ? (int) $diskTotal : null;
        $diskFree = is_float($diskFree) ? (int) $diskFree : null;

        $result = [
            'logical_bytes' => $logicalBytes,
            'unique_blob_bytes' => $uniqueBlobBytes,
            'derivative_bytes' => $derivativeBytes,
            'dedup_saved_bytes' => $dedupSavedBytes,
            'local_bytes' => $local['bytes'],
            'orphan_bytes' => $local['orphan_bytes'],
            'orphan_count' => $local['orphan_count'],
            'missing_count' => $missing,
            'archived_only_count' => $missingButArchived,
            'archive_bytes' => $archiveBytes,
            'disk_total_bytes' => $diskTotal,
            'disk_free_bytes' => $diskFree,
            'by_type' => $byType,
        ];

        if ($recordSnapshot) {
            $this->recordSnapshot($result);
        }

        return $result + $this->forecast($local['bytes'], $diskFree);
    }

    /**
     * @param array<string,int|null|array<string,int>> $report
     */
    private function recordSnapshot(array $report): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO media_storage_snapshots (
                local_bytes, logical_bytes, unique_blob_bytes,
                derivative_bytes, disk_total_bytes, disk_free_bytes, captured_at
             ) VALUES (
                :local_bytes, :logical_bytes, :unique_blob_bytes,
                :derivative_bytes, :disk_total_bytes, :disk_free_bytes, :captured_at
             )'
        );
        $statement->execute([
            'local_bytes' => (int) $report['local_bytes'],
            'logical_bytes' => (int) $report['logical_bytes'],
            'unique_blob_bytes' => (int) $report['unique_blob_bytes'],
            'derivative_bytes' => (int) $report['derivative_bytes'],
            'disk_total_bytes' => $report['disk_total_bytes'],
            'disk_free_bytes' => $report['disk_free_bytes'],
            'captured_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{growth_bytes_per_day:?int,estimated_days_to_full:?int}
     */
    private function forecast(int $currentLocalBytes, ?int $diskFree): array
    {
        $rows = $this->pdo->query(
            'SELECT local_bytes, captured_at
             FROM media_storage_snapshots
             ORDER BY captured_at DESC, id DESC
             LIMIT 30'
        )->fetchAll();

        if (count($rows) < 2) {
            return [
                'growth_bytes_per_day' => null,
                'estimated_days_to_full' => null,
            ];
        }

        $latest = $rows[0];
        $oldest = $rows[array_key_last($rows)];
        $latestAt = strtotime((string) $latest['captured_at']);
        $oldestAt = strtotime((string) $oldest['captured_at']);
        if ($latestAt === false || $oldestAt === false || $latestAt <= $oldestAt) {
            return [
                'growth_bytes_per_day' => null,
                'estimated_days_to_full' => null,
            ];
        }

        $days = ($latestAt - $oldestAt) / 86400;
        if ($days < 0.5) {
            return [
                'growth_bytes_per_day' => null,
                'estimated_days_to_full' => null,
            ];
        }

        $growth = (int) round(
            ((int) $latest['local_bytes'] - (int) $oldest['local_bytes']) / $days
        );
        if ($growth <= 0 || $diskFree === null) {
            return [
                'growth_bytes_per_day' => max(0, $growth),
                'estimated_days_to_full' => null,
            ];
        }

        return [
            'growth_bytes_per_day' => $growth,
            'estimated_days_to_full' => (int) floor($diskFree / $growth),
        ];
    }

    /**
     * @param array<string,int> $references
     * @return array{bytes:int,orphan_bytes:int,orphan_count:int}
     */
    private function scanLocal(array $references): array
    {
        if (!is_dir($this->storageRoot)) {
            return ['bytes' => 0, 'orphan_bytes' => 0, 'orphan_count' => 0];
        }

        $bytes = 0;
        $orphanBytes = 0;
        $orphanCount = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->storageRoot,
                FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }
            if (!preg_match('/^([a-f0-9]{64})\.blob$/D', $file->getFilename(), $match)) {
                continue;
            }

            $size = $file->getSize();
            $bytes += $size;
            if (!isset($references[$match[1]])) {
                $orphanBytes += $size;
                $orphanCount++;
            }
        }

        return [
            'bytes' => $bytes,
            'orphan_bytes' => $orphanBytes,
            'orphan_count' => $orphanCount,
        ];
    }
}
