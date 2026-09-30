<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Redirects;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class RedirectRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * @return list<RedirectRule>
     */
    public function all(string $siteKey = 'default'): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM redirect_rules
             WHERE site_key = :site_key
             ORDER BY source_path, id'
        );
        $statement->execute([
            'site_key' => $siteKey,
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?RedirectRule {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM redirect_rules
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
            'site_key' => $siteKey,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    public function findBySource(
        string $sourcePath,
        string $siteKey = 'default',
    ): ?RedirectRule {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM redirect_rules
             WHERE source_path = :source_path
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'source_path' => $sourcePath,
            'site_key' => $siteKey,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    public function enabledBySource(
        string $sourcePath,
        string $siteKey = 'default',
    ): ?RedirectRule {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM redirect_rules
             WHERE source_path = :source_path
               AND site_key = :site_key
               AND enabled = :enabled
             LIMIT 1'
        );
        $statement->execute([
            'source_path' => $sourcePath,
            'site_key' => $siteKey,
            'enabled' => 1,
        ]);
        $row = $statement->fetch();

        return is_array($row)
            ? self::hydrate($row)
            : null;
    }

    public function recordHit(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'UPDATE redirect_rules
             SET hit_count = hit_count + 1,
                 last_hit_at = :last_hit_at
             WHERE id = :id'
        );
        $statement->execute([
            'last_hit_at' => $now,
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM redirect_rules
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
        ]);
    }

    private static function hydrate(array $row): RedirectRule
    {
        return new RedirectRule(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            sourcePath: (string) $row['source_path'],
            targetPath: (string) $row['target_path'],
            statusCode: (int) $row['status_code'],
            enabled: (bool) $row['enabled'],
            hitCount: (int) $row['hit_count'],
            lastHitAt: isset($row['last_hit_at'])
                && $row['last_hit_at'] !== null
                    ? (string) $row['last_hit_at']
                    : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
