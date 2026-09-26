<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Seo;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Core\PageCache;
use ChurchCMS\Modules\Publications\Publication;
use PDO;

final class PublicationSeoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @return array{
     *   seo_title:string,seo_description:string,seo_keywords:string,canonical_url:string,
     *   social_title:string,social_description:string,social_image_url:string,
     *   robots_index:bool,robots_follow:bool
     * }
     */
    public function formFor(Publication $publication): array
    {
        $row = $this->findRow($publication->id);

        return [
            'seo_title' => (string) ($row['seo_title'] ?? ''),
            'seo_description' => (string) ($row['seo_description'] ?? ''),
            'seo_keywords' => (string) ($row['seo_keywords'] ?? ''),
            'canonical_url' => (string) ($row['canonical_url'] ?? ''),
            'social_title' => (string) ($row['social_title'] ?? ''),
            'social_description' => (string) ($row['social_description'] ?? ''),
            'social_image_url' => (string) ($row['social_image_url'] ?? ''),
            'robots_index' => self::dbBool($row['robots_index'] ?? true),
            'robots_follow' => self::dbBool($row['robots_follow'] ?? true),
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    public function save(Publication $publication, array $input): void
    {
        $data = [
            'seo_title' => self::bounded((string) ($input['seo_title'] ?? ''), 255),
            'seo_description' => self::bounded((string) ($input['seo_description'] ?? ''), 500),
            'seo_keywords' => self::bounded((string) ($input['seo_keywords'] ?? ''), 1000),
            'canonical_url' => self::safeAbsoluteUrl((string) ($input['canonical_url'] ?? '')),
            'social_title' => self::bounded((string) ($input['social_title'] ?? ''), 255),
            'social_description' => self::bounded((string) ($input['social_description'] ?? ''), 500),
            'social_image_url' => self::safeAbsoluteUrl((string) ($input['social_image_url'] ?? '')),
            'robots_index' => ($input['robots_index'] ?? true) === true,
            'robots_follow' => ($input['robots_follow'] ?? true) === true,
        ];

        $existing = $this->findRow($publication->id);
        $now = gmdate('Y-m-d H:i:s');

        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO publication_seo (
                    publication_id, seo_title, seo_description, seo_keywords, canonical_url,
                    social_title, social_description, social_image_url,
                    robots_index, robots_follow, updated_at
                 ) VALUES (
                    :publication_id, :seo_title, :seo_description, :seo_keywords, :canonical_url,
                    :social_title, :social_description, :social_image_url,
                    :robots_index, :robots_follow, :updated_at
                 )'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE publication_seo
                 SET seo_title = :seo_title,
                     seo_description = :seo_description,
                     seo_keywords = :seo_keywords,
                     canonical_url = :canonical_url,
                     social_title = :social_title,
                     social_description = :social_description,
                     social_image_url = :social_image_url,
                     robots_index = :robots_index,
                     robots_follow = :robots_follow,
                     updated_at = :updated_at
                 WHERE publication_id = :publication_id'
            );
        }

        $statement->execute([
            'publication_id' => $publication->id,
            'seo_title' => $data['seo_title'] !== '' ? $data['seo_title'] : null,
            'seo_description' => $data['seo_description'] !== '' ? $data['seo_description'] : null,
            'seo_keywords' => $data['seo_keywords'] !== '' ? $data['seo_keywords'] : null,
            'canonical_url' => $data['canonical_url'] !== '' ? $data['canonical_url'] : null,
            'social_title' => $data['social_title'] !== '' ? $data['social_title'] : null,
            'social_description' => $data['social_description'] !== '' ? $data['social_description'] : null,
            'social_image_url' => $data['social_image_url'] !== '' ? $data['social_image_url'] : null,
            'robots_index' => $data['robots_index'] ? 1 : 0,
            'robots_follow' => $data['robots_follow'] ? 1 : 0,
            'updated_at' => $now,
        ]);

        PageCache::bumpVersion();
    }

    /** @return array<string,mixed> */
    public function metaFor(Publication $publication): array
    {
        $row = $this->findRow($publication->id) ?? [];
        $defaultCanonical = SeoRenderer::absoluteUrl(
            '/publications/' . rawurlencode($publication->slug)
        );

        $seoTitle = trim((string) ($row['seo_title'] ?? ''));
        $seoDescription = trim((string) ($row['seo_description'] ?? ''));
        $socialTitle = trim((string) ($row['social_title'] ?? ''));
        $socialDescription = trim((string) ($row['social_description'] ?? ''));
        $canonical = trim((string) ($row['canonical_url'] ?? ''));
        $image = trim((string) ($row['social_image_url'] ?? ''));

        return [
            'title' => $socialTitle !== '' ? $socialTitle : ($seoTitle !== '' ? $seoTitle : $publication->title),
            'description' => $socialDescription !== ''
                ? $socialDescription
                : ($seoDescription !== '' ? $seoDescription : $publication->excerpt),
            'canonical' => $canonical !== '' ? $canonical : $defaultCanonical,
            'image' => $image !== '' ? $image : (string) Config::get('seo.default_image', ''),
            'og_type' => 'article',
            'index' => self::dbBool($row['robots_index'] ?? true),
            'follow' => self::dbBool($row['robots_follow'] ?? true),
            'published_time' => $publication->publishedAt?->format(DATE_ATOM),
            'modified_time' => $publication->updatedAt->format(DATE_ATOM),
            'author' => $publication->authorName ?? '',
            'section' => $publication->type->value,
            'keywords' => trim((string) ($row['seo_keywords'] ?? '')),
        ];
    }

    /**
     * @return list<array{slug:string,updated_at:string}>
     */
    public function sitemapPublications(int $limit = 50000): array
    {
        $limit = max(1, min(50000, $limit));

        $statement = $this->pdo->prepare(
            'SELECT slug, updated_at
             FROM publications
             WHERE status = :status
               AND published_at IS NOT NULL
               AND published_at <= :now
             ORDER BY id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':status', 'published');
        $statement->bindValue(':now', gmdate('Y-m-d H:i:s'));
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map(
            static fn(array $row): array => [
                'slug' => (string) $row['slug'],
                'updated_at' => (string) $row['updated_at'],
            ],
            $statement->fetchAll(),
        ));
    }

    private function findRow(int $publicationId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM publication_seo WHERE publication_id = :publication_id LIMIT 1'
        );
        $statement->execute(['publication_id' => $publicationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private static function bounded(string $value, int $max): string
    {
        $value = trim(strip_tags($value));

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }

    private static function safeAbsoluteUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $url = SeoRenderer::absoluteUrl($value);
        return $url !== '' ? $url : '';
    }

    private static function dbBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 't', 'true', 'yes', 'on'], true);
    }
}
