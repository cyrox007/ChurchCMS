<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Comments;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class CommentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @return list<Comment>
     */
    public function approvedForPublication(int $publicationId, int $limit = 200): array
    {
        $limit = max(1, min(200, $limit));

        $statement = $this->pdo->prepare(
            'SELECT c.*, p.title AS publication_title, p.slug AS publication_slug
             FROM publication_comments c
             INNER JOIN publications p ON p.id = c.publication_id
             WHERE c.publication_id = :publication_id
               AND c.status = :status
             ORDER BY c.created_at ASC, c.id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':publication_id', $publicationId, PDO::PARAM_INT);
        $statement->bindValue(':status', CommentStatus::Approved->value);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn(array $row): Comment => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<Comment>
     */
    public function moderationQueue(int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));

        $statement = $this->pdo->prepare(
            'SELECT c.*, p.title AS publication_title, p.slug AS publication_slug
             FROM publication_comments c
             INNER JOIN publications p ON p.id = c.publication_id
             WHERE c.status = :status
             ORDER BY c.created_at ASC, c.id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':status', CommentStatus::Pending->value);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn(array $row): Comment => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    public function findByPublicId(string $publicId): ?Comment
    {
        $statement = $this->pdo->prepare(
            'SELECT c.*, p.title AS publication_title, p.slug AS publication_slug
             FROM publication_comments c
             INNER JOIN publications p ON p.id = c.publication_id
             WHERE c.public_id = :public_id
             LIMIT 1'
        );
        $statement->execute(['public_id' => $publicId]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function countPending(): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM publication_comments WHERE status = :status'
        );
        $statement->execute(['status' => CommentStatus::Pending->value]);

        return (int) $statement->fetchColumn();
    }

    private function hydrate(array $row): Comment
    {
        $status = CommentStatus::tryFrom((string) ($row['status'] ?? ''));
        if ($status === null) {
            throw new RuntimeException('Invalid comment status.');
        }

        return new Comment(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            publicationId: (int) $row['publication_id'],
            status: $status,
            displayName: (string) $row['display_name'],
            email: isset($row['email']) && $row['email'] !== '' ? (string) $row['email'] : null,
            bodyText: (string) $row['body_text'],
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            moderatedAt: !empty($row['moderated_at']) ? new DateTimeImmutable((string) $row['moderated_at']) : null,
            moderatorUserId: isset($row['moderator_user_id']) ? (int) $row['moderator_user_id'] : null,
            publicationTitle: isset($row['publication_title']) ? (string) $row['publication_title'] : null,
            publicationSlug: isset($row['publication_slug']) ? (string) $row['publication_slug'] : null,
        );
    }
}
