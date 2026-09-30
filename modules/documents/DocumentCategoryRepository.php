<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Slugger;
use ChurchCMS\Core\Uuid;
use PDO;
use RuntimeException;

final class DocumentCategoryRepository
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
     * @return list<array{public_id:string,name:string,slug:string}>
     */
    public function forDocument(int $documentId): array
    {
        if ($documentId <= 0) {
            return [];
        }

        $all = $this->forDocuments([$documentId]);

        return $all[$documentId] ?? [];
    }

    /**
     * @param list<int> $documentIds
     * @return array<int,list<array{public_id:string,name:string,slug:string}>>
     */
    public function forDocuments(array $documentIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $documentIds),
            static fn(int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = [];
        }

        $placeholders = implode(
            ', ',
            array_fill(0, count($ids), '?'),
        );

        $statement = $this->pdo->prepare(
            "SELECT
                l.document_id,
                c.public_id,
                c.name,
                c.slug
             FROM document_category_links l
             INNER JOIN document_categories c
                ON c.id = l.category_id
             WHERE l.document_id IN ({$placeholders})
             ORDER BY
                l.document_id ASC,
                c.name ASC,
                c.id ASC"
        );

        foreach ($ids as $index => $documentId) {
            $statement->bindValue(
                $index + 1,
                $documentId,
                PDO::PARAM_INT,
            );
        }

        $statement->execute();

        foreach ($statement->fetchAll() as $row) {
            $documentId = (int) $row['document_id'];
            if (!isset($result[$documentId])) {
                continue;
            }

            $result[$documentId][] = [
                'public_id' => (string) $row['public_id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{public_id:string,name:string,slug:string}>
     */
    public function allForSite(
        string $siteKey = 'default',
        int $limit = 500,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT public_id, name, slug
             FROM document_categories
             WHERE site_key = :site_key
             ORDER BY name ASC, id ASC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);

        return array_map(
            static fn(array $row): array => [
                'public_id' => (string) $row['public_id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ],
            $statement->fetchAll(),
        );
    }

    /**
     * @param list<string> $names
     */
    public function replaceForDocument(
        int $documentId,
        string $siteKey,
        array $names,
    ): void {
        if ($documentId <= 0) {
            throw new RuntimeException(
                'Не найден документ для сохранения рубрик.'
            );
        }

        $delete = $this->pdo->prepare(
            'DELETE FROM document_category_links
             WHERE document_id = :document_id'
        );
        $delete->execute([
            'document_id' => $documentId,
        ]);

        if ($names !== []) {
            $insert = $this->pdo->prepare(
                'INSERT INTO document_category_links (
                    document_id,
                    category_id
                 ) VALUES (
                    :document_id,
                    :category_id
                 )'
            );

            foreach ($names as $name) {
                $insert->execute([
                    'document_id' => $documentId,
                    'category_id' => $this->ensureCategory(
                        $siteKey,
                        $name,
                    ),
                ]);
            }
        }

        $this->cleanupOrphans($siteKey);
    }

    private function ensureCategory(
        string $siteKey,
        string $name,
    ): int {
        $slug = rtrim(
            substr(
                Slugger::fromText($name),
                0,
                120,
            ),
            '-',
        );

        if ($slug === '') {
            throw new RuntimeException(
                'Не удалось сформировать безопасный адрес рубрики документа.'
            );
        }

        $existing = $this->findId(
            $siteKey,
            $slug,
        );
        if ($existing !== null) {
            return $existing;
        }

        $now = gmdate('Y-m-d H:i:s');
        $driver = (string) $this->pdo->getAttribute(
            PDO::ATTR_DRIVER_NAME,
        );
        $sql = <<<'SQL'
INSERT INTO document_categories (
    public_id,
    site_key,
    slug,
    name,
    created_at,
    updated_at
) VALUES (
    :public_id,
    :site_key,
    :slug,
    :name,
    :created_at,
    :updated_at
)
SQL;

        if ($driver === 'pgsql') {
            $sql .=
                ' ON CONFLICT (site_key, slug) DO NOTHING RETURNING id';
        } elseif ($driver === 'mysql') {
            $sql .=
                ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        } else {
            throw new RuntimeException(
                "Неподдерживаемый драйвер рубрик документов: {$driver}"
            );
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'public_id' => Uuid::v4(),
            'site_key' => $siteKey,
            'slug' => $slug,
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = $driver === 'pgsql'
            ? $statement->fetchColumn()
            : $this->pdo->lastInsertId();

        if ($id !== false && (int) $id > 0) {
            return (int) $id;
        }

        $existing = $this->findId(
            $siteKey,
            $slug,
        );
        if ($existing === null) {
            throw new RuntimeException(
                'Не удалось создать или найти рубрику документа.'
            );
        }

        return $existing;
    }

    private function findId(
        string $siteKey,
        string $slug,
    ): ?int {
        $statement = $this->pdo->prepare(
            'SELECT id
             FROM document_categories
             WHERE site_key = :site_key
               AND slug = :slug
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'slug' => $slug,
        ]);

        $value = $statement->fetchColumn();

        return $value === false
            ? null
            : (int) $value;
    }

    private function cleanupOrphans(
        string $siteKey,
    ): void {
        $statement = $this->pdo->prepare(
            'DELETE FROM document_categories
             WHERE site_key = :site_key
               AND NOT EXISTS (
                    SELECT 1
                    FROM document_category_links l
                    WHERE l.category_id = document_categories.id
               )'
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);
    }
}
