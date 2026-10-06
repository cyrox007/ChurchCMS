<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class SyndicationExportLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDefaultConnection(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    public function record(
        string $target,
        string $status,
        int $entryCount,
        int $bodyBytes,
        int $durationMs,
        ?string $errorCode = null,
    ): void {
        $target = $this->bounded($target, 64, 'unknown');
        $status = in_array($status, ['success', 'failed'], true)
            ? $status
            : 'failed';
        $errorCode = $errorCode === null
            ? null
            : $this->bounded($errorCode, 64, 'unknown_error');

        $statement = $this->pdo->prepare(
            'INSERT INTO syndication_export_log '
            . '(target, status, entry_count, body_bytes, duration_ms, error_code, created_at) '
            . 'VALUES (:target, :status, :entry_count, :body_bytes, :duration_ms, :error_code, :created_at)'
        );
        $statement->execute([
            ':target' => $target,
            ':status' => $status,
            ':entry_count' => max(0, $entryCount),
            ':body_bytes' => max(0, $bodyBytes),
            ':duration_ms' => max(0, $durationMs),
            ':error_code' => $errorCode,
            ':created_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 100, ?string $target = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT id, target, status, entry_count, body_bytes, duration_ms, error_code, created_at '
            . 'FROM syndication_export_log';
        $parameters = [];

        if ($target !== null && trim($target) !== '') {
            $sql .= ' WHERE target = :target';
            $parameters[':target'] = $this->bounded($target, 64, 'unknown');
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function bounded(string $value, int $limit, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        return substr($value, 0, $limit);
    }
}
