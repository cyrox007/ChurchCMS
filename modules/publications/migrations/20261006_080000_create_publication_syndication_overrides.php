<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20261006_080000_create_publication_syndication_overrides';
    }

    public function up(\PDO $pdo, string $driver): void
    {
        $sql = match ($driver) {
            'pgsql' => <<<'SQL'
CREATE TABLE publication_syndication_overrides (
    id BIGSERIAL PRIMARY KEY,
    publication_id BIGINT NOT NULL,
    target VARCHAR(64) NOT NULL,
    title_override VARCHAR(255) NULL,
    excerpt_override TEXT NULL,
    image_url VARCHAR(2048) NULL,
    image_mime VARCHAR(128) NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT publication_syndication_override_unique UNIQUE (publication_id, target),
    CONSTRAINT publication_syndication_override_publication_fk
        FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
)
SQL,
            'mysql' => <<<'SQL'
CREATE TABLE publication_syndication_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    publication_id BIGINT UNSIGNED NOT NULL,
    target VARCHAR(64) NOT NULL,
    title_override VARCHAR(255) NULL,
    excerpt_override TEXT NULL,
    image_url VARCHAR(2048) NULL,
    image_mime VARCHAR(128) NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY publication_syndication_override_unique (publication_id, target),
    KEY publication_syndication_override_target_idx (target, publication_id),
    CONSTRAINT publication_syndication_override_publication_fk
        FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            default => throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            ),
        };

        $pdo->exec($sql);

        if ($driver === 'pgsql') {
            $pdo->exec(
                'CREATE INDEX publication_syndication_override_target_idx '
                . 'ON publication_syndication_overrides (target, publication_id)'
            );
        }
    }

    public function down(\PDO $pdo, string $driver): void
    {
        if (!in_array($driver, ['pgsql', 'mysql'], true)) {
            throw new \RuntimeException(
                "Неподдерживаемый драйвер миграции: {$driver}"
            );
        }

        $pdo->exec('DROP TABLE publication_syndication_overrides');
    }
};
