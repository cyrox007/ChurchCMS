<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Slugger;
use ChurchCMS\Core\Uuid;
use PDO;
use RuntimeException;

final class PublicationTaxonomyRepository
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
     * @return array{
     *     categories:list<array{public_id:string,name:string,slug:string}>,
     *     tags:list<array{public_id:string,name:string,slug:string}>
     * }
     */
    public function forPublication(int $publicationId): array
    {
        if ($publicationId <= 0) {
            return [
                'categories' => [],
                'tags' => [],
            ];
        }

        $all = $this->forPublications([$publicationId]);

        return $all[$publicationId] ?? [
            'categories' => [],
            'tags' => [],
        ];
    }

    /**
     * @param list<int> $publicationIds
     * @return array<int,array{
     *     categories:list<array{public_id:string,name:string,slug:string}>,
     *     tags:list<array{public_id:string,name:string,slug:string}>
     * }>
     */
    public function forPublications(array $publicationIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $publicationIds),
            static fn(int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $result = [];
        foreach ($ids as $id) {
            $result[$id] = [
                'categories' => [],
                'tags' => [],
            ];
        }

        $this->loadTermsForPublications(
            $ids,
            'category',
            $result,
        );
        $this->loadTermsForPublications(
            $ids,
            'tag',
            $result,
        );

        return $result;
    }

    /**
     * @param list<string> $categories
     * @param list<string> $tags
     */
    public function replaceForPublication(
        int $publicationId,
        string $siteKey,
        array $categories,
        array $tags,
    ): void {
        if ($publicationId <= 0) {
            throw new RuntimeException(
                'Не найдена публикация для сохранения категорий и тегов.'
            );
        }

        $this->replaceKind(
            $publicationId,
            $siteKey,
            'category',
            $categories,
        );
        $this->replaceKind(
            $publicationId,
            $siteKey,
            'tag',
            $tags,
        );
    }

    /**
     * @param list<int> $publicationIds
     * @param array<int,array{
     *     categories:list<array{public_id:string,name:string,slug:string}>,
     *     tags:list<array{public_id:string,name:string,slug:string}>
     * }> $result
     */
    private function loadTermsForPublications(
        array $publicationIds,
        string $kind,
        array &$result,
    ): void {
        [$termTable, $linkTable, $foreignKey] = self::tables(
            $kind,
        );
        $placeholders = implode(
            ', ',
            array_fill(0, count($publicationIds), '?'),
        );
        $bucket = $kind === 'category'
            ? 'categories'
            : 'tags';

        $statement = $this->pdo->prepare(
            "SELECT l.publication_id, t.public_id, t.name, t.slug
             FROM {$linkTable} l
             INNER JOIN {$termTable} t
                ON t.id = l.{$foreignKey}
             WHERE l.publication_id IN ({$placeholders})
             ORDER BY l.publication_id ASC, t.slug ASC, t.id ASC"
        );

        foreach ($publicationIds as $index => $publicationId) {
            $statement->bindValue(
                $index + 1,
                $publicationId,
                PDO::PARAM_INT,
            );
        }

        $statement->execute();

        foreach ($statement->fetchAll() as $row) {
            $publicationId = (int) $row['publication_id'];
            if (!isset($result[$publicationId])) {
                continue;
            }

            $result[$publicationId][$bucket][] = [
                'public_id' => (string) $row['public_id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }
    }

    /**
     * @param list<string> $names
     */
    private function replaceKind(
        int $publicationId,
        string $siteKey,
        string $kind,
        array $names,
    ): void {
        [, $linkTable, $foreignKey] = self::tables($kind);

        $delete = $this->pdo->prepare(
            "DELETE FROM {$linkTable}
             WHERE publication_id = :publication_id"
        );
        $delete->execute([
            'publication_id' => $publicationId,
        ]);

        if ($names === []) {
            $this->cleanupOrphans($kind, $siteKey);
            return;
        }

        $insert = $this->pdo->prepare(
            "INSERT INTO {$linkTable} (
                publication_id,
                {$foreignKey}
             ) VALUES (
                :publication_id,
                :term_id
             )"
        );

        foreach ($names as $name) {
            $termId = $this->ensureTerm(
                $kind,
                $siteKey,
                $name,
            );

            $insert->execute([
                'publication_id' => $publicationId,
                'term_id' => $termId,
            ]);
        }

        $this->cleanupOrphans($kind, $siteKey);
    }

    private function cleanupOrphans(
        string $kind,
        string $siteKey,
    ): void {
        [$termTable, $linkTable, $foreignKey] = self::tables(
            $kind,
        );

        $statement = $this->pdo->prepare(
            "DELETE FROM {$termTable}
             WHERE site_key = :site_key
               AND NOT EXISTS (
                    SELECT 1
                    FROM {$linkTable} l
                    WHERE l.{$foreignKey} = {$termTable}.id
               )"
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);
    }

    private function ensureTerm(
        string $kind,
        string $siteKey,
        string $name,
    ): int {
        [$termTable] = self::tables($kind);
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
                'Не удалось сформировать безопасный адрес категории или тега.'
            );
        }

        $existing = $this->findTermId(
            $termTable,
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
        $sql = "INSERT INTO {$termTable} (
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
                )";

        if ($driver === 'pgsql') {
            $sql .= ' ON CONFLICT (site_key, slug) DO NOTHING RETURNING id';
        } elseif ($driver === 'mysql') {
            $sql .= ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        } else {
            throw new RuntimeException(
                "Неподдерживаемый драйвер таксономии: {$driver}"
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

        $existing = $this->findTermId(
            $termTable,
            $siteKey,
            $slug,
        );

        if ($existing === null) {
            throw new RuntimeException(
                'Не удалось создать или найти категорию/тег.'
            );
        }

        return $existing;
    }

    private function findTermId(
        string $table,
        string $siteKey,
        string $slug,
    ): ?int {
        $statement = $this->pdo->prepare(
            "SELECT id FROM {$table}
             WHERE site_key = :site_key
               AND slug = :slug
             LIMIT 1"
        );
        $statement->execute([
            'site_key' => $siteKey,
            'slug' => $slug,
        ]);

        $value = $statement->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function tables(string $kind): array
    {
        return match ($kind) {
            'category' => [
                'publication_categories',
                'publication_category_links',
                'category_id',
            ],
            'tag' => [
                'publication_tags',
                'publication_tag_links',
                'tag_id',
            ],
            default => throw new RuntimeException(
                'Неподдерживаемый тип таксономии публикации.'
            ),
        };
    }
}
