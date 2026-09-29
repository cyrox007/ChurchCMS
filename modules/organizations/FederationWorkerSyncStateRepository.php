<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use PDO;
use RuntimeException;

final class FederationWorkerSyncStateRepository
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

    public function cursor(
        int $linkId,
        string $workerId,
    ): ?string {
        $state = $this->state($linkId, $workerId);

        return $state['cursor'] ?? null;
    }

    /**
     * @return array{
     *     cursor:?string,
     *     last_sync_at:?string,
     *     last_sync_error:?string
     * }|null
     */
    public function state(
        int $linkId,
        string $workerId,
    ): ?array {
        $workerId = self::workerId($workerId);
        if ($linkId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT sync_cursor, last_sync_at, last_sync_error
             FROM federation_worker_sync_states
             WHERE federation_link_id = :link_id
               AND worker_id = :worker_id
             LIMIT 1'
        );
        $statement->execute([
            'link_id' => $linkId,
            'worker_id' => $workerId,
        ]);

        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'cursor' => self::nullable(
                $row['sync_cursor'] ?? null,
            ),
            'last_sync_at' => self::nullable(
                $row['last_sync_at'] ?? null,
            ),
            'last_sync_error' => self::nullable(
                $row['last_sync_error'] ?? null,
            ),
        ];
    }

    public function saveCursor(
        int $linkId,
        string $workerId,
        ?string $expectedCursor,
        string $cursor,
    ): void {
        $this->writeCursor(
            $linkId,
            $workerId,
            $expectedCursor,
            $cursor,
            false,
        );
    }

    public function recordSuccess(
        int $linkId,
        string $workerId,
        ?string $expectedCursor,
        string $cursor,
    ): void {
        $this->writeCursor(
            $linkId,
            $workerId,
            $expectedCursor,
            $cursor,
            true,
        );
    }

    public function recordFailure(
        int $linkId,
        string $workerId,
        string $message,
        ?string $expectedCursor,
    ): void {
        $workerId = self::workerId($workerId);
        $this->ensureRow($linkId, $workerId);

        $message = trim($message);
        if ($message === '' || strlen($message) > 500) {
            $message = 'Синхронизация этого типа данных не выполнена.';
        }

        $expected = trim((string) ($expectedCursor ?? ''));
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'UPDATE federation_worker_sync_states
             SET last_sync_error = :error,
                 updated_at = :updated_at
             WHERE federation_link_id = :link_id
               AND worker_id = :worker_id
               AND COALESCE(sync_cursor, \'\') = :expected_cursor'
        );
        $statement->execute([
            'error' => $message,
            'updated_at' => $now,
            'link_id' => $linkId,
            'worker_id' => $workerId,
            'expected_cursor' => $expected,
        ]);

        if ($statement->rowCount() !== 1) {
            $current = trim((string) (
                $this->cursor($linkId, $workerId) ?? ''
            ));
            if ($current !== $expected) {
                throw new RuntimeException(
                    'Курсор federation sync worker уже изменён другим процессом.'
                );
            }
        }

        $this->mirrorLegacyFailure(
            $linkId,
            $workerId,
            $message,
            $now,
        );
    }

    public function clearForLink(int $linkId): void
    {
        if ($linkId <= 0) {
            return;
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM federation_worker_sync_states
             WHERE federation_link_id = :link_id'
        );
        $statement->execute(['link_id' => $linkId]);
    }

    private function writeCursor(
        int $linkId,
        string $workerId,
        ?string $expectedCursor,
        string $cursor,
        bool $completed,
    ): void {
        $workerId = self::workerId($workerId);
        $cursor = trim($cursor);

        if ($linkId <= 0 || $cursor === '') {
            throw new RuntimeException(
                'Некорректное состояние курсора federation sync worker.'
            );
        }

        $this->ensureRow($linkId, $workerId);

        $expected = trim((string) ($expectedCursor ?? ''));
        $now = gmdate('Y-m-d H:i:s');
        $syncFields = $completed
            ? ',
                 last_sync_at = :last_sync_at,
                 last_sync_error = NULL'
            : '';

        $statement = $this->pdo->prepare(
            'UPDATE federation_worker_sync_states
             SET sync_cursor = :cursor'
            . $syncFields
            . ',
                 updated_at = :updated_at
             WHERE federation_link_id = :link_id
               AND worker_id = :worker_id
               AND COALESCE(sync_cursor, \'\') = :expected_cursor'
        );
        $parameters = [
            'cursor' => $cursor,
            'updated_at' => $now,
            'link_id' => $linkId,
            'worker_id' => $workerId,
            'expected_cursor' => $expected,
        ];
        if ($completed) {
            $parameters['last_sync_at'] = $now;
        }
        $statement->execute($parameters);

        if ($statement->rowCount() !== 1) {
            $current = trim((string) (
                $this->cursor($linkId, $workerId) ?? ''
            ));

            if (
                $cursor === $expected
                && $current === $expected
            ) {
                return;
            }

            throw new RuntimeException(
                'Курсор federation sync worker уже изменён другим процессом.'
            );
        }

        $this->mirrorLegacyCursor(
            $linkId,
            $workerId,
            $cursor,
            $completed,
            $now,
        );
    }

    private function ensureRow(
        int $linkId,
        string $workerId,
    ): void {
        if ($linkId <= 0) {
            throw new RuntimeException(
                'Federation link для sync worker не найден.'
            );
        }

        $now = gmdate('Y-m-d H:i:s');
        $driver = (string) $this->pdo->getAttribute(
            PDO::ATTR_DRIVER_NAME,
        );

        $sql = match ($driver) {
            'pgsql' =>
                'INSERT INTO federation_worker_sync_states (
                    federation_link_id,
                    worker_id,
                    sync_cursor,
                    last_sync_at,
                    last_sync_error,
                    created_at,
                    updated_at
                 ) VALUES (
                    :link_id,
                    :worker_id,
                    NULL,
                    NULL,
                    NULL,
                    :created_at,
                    :updated_at
                 )
                 ON CONFLICT (
                    federation_link_id,
                    worker_id
                 ) DO NOTHING',
            'mysql' =>
                'INSERT IGNORE INTO federation_worker_sync_states (
                    federation_link_id,
                    worker_id,
                    sync_cursor,
                    last_sync_at,
                    last_sync_error,
                    created_at,
                    updated_at
                 ) VALUES (
                    :link_id,
                    :worker_id,
                    NULL,
                    NULL,
                    NULL,
                    :created_at,
                    :updated_at
                 )',
            default => throw new RuntimeException(
                'Неподдерживаемый драйвер federation sync state.'
            ),
        };

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'link_id' => $linkId,
            'worker_id' => $workerId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function mirrorLegacyCursor(
        int $linkId,
        string $workerId,
        string $cursor,
        bool $completed,
        string $now,
    ): void {
        if ($workerId !== 'publications') {
            return;
        }

        $syncFields = $completed
            ? ',
                 last_sync_at = :last_sync_at,
                 last_sync_error = NULL'
            : '';

        $statement = $this->pdo->prepare(
            'UPDATE organization_federation_links
             SET sync_cursor = :cursor'
            . $syncFields
            . ',
                 updated_at = :updated_at
             WHERE id = :link_id'
        );
        $parameters = [
            'cursor' => $cursor,
            'updated_at' => $now,
            'link_id' => $linkId,
        ];
        if ($completed) {
            $parameters['last_sync_at'] = $now;
        }
        $statement->execute($parameters);
    }

    private function mirrorLegacyFailure(
        int $linkId,
        string $workerId,
        string $message,
        string $now,
    ): void {
        if ($workerId !== 'publications') {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE organization_federation_links
             SET last_sync_error = :error,
                 updated_at = :updated_at
             WHERE id = :link_id'
        );
        $statement->execute([
            'error' => $message,
            'updated_at' => $now,
            'link_id' => $linkId,
        ]);
    }

    private static function workerId(string $value): string
    {
        $value = trim($value);
        if (
            preg_match(
                '/^[a-z][a-z0-9_.-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Некорректный ID federation sync worker.'
            );
        }

        return $value;
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
