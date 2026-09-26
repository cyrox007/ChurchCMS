<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Social;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use PDO;

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

    /** @return list<SocialPost> */
    public function pending(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        $statement = $this->pdo->prepare(
            'SELECT * FROM publication_social_posts
             WHERE status = :status
               AND enabled = :enabled
             ORDER BY queued_at ASC, id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':status', 'pending');
        $statement->bindValue(':enabled', 1, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn(array $row): SocialPost => $this->hydrate($row),
            $statement->fetchAll(),
        );
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
