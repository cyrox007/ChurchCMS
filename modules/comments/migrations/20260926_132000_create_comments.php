<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260926_132000_create_comments';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
CREATE TABLE publication_comments (
    id BIGSERIAL PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    publication_id BIGINT NOT NULL REFERENCES publications(id) ON DELETE CASCADE,
    status VARCHAR(20) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    body_text TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL,
    moderated_at TIMESTAMP NULL,
    moderator_user_id BIGINT NULL REFERENCES admin_users(id) ON DELETE SET NULL
)
SQL,
                'CREATE INDEX publication_comments_public_idx ON publication_comments (publication_id, status, created_at ASC)',
                'CREATE INDEX publication_comments_queue_idx ON publication_comments (status, created_at ASC)',
                <<<'SQL'
INSERT INTO permissions (permission_key, name)
VALUES ('comments.moderate', 'Модерация комментариев')
ON CONFLICT (permission_key) DO NOTHING
SQL,
            ],
            'mysql' => [
                <<<'SQL'
CREATE TABLE publication_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(36) NOT NULL UNIQUE,
    publication_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    body_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    moderated_at DATETIME NULL,
    moderator_user_id BIGINT UNSIGNED NULL,
    KEY publication_comments_public_idx (publication_id, status, created_at),
    KEY publication_comments_queue_idx (status, created_at),
    CONSTRAINT fk_publication_comments_publication FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    CONSTRAINT fk_publication_comments_moderator FOREIGN KEY (moderator_user_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
                <<<'SQL'
INSERT IGNORE INTO permissions (permission_key, name)
VALUES ('comments.moderate', 'Модерация комментариев')
SQL,
            ],
            default => throw new RuntimeException("Unsupported migration driver: {$driver}"),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
