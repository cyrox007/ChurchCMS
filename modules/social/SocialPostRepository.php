<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SocialPostRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @return list<SocialPost> */
    public function forPublication(int $publicationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM publication_social_posts
             WHERE publication_id = :publication_id
             ORDER BY id'
        );
        $statement->execute(['publication_id' => $publicationId]);

        return array_map(
            fn(array $row): SocialPost => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    /**
     * Атомарно резервирует pending-записи для одного запуска worker.
     *
     * @return list<SocialPost>
     */
    public function claimPending(
        int $limit = 20,
        int $staleSeconds = 900,
    ): array {
        $limit = max(1, min(100, $limit));
        $staleSeconds = max(60, min(86400, $staleSeconds));
        $now = gmdate('Y-m-d H:i:s');
        $staleBefore = gmdate(
            'Y-m-d H:i:s',
            time() - $staleSeconds,
        );

        $recover = $this->pdo->prepare(
            'UPDATE publication_social_posts
             SET status = :pending,
                 updated_at = :updated_at
             WHERE status = :processing
               AND updated_at < :stale_before'
        );
        $recover->execute([
            'pending' => 'pending',
            'processing' => 'processing',
            'updated_at' => $now,
            'stale_before' => $staleBefore,
        ]);

        $select = $this->pdo->prepare(
            'SELECT id FROM publication_social_posts
             WHERE status = :status
               AND enabled = :enabled
             ORDER BY queued_at ASC, id ASC
             LIMIT :limit'
        );
        $select->bindValue(':status', 'pending');
        $select->bindValue(':enabled', 1, PDO::PARAM_INT);
        $select->bindValue(':limit', $limit, PDO::PARAM_INT);
        $select->execute();

        $claim = $this->pdo->prepare(
            'UPDATE publication_social_posts
             SET status = :processing,
                 updated_at = :updated_at
             WHERE id = :id
               AND status = :pending'
        );
        $load = $this->pdo->prepare(
            'SELECT * FROM publication_social_posts
             WHERE id = :id
             LIMIT 1'
        );

        $claimed = [];

        foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $rawId) {
            $id = (int) $rawId;

            $claim->execute([
                'processing' => 'processing',
                'updated_at' => $now,
                'id' => $id,
                'pending' => 'pending',
            ]);

            if ($claim->rowCount() !== 1) {
                continue;
            }

            $load->execute(['id' => $id]);
            $row = $load->fetch();

            if (is_array($row)) {
                $claimed[] = $this->hydrate($row);
            }
        }

        return $claimed;
    }

    /**
     * @param list<int> $connectionIds
     * @param array<int,?string> $customTexts
     */
    public function replaceSelection(
        int $publicationId,
        array $connectionIds,
        array $customTexts = [],
    ): void {
        $connectionIds = array_values(array_unique(array_filter(
            $connectionIds,
            static fn(mixed $id): bool =>
                is_int($id) && $id > 0,
        )));
        $selected = array_fill_keys($connectionIds, true);
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT id, connection_id
                 FROM publication_social_posts
                 WHERE publication_id = :publication_id'
            );
            $statement->execute([
                'publication_id' => $publicationId,
            ]);

            $existing = [];
            foreach ($statement->fetchAll() as $row) {
                $existing[(int) $row['connection_id']] =
                    (int) $row['id'];
            }

            $enable = $this->pdo->prepare(
                'UPDATE publication_social_posts
                 SET enabled = :enabled,
                     custom_text = :custom_text,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $disable = $this->pdo->prepare(
                'UPDATE publication_social_posts
                 SET enabled = :enabled,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $now = gmdate('Y-m-d H:i:s');

            foreach ($existing as $connectionId => $postId) {
                if (isset($selected[$connectionId])) {
                    $enable->execute([
                        'enabled' => 1,
                        'custom_text' =>
                            $customTexts[$connectionId] ?? null,
                        'updated_at' => $now,
                        'id' => $postId,
                    ]);
                    continue;
                }

                $disable->execute([
                    'enabled' => 0,
                    'updated_at' => $now,
                    'id' => $postId,
                ]);
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO publication_social_posts (
                    public_id,
                    publication_id,
                    connection_id,
                    enabled,
                    custom_text,
                    status,
                    attempts,
                    queued_at,
                    created_at,
                    updated_at
                 ) VALUES (
                    :public_id,
                    :publication_id,
                    :connection_id,
                    :enabled,
                    :custom_text,
                    :status,
                    0,
                    NULL,
                    :created_at,
                    :updated_at
                 )'
            );

            foreach ($connectionIds as $connectionId) {
                if (isset($existing[$connectionId])) {
                    continue;
                }

                $insert->execute([
                    'public_id' => Uuid::v4(),
                    'publication_id' => $publicationId,
                    'connection_id' => $connectionId,
                    'enabled' => 1,
                    'custom_text' =>
                        $customTexts[$connectionId] ?? null,
                    'status' => 'idle',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    public function queueEnabledForPublication(
        int $publicationId,
    ): int {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE publication_social_posts
             SET status = :pending,
                 queued_at = :queued_at,
                 last_error = NULL,
                 updated_at = :updated_at
             WHERE publication_id = :publication_id
               AND enabled = :enabled
               AND status = :idle'
        );
        $statement->execute([
            'pending' => 'pending',
            'queued_at' => $now,
            'updated_at' => $now,
            'publication_id' => $publicationId,
            'enabled' => 1,
            'idle' => 'idle',
        ]);

        return $statement->rowCount();
    }

    public function failedCount(): int
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) FROM publication_social_posts WHERE status = 'failed'"
        );

        return (int) $statement->fetchColumn();
    }

    public function markSent(int $id, ?string $remotePostId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE publication_social_posts
             SET status = :status,
                 remote_post_id = :remote_post_id,
                 sent_at = :sent_at,
                 last_error = NULL,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'status' => 'sent',
            'remote_post_id' => $remotePostId,
            'sent_at' => $now,
            'updated_at' => $now,
            'id' => $id,
        ]);
    }

    public function markFailed(int $id, string $error, int $maxAttempts): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE publication_social_posts
             SET attempts = attempts + 1,
                 status = CASE WHEN attempts + 1 >= :max_attempts THEN :failed ELSE :pending END,
                 last_error = :last_error,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'max_attempts' => max(1, $maxAttempts),
            'failed' => 'failed',
            'pending' => 'pending',
            'last_error' => substr($error, 0, 500),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    private function hydrate(array $row): SocialPost
    {
        return new SocialPost(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            publicationId: (int) $row['publication_id'],
            connectionId: (int) $row['connection_id'],
            enabled: in_array(strtolower((string) ($row['enabled'] ?? '')), ['1', 't', 'true'], true),
            customText: isset($row['custom_text']) && $row['custom_text'] !== '' ? (string) $row['custom_text'] : null,
            status: (string) $row['status'],
            remotePostId: isset($row['remote_post_id']) && $row['remote_post_id'] !== '' ? (string) $row['remote_post_id'] : null,
            attempts: (int) $row['attempts'],
            lastError: isset($row['last_error']) && $row['last_error'] !== '' ? (string) $row['last_error'] : null,
            queuedAt: !empty($row['queued_at']) ? new DateTimeImmutable((string) $row['queued_at']) : null,
            sentAt: !empty($row['sent_at']) ? new DateTimeImmutable((string) $row['sent_at']) : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
