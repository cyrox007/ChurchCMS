<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PageRepository
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

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?Page {
        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function findById(
        int $id,
        string $siteKey = 'default',
    ): ?Page {
        if ($id <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE id = :id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'id' => $id,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function findByPath(
        string $path,
        string $siteKey = 'default',
    ): ?Page {
        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE path = :path
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'path' => $path,
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    /**
     * @return list<Page>
     */
    public function tree(string $siteKey = 'default'): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_key = :site_key
             ORDER BY path ASC, sort_order ASC, id ASC'
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);

        return array_map(
            fn(array $row): Page => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    /**
     * Возвращает опубликованные страницы в стабильном порядке дерева.
     *
     * @return list<Page>
     */
    public function published(
        string $siteKey = 'default',
        int $limit = 100,
        int $offset = 0,
    ): array {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_key = :site_key
               AND status = :status
               AND published_at IS NOT NULL
               AND published_at <= :now
             ORDER BY path ASC, sort_order ASC, id ASC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue(':site_key', $siteKey);
        $statement->bindValue(
            ':status',
            PageStatus::Published->value,
        );
        $statement->bindValue(
            ':now',
            gmdate('Y-m-d H:i:s'),
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn(array $row): Page => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    public function findPublishedByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?Page {
        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE public_id = :public_id
               AND site_key = :site_key
               AND status = :status
               AND published_at IS NOT NULL
               AND published_at <= :now
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'status' => PageStatus::Published->value,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $this->hydrate($row)
            : null;
    }

    public function countPublished(
        string $siteKey = 'default',
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pages
             WHERE site_key = :site_key
               AND status = :status
               AND published_at IS NOT NULL
               AND published_at <= :now'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'status' => PageStatus::Published->value,
            'now' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param list<int> $ids
     * @return array<int,string>
     */
    public function publicIdsByIds(
        array $ids,
        string $siteKey = 'default',
    ): array {
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn(mixed $id): bool =>
                is_int($id) && $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(
            ', ',
            array_fill(0, count($ids), '?'),
        );
        $statement = $this->pdo->prepare(
            'SELECT id, public_id FROM pages
             WHERE site_key = ?
               AND id IN (' . $placeholders . ')'
        );
        $statement->execute([$siteKey, ...$ids]);
        $result = [];

        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['id']] = (string) $row['public_id'];
        }

        return $result;
    }

    /**
     * Возвращает узел и всё его поддерево в порядке от корня к потомкам.
     *
     * @return list<Page>
     */
    public function subtree(
        Page $root,
    ): array {
        $pattern = $root->path . '/%';

        $statement = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_key = :site_key
               AND (
                    id = :id
                    OR path LIKE :pattern
               )
             ORDER BY path ASC, id ASC'
        );
        $statement->execute([
            'site_key' => $root->siteKey,
            'id' => $root->id,
            'pattern' => $pattern,
        ]);

        return array_map(
            fn(array $row): Page => $this->hydrate($row),
            $statement->fetchAll(),
        );
    }

    public function pathExists(
        string $path,
        string $siteKey,
        ?int $ignoreId = null,
    ): bool {
        $sql = 'SELECT 1 FROM pages
                WHERE site_key = :site_key
                  AND path = :path';

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
        }

        $sql .= ' LIMIT 1';

        $statement = $this->pdo->prepare($sql);
        $params = [
            'site_key' => $siteKey,
            'path' => $path,
        ];

        if ($ignoreId !== null) {
            $params['ignore_id'] = $ignoreId;
        }

        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    private function hydrate(array $row): Page
    {
        $status = PageStatus::tryFrom(
            (string) ($row['status'] ?? '')
        );

        if ($status === null) {
            throw new RuntimeException(
                'Страница содержит неизвестный статус.'
            );
        }

        return new Page(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                isset($row['owner_organization_public_id'])
                && $row['owner_organization_public_id'] !== ''
                    ? (string) $row['owner_organization_public_id']
                    : null,
            parentId: isset($row['parent_id'])
                ? (int) $row['parent_id']
                : null,
            status: $status,
            slug: (string) $row['slug'],
            path: (string) $row['path'],
            title: (string) $row['title'],
            navigationTitle: isset($row['navigation_title'])
                && $row['navigation_title'] !== ''
                ? (string) $row['navigation_title']
                : null,
            bodyHtml: (string) ($row['body_html'] ?? ''),
            sortOrder: (int) ($row['sort_order'] ?? 0),
            publishedAt: !empty($row['published_at'])
                ? new DateTimeImmutable(
                    (string) $row['published_at']
                )
                : null,
            createdAt: new DateTimeImmutable(
                (string) $row['created_at']
            ),
            updatedAt: new DateTimeImmutable(
                (string) $row['updated_at']
            ),
        );
    }
}
