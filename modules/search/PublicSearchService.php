<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Search;

use ChurchCMS\Core\DatabaseManager;
use InvalidArgumentException;
use PDO;

final class PublicSearchService
{
    private const QUERY_MIN = 2;
    private const QUERY_MAX = 120;
    private const TYPE_LIMIT = 20;
    private const TOTAL_LIMIT = 50;

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
     *   query:string,
     *   items:list<array{
     *     type:string,
     *     title:string,
     *     excerpt:string,
     *     url:string,
     *     score:int
     *   }>
     * }
     */
    public function search(
        string $query,
        string $siteKey = 'default',
    ): array {
        $query = self::query($query);
        $pattern = '%' . self::escapeLike($query) . '%';

        $items = array_merge(
            $this->publications($pattern, $siteKey),
            $this->pages($pattern, $siteKey),
            $this->documents($pattern, $siteKey),
            $this->people($pattern, $siteKey),
            $this->events($pattern, $siteKey),
        );

        foreach ($items as &$item) {
            $item['score'] = self::score(
                $query,
                (string) $item['title'],
            );
        }
        unset($item);

        usort(
            $items,
            static function (array $left, array $right): int {
                $score = $right['score'] <=> $left['score'];
                if ($score !== 0) {
                    return $score;
                }

                $type = strcmp(
                    (string) $left['type'],
                    (string) $right['type'],
                );
                if ($type !== 0) {
                    return $type;
                }

                return strcasecmp(
                    (string) $left['title'],
                    (string) $right['title'],
                );
            },
        );

        return [
            'query' => $query,
            'items' => array_slice(
                $items,
                0,
                self::TOTAL_LIMIT,
            ),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function publications(string $pattern, string $siteKey): array
    {
        return $this->rows(
            "SELECT 'publication' AS type, title, excerpt, slug
             FROM publications
             WHERE site_key = :site_key
               AND status = 'published'
               AND published_at IS NOT NULL
               AND published_at <= :now
               AND (
                   LOWER(title) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(slug) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(excerpt) LIKE LOWER(:pattern) ESCAPE '!'
               )
             ORDER BY published_at DESC, id DESC
             LIMIT 20",
            [
                'site_key' => $siteKey,
                'now' => gmdate('Y-m-d H:i:s'),
                'pattern' => $pattern,
            ],
            static fn(array $row): array => [
                'type' => 'publication',
                'title' => (string) $row['title'],
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'url' => '/publications/' . rawurlencode((string) $row['slug']),
            ],
        );
    }

    /** @return list<array<string,mixed>> */
    private function pages(string $pattern, string $siteKey): array
    {
        return $this->rows(
            "SELECT 'page' AS type, title, path, body_html
             FROM pages
             WHERE site_key = :site_key
               AND status = 'published'
               AND published_at IS NOT NULL
               AND published_at <= :now
               AND (
                   LOWER(title) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(path) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(body_html) LIKE LOWER(:pattern) ESCAPE '!'
               )
             ORDER BY updated_at DESC, id DESC
             LIMIT 20",
            [
                'site_key' => $siteKey,
                'now' => gmdate('Y-m-d H:i:s'),
                'pattern' => $pattern,
            ],
            static fn(array $row): array => [
                'type' => 'page',
                'title' => (string) $row['title'],
                'excerpt' => self::excerpt((string) ($row['body_html'] ?? '')),
                'url' => '/pages/' . ltrim((string) $row['path'], '/'),
            ],
        );
    }

    /** @return list<array<string,mixed>> */
    private function documents(string $pattern, string $siteKey): array
    {
        return $this->rows(
            "SELECT 'document' AS type, public_id, title, summary
             FROM documents
             WHERE site_key = :site_key
               AND status = 'published'
               AND visibility = 'public'
               AND (
                   LOWER(title) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(summary) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(document_number) LIKE LOWER(:pattern) ESCAPE '!'
               )
             ORDER BY updated_at DESC, id DESC
             LIMIT 20",
            [
                'site_key' => $siteKey,
                'pattern' => $pattern,
            ],
            static fn(array $row): array => [
                'type' => 'document',
                'title' => (string) $row['title'],
                'excerpt' => (string) ($row['summary'] ?? ''),
                'url' => '/documents/' . rawurlencode((string) $row['public_id']),
            ],
        );
    }

    /** @return list<array<string,mixed>> */
    private function people(string $pattern, string $siteKey): array
    {
        return $this->rows(
            "SELECT 'person' AS type, public_id, display_name, biography_html
             FROM people
             WHERE site_key = :site_key
               AND status = 'active'
               AND (
                   LOWER(display_name) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(first_name) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(middle_name) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(last_name) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(biography_html) LIKE LOWER(:pattern) ESCAPE '!'
               )
             ORDER BY display_name ASC, id ASC
             LIMIT 20",
            [
                'site_key' => $siteKey,
                'pattern' => $pattern,
            ],
            static fn(array $row): array => [
                'type' => 'person',
                'title' => (string) $row['display_name'],
                'excerpt' => self::excerpt((string) ($row['biography_html'] ?? '')),
                'url' => '/people/' . rawurlencode((string) $row['public_id']),
            ],
        );
    }

    /** @return list<array<string,mixed>> */
    private function events(string $pattern, string $siteKey): array
    {
        return $this->rows(
            "SELECT 'event' AS type, public_id, title, excerpt, location_name
             FROM events
             WHERE site_key = :site_key
               AND status = 'published'
               AND (
                   LOWER(title) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(excerpt) LIKE LOWER(:pattern) ESCAPE '!'
                   OR LOWER(location_name) LIKE LOWER(:pattern) ESCAPE '!'
               )
             ORDER BY starts_at DESC, id DESC
             LIMIT 20",
            [
                'site_key' => $siteKey,
                'pattern' => $pattern,
            ],
            static fn(array $row): array => [
                'type' => 'event',
                'title' => (string) $row['title'],
                'excerpt' => (string) ($row['excerpt'] ?? ''),
                'url' => '/events/' . rawurlencode((string) $row['public_id']),
            ],
        );
    }

    /**
     * @param array<string,mixed> $parameters
     * @param callable(array<string,mixed>):array<string,mixed> $project
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql, array $parameters, callable $project): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return array_map($project, $statement->fetchAll());
    }

    private static function query(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $value = is_string($value) ? $value : '';
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length < self::QUERY_MIN || $length > self::QUERY_MAX) {
            throw new InvalidArgumentException(
                'Поисковый запрос должен содержать от 2 до 120 символов.'
            );
        }

        return $value;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    private static function score(string $query, string $title): int
    {
        $query = self::lower($query);
        $title = self::lower(trim($title));

        if ($title === $query) {
            return 300;
        }

        if (str_starts_with($title, $query)) {
            return 200;
        }

        if (str_contains($title, $query)) {
            return 100;
        }

        return 10;
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private static function excerpt(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = is_string($value) ? $value : '';

        if ($value === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 240, 'UTF-8')
            : substr($value, 0, 240);
    }
}
