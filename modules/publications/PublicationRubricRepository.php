<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Slugger;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PublicationRubricRepository
{
    private const MAX_DEPTH = 8;

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
     * @return list<array{
     *     public_id:string,
     *     name:string,
     *     slug:string,
     *     parent_public_id:?string,
     *     parent_name:?string,
     *     sort_order:int,
     *     usage_count:int,
     *     depth:int
     * }>
     */
    public function tree(string $siteKey = 'default'): array
    {
        $rows = $this->rows($siteKey);
        if ($rows === []) {
            return [];
        }

        $byId = [];
        $children = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $parentId = $row['parent_id'] === null
                ? 0
                : (int) $row['parent_id'];
            $byId[$id] = $row;
            $children[$parentId][] = $id;
        }

        $result = [];
        $visited = [];
        $append = function (
            int $id,
            int $depth,
        ) use (&$append, &$result, &$visited, $byId, $children): void {
            if (isset($visited[$id]) || !isset($byId[$id])) {
                return;
            }

            $visited[$id] = true;
            $row = $byId[$id];
            $result[] = self::publicRow(
                $row,
                min($depth, self::MAX_DEPTH),
            );

            if ($depth >= self::MAX_DEPTH) {
                return;
            }

            foreach ($children[$id] ?? [] as $childId) {
                $append($childId, $depth + 1);
            }
        };

        foreach ($children[0] ?? [] as $rootId) {
            $append($rootId, 0);
        }

        foreach (array_keys($byId) as $id) {
            $append((int) $id, 0);
        }

        return $result;
    }

    public function create(
        string $name,
        ?string $parentPublicId,
        int $sortOrder,
        string $siteKey = 'default',
    ): string {
        $name = $this->validName($name);
        $siteKey = $this->validSiteKey($siteKey);
        $parentId = $this->parentId(
            $parentPublicId,
            $siteKey,
        );
        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');
        $slug = $this->uniqueSlug(
            Slugger::fromText($name),
            $siteKey,
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO publication_categories (
                public_id,
                site_key,
                slug,
                name,
                parent_id,
                sort_order,
                is_managed,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :slug,
                :name,
                :parent_id,
                :sort_order,
                1,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'slug' => $slug,
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function update(
        string $publicId,
        string $name,
        ?string $parentPublicId,
        int $sortOrder,
        string $siteKey = 'default',
    ): void {
        $rubric = $this->find($publicId, $siteKey);
        if ($rubric === null) {
            throw new InvalidArgumentException(
                'Рубрика не найдена.'
            );
        }

        $name = $this->validName($name);
        $parentId = $this->parentId(
            $parentPublicId,
            $siteKey,
        );
        $id = (int) $rubric['id'];

        if ($parentId === $id) {
            throw new InvalidArgumentException(
                'Рубрика не может быть родительской для самой себя.'
            );
        }

        $this->assertNoCycle($id, $parentId);

        $slug = $this->uniqueSlug(
            Slugger::fromText($name),
            $siteKey,
            $id,
        );
        $statement = $this->pdo->prepare(
            'UPDATE publication_categories
             SET name = :name,
                 slug = :slug,
                 parent_id = :parent_id,
                 sort_order = :sort_order,
                 updated_at = :updated_at
             WHERE id = :id
               AND site_key = :site_key
               AND is_managed = 1'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
            'site_key' => $siteKey,
        ]);
    }

    public function delete(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        $rubric = $this->find($publicId, $siteKey);
        if ($rubric === null) {
            throw new InvalidArgumentException(
                'Рубрика не найдена.'
            );
        }

        $id = (int) $rubric['id'];
        if ($this->usageCount($id) > 0) {
            throw new InvalidArgumentException(
                'Нельзя удалить рубрику, пока она назначена публикациям.'
            );
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM publication_categories
             WHERE id = :id
               AND site_key = :site_key
               AND is_managed = 1'
        );
        $statement->execute([
            'id' => $id,
            'site_key' => $siteKey,
        ]);
    }

    /** @return array<string,mixed>|null */
    private function find(
        string $publicId,
        string $siteKey,
    ): ?array {
        $statement = $this->pdo->prepare(
            'SELECT id, public_id, site_key, slug, name, parent_id, sort_order
             FROM publication_categories
             WHERE public_id = :public_id
               AND site_key = :site_key
               AND is_managed = 1
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
            'site_key' => $this->validSiteKey($siteKey),
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $siteKey): array
    {
        $statement = $this->pdo->prepare(
            'SELECT
                c.id,
                c.public_id,
                c.name,
                c.slug,
                c.parent_id,
                c.sort_order,
                p.public_id AS parent_public_id,
                p.name AS parent_name,
                (
                    SELECT COUNT(*)
                    FROM publication_category_links l
                    WHERE l.category_id = c.id
                ) AS usage_count
             FROM publication_categories c
             LEFT JOIN publication_categories p
                ON p.id = c.parent_id
             WHERE c.site_key = :site_key
               AND c.is_managed = 1
             ORDER BY c.sort_order ASC, c.name ASC, c.id ASC'
        );
        $statement->execute([
            'site_key' => $this->validSiteKey($siteKey),
        ]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string,mixed> $row */
    private static function publicRow(
        array $row,
        int $depth,
    ): array {
        return [
            'public_id' => (string) $row['public_id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'parent_public_id' => $row['parent_public_id'] === null
                ? null
                : (string) $row['parent_public_id'],
            'parent_name' => $row['parent_name'] === null
                ? null
                : (string) $row['parent_name'],
            'sort_order' => (int) $row['sort_order'],
            'usage_count' => (int) $row['usage_count'],
            'depth' => $depth,
        ];
    }

    private function parentId(
        ?string $publicId,
        string $siteKey,
    ): ?int {
        $publicId = trim((string) $publicId);
        if ($publicId === '') {
            return null;
        }

        $parent = $this->find($publicId, $siteKey);
        if ($parent === null) {
            throw new InvalidArgumentException(
                'Родительская рубрика не найдена.'
            );
        }

        return (int) $parent['id'];
    }

    private function assertNoCycle(
        int $rubricId,
        ?int $parentId,
    ): void {
        $current = $parentId;
        $depth = 0;

        while ($current !== null && $depth <= self::MAX_DEPTH + 2) {
            if ($current === $rubricId) {
                throw new InvalidArgumentException(
                    'Нельзя создать кольцевую вложенность рубрик.'
                );
            }

            $statement = $this->pdo->prepare(
                'SELECT parent_id
                 FROM publication_categories
                 WHERE id = :id
                   AND is_managed = 1
                 LIMIT 1'
            );
            $statement->execute(['id' => $current]);
            $value = $statement->fetchColumn();
            $current = $value === false || $value === null
                ? null
                : (int) $value;
            $depth++;
        }

        if ($current !== null) {
            throw new InvalidArgumentException(
                'Слишком глубокая вложенность рубрик.'
            );
        }
    }

    private function usageCount(int $rubricId): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM publication_category_links
             WHERE category_id = :category_id'
        );
        $statement->execute([
            'category_id' => $rubricId,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function uniqueSlug(
        string $base,
        string $siteKey,
        ?int $exceptId = null,
    ): string {
        $base = rtrim(substr($base, 0, 108), '-');
        if ($base === '') {
            throw new InvalidArgumentException(
                'Не удалось сформировать адрес рубрики.'
            );
        }

        for ($suffix = 1; $suffix <= 99; $suffix++) {
            $slug = $suffix === 1
                ? $base
                : $base . '-' . $suffix;

            if (!$this->slugExists($slug, $siteKey, $exceptId)) {
                return $slug;
            }
        }

        throw new RuntimeException(
            'Не удалось подобрать уникальный адрес рубрики.'
        );
    }

    private function slugExists(
        string $slug,
        string $siteKey,
        ?int $exceptId,
    ): bool {
        $sql = 'SELECT id
                FROM publication_categories
                WHERE site_key = :site_key
                  AND slug = :slug';
        $params = [
            'site_key' => $siteKey,
            'slug' => $slug,
        ];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }

        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchColumn() !== false;
    }

    private function validName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $name = is_string($name) ? $name : '';
        $length = function_exists('mb_strlen')
            ? mb_strlen($name, 'UTF-8')
            : strlen($name);

        if ($name === '' || $length > 120) {
            throw new InvalidArgumentException(
                'Название рубрики должно содержать от 1 до 120 символов.'
            );
        }

        return $name;
    }

    private function validSiteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if ($siteKey === '' || strlen($siteKey) > 64) {
            throw new InvalidArgumentException(
                'Некорректный идентификатор сайта.'
            );
        }

        return $siteKey;
    }
}
