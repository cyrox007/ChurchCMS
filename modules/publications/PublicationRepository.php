<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PublicationRepository
{
    public function __construct(
        private readonly PDO $pdo = new \PDO('sqlite::memory:'),
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @return list<Publication>
     */
    public function published(
        string $siteKey = 'default',
        int $limit = 20,
        int $offset = 0,
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $statement = $this->pdo->prepare(
            'SELECT * FROM publications
             WHERE site_key = :site_key
               AND status = :status
               AND published_at IS NOT NULL
               AND published_at <= :now
             ORDER BY published_at DESC, id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(':status', PublicationStatus::Published->value);
        $statement->bindValue(':now', gmdate('Y-m-d H:i:s'));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn(array $row): Publication => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    public function findPublishedBySlug(string $slug, string $siteKey = 'default'): ?Publication
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM publications
             WHERE site_key = :site_key
               AND slug = :slug
               AND status = :status
               AND published_at IS NOT NULL
               AND published_at <= :now
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'slug' => $slug,
            'status' => PublicationStatus::Published->value,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return list<Publication>
     */
    public function syndicated(string $target, string $siteKey = 'default', int $limit = 100): array
    {
        $items = $this->published($siteKey, min(100, max(1, $limit)), 0);

        return array_values(array_filter(
            $items,
            static fn(Publication $publication): bool =>
                in_array($target, $publication->syndicationTargets, true),
        ));
    }

    private function hydrate(array $row): Publication
    {
        $targets = json_decode((string) ($row['syndication_targets'] ?? '[]'), true);
        if (!is_array($targets)) {
            $targets = [];
        }

        $type = PublicationType::tryFrom((string) ($row['type'] ?? ''));
        $status = PublicationStatus::tryFrom((string) ($row['status'] ?? ''));

        if ($type === null || $status === null) {
            throw new RuntimeException('Invalid publication row enum value.');
        }

        return new Publication(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            type: $type,
            status: $status,
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            excerpt: (string) ($row['excerpt'] ?? ''),
            bodyHtml: (string) ($row['body_html'] ?? ''),
            authorName: isset($row['author_name']) ? (string) $row['author_name'] : null,
            publishedAt: !empty($row['published_at']) ? new DateTimeImmutable((string) $row['published_at']) : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
            syndicationTargets: array_values(array_filter($targets, 'is_string')),
            syndicationTitle: isset($row['syndication_title']) && $row['syndication_title'] !== '' ? (string) $row['syndication_title'] : null,
            syndicationExcerpt: isset($row['syndication_excerpt']) && $row['syndication_excerpt'] !== '' ? (string) $row['syndication_excerpt'] : null,
        );
    }
}
