<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class PublicationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * Internal write service. HTTP write routes are intentionally not exposed
     * until administrator authentication/RBAC/CSRF are implemented.
     *
     * @param list<string> $syndicationTargets
     */
    public function createDraft(
        string $title,
        string $slug,
        PublicationType $type = PublicationType::News,
        string $excerpt = '',
        string $bodyHtml = '',
        ?string $authorName = null,
        array $syndicationTargets = [],
        string $siteKey = 'default',
    ): string {
        $title = trim($title);
        $slug = strtolower(trim($slug));
        $siteKey = trim($siteKey);

        $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($title === '' || $titleLength > 255) {
            throw new InvalidArgumentException('Publication title is required and must be <= 255 characters.');
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1 || strlen($slug) > 180) {
            throw new InvalidArgumentException('Publication slug must use lowercase latin letters, numbers and hyphens.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $siteKey) !== 1) {
            throw new InvalidArgumentException('Invalid site key.');
        }

        $targets = self::normalizeTargets($syndicationTargets);
        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO publications (
                public_id, site_key, type, status, slug, title, excerpt, body_html,
                author_name, published_at, created_at, updated_at, syndication_targets,
                syndication_title, syndication_excerpt
             ) VALUES (
                :public_id, :site_key, :type, :status, :slug, :title, :excerpt, :body_html,
                :author_name, NULL, :created_at, :updated_at, :syndication_targets,
                NULL, NULL
             )'
        );

        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'type' => $type->value,
            'status' => PublicationStatus::Draft->value,
            'slug' => $slug,
            'title' => $title,
            'excerpt' => trim($excerpt),
            'body_html' => HtmlSanitizer::sanitize($bodyHtml),
            'author_name' => $authorName !== null ? trim($authorName) : null,
            'created_at' => $now,
            'updated_at' => $now,
            'syndication_targets' => json_encode($targets, JSON_THROW_ON_ERROR),
        ]);

        return $publicId;
    }

    public function publish(string $publicId, ?DateTimeImmutable $when = null): void
    {
        self::assertUuid($publicId);
        $publishedAt = ($when ?? new DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET status = :status, published_at = :published_at, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt->format('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);
    }

    public function withdraw(string $publicId): void
    {
        self::assertUuid($publicId);

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET status = :status, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => PublicationStatus::Withdrawn->value,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);
    }

    /** @param list<string> $targets */
    public function setSyndicationTargets(string $publicId, array $targets): void
    {
        self::assertUuid($publicId);
        $targets = self::normalizeTargets($targets);

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET syndication_targets = :targets, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'targets' => json_encode($targets, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);
    }

    /** @param list<string> $targets
     *  @return list<string>
     */
    private static function normalizeTargets(array $targets): array
    {
        $allowed = [];
        foreach ($targets as $target) {
            if (!is_string($target) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $target) !== 1) {
                throw new InvalidArgumentException('Invalid syndication target.');
            }
            $allowed[$target] = true;
        }

        $result = array_keys($allowed);
        sort($result, SORT_STRING);
        return $result;
    }

    private static function assertUuid(string $value): void
    {
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value) !== 1) {
            throw new InvalidArgumentException('Invalid public id.');
        }
    }
}
