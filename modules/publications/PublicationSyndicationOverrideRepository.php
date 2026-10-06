<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;

final class PublicationSyndicationOverrideRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @param list<int> $publicationIds
     * @return array<int,array{title:?string,excerpt:?string,image_url:?string,image_mime:?string}>
     */
    public function forTarget(array $publicationIds, string $target): array
    {
        $target = $this->target($target);
        $publicationIds = array_values(array_unique(array_filter(
            array_map('intval', $publicationIds),
            static fn(int $id): bool => $id > 0,
        )));
        if ($publicationIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($publicationIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT publication_id, title_override, excerpt_override, image_url, image_mime '
            . 'FROM publication_syndication_overrides '
            . "WHERE target = ? AND publication_id IN ({$placeholders})"
        );
        $statement->execute([$target, ...$publicationIds]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(int) $row['publication_id']] = [
                'title' => $row['title_override'] !== null ? (string) $row['title_override'] : null,
                'excerpt' => $row['excerpt_override'] !== null ? (string) $row['excerpt_override'] : null,
                'image_url' => $row['image_url'] !== null ? (string) $row['image_url'] : null,
                'image_mime' => $row['image_mime'] !== null ? (string) $row['image_mime'] : null,
            ];
        }

        return $result;
    }

    /** @return array<string,array{title:?string,excerpt:?string,image_url:?string,image_mime:?string}> */
    public function forPublication(int $publicationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT target, title_override, excerpt_override, image_url, image_mime '
            . 'FROM publication_syndication_overrides '
            . 'WHERE publication_id = :publication_id ORDER BY target'
        );
        $statement->execute([':publication_id' => $publicationId]);

        $result = [];
        foreach ($statement->fetchAll() as $row) {
            $result[(string) $row['target']] = [
                'title' => $row['title_override'] !== null ? (string) $row['title_override'] : null,
                'excerpt' => $row['excerpt_override'] !== null ? (string) $row['excerpt_override'] : null,
                'image_url' => $row['image_url'] !== null ? (string) $row['image_url'] : null,
                'image_mime' => $row['image_mime'] !== null ? (string) $row['image_mime'] : null,
            ];
        }

        return $result;
    }

    public function save(
        int $publicationId,
        string $target,
        ?string $title,
        ?string $excerpt,
        ?string $imageUrl,
        ?string $imageMime,
    ): void {
        if ($publicationId <= 0) {
            throw new InvalidArgumentException('Публикация для переопределения не найдена.');
        }

        $target = $this->target($target);
        $title = $this->nullableText($title, 255);
        $excerpt = $this->nullableText($excerpt, 4000);
        $imageUrl = $this->imageUrl($imageUrl);
        $imageMime = $this->imageMime($imageMime, $imageUrl);

        if ($title === null && $excerpt === null && $imageUrl === null) {
            $this->delete($publicationId, $target);
            return;
        }

        $updatedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
INSERT INTO publication_syndication_overrides
    (publication_id, target, title_override, excerpt_override, image_url, image_mime, updated_at)
VALUES
    (:publication_id, :target, :title_override, :excerpt_override, :image_url, :image_mime, :updated_at)
ON CONFLICT (publication_id, target) DO UPDATE SET
    title_override = EXCLUDED.title_override,
    excerpt_override = EXCLUDED.excerpt_override,
    image_url = EXCLUDED.image_url,
    image_mime = EXCLUDED.image_mime,
    updated_at = EXCLUDED.updated_at
SQL,
            'mysql' => <<<'SQL'
INSERT INTO publication_syndication_overrides
    (publication_id, target, title_override, excerpt_override, image_url, image_mime, updated_at)
VALUES
    (:publication_id, :target, :title_override, :excerpt_override, :image_url, :image_mime, :updated_at)
ON DUPLICATE KEY UPDATE
    title_override = VALUES(title_override),
    excerpt_override = VALUES(excerpt_override),
    image_url = VALUES(image_url),
    image_mime = VALUES(image_mime),
    updated_at = VALUES(updated_at)
SQL,
            default => throw new InvalidArgumentException('Неподдерживаемая база данных.'),
        };

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ':publication_id' => $publicationId,
            ':target' => $target,
            ':title_override' => $title,
            ':excerpt_override' => $excerpt,
            ':image_url' => $imageUrl,
            ':image_mime' => $imageMime,
            ':updated_at' => $updatedAt,
        ]);
    }

    public function delete(int $publicationId, string $target): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM publication_syndication_overrides '
            . 'WHERE publication_id = :publication_id AND target = :target'
        );
        $statement->execute([
            ':publication_id' => $publicationId,
            ':target' => $this->target($target),
        ]);
    }

    private function target(string $target): string
    {
        $target = trim($target);
        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $target) !== 1) {
            throw new InvalidArgumentException('Некорректный идентификатор канала синдикации.');
        }

        return $target;
    }

    private function nullableText(?string $value, int $limit): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('Значение переопределения слишком длинное.');
        }

        return $value;
    }

    private function imageUrl(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (
            strlen($value) > 2048
            || filter_var($value, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https'
        ) {
            throw new InvalidArgumentException('URL изображения канала должен быть абсолютным HTTPS-адресом.');
        }

        return $value;
    }

    private function imageMime(?string $value, ?string $imageUrl): ?string
    {
        if ($imageUrl === null) {
            return null;
        }

        $value = strtolower(trim((string) $value));
        if ($value === '') {
            throw new InvalidArgumentException('Для изображения канала укажите MIME-тип.');
        }
        if (preg_match('#^image/[a-z0-9.+-]{1,100}$#D', $value) !== 1) {
            throw new InvalidArgumentException('Некорректный MIME-тип изображения канала.');
        }

        return $value;
    }
}
