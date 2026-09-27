<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_120000_add_publication_organization_owner';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
ALTER TABLE organization_units
    ADD CONSTRAINT organization_units_site_public_unique
    UNIQUE (site_key, public_id)
SQL,
                <<<'SQL'
ALTER TABLE publications
    ADD COLUMN owner_organization_public_id VARCHAR(36) NULL
SQL,
                <<<'SQL'
UPDATE publications AS publication
SET owner_organization_public_id = organization.public_id
FROM organization_site_roots AS site_root
INNER JOIN organization_units AS organization
    ON organization.id = site_root.organization_id
   AND organization.site_key = site_root.site_key
WHERE publication.site_key = site_root.site_key
  AND publication.owner_organization_public_id IS NULL
SQL,
                <<<'SQL'
ALTER TABLE publications
    ADD CONSTRAINT fk_publications_organization_owner
    FOREIGN KEY (site_key, owner_organization_public_id)
    REFERENCES organization_units (site_key, public_id)
    ON DELETE RESTRICT
SQL,
                <<<'SQL'
CREATE INDEX publications_organization_owner_idx
    ON publications (site_key, owner_organization_public_id, status, published_at)
SQL,
            ],
            'mysql' => [
                <<<'SQL'
ALTER TABLE organization_units
    ADD UNIQUE KEY organization_units_site_public_unique (site_key, public_id)
SQL,
                <<<'SQL'
ALTER TABLE publications
    ADD COLUMN owner_organization_public_id VARCHAR(36) NULL
SQL,
                <<<'SQL'
UPDATE publications AS publication
INNER JOIN organization_site_roots AS site_root
    ON site_root.site_key = publication.site_key
INNER JOIN organization_units AS organization
    ON organization.id = site_root.organization_id
   AND organization.site_key = site_root.site_key
SET publication.owner_organization_public_id = organization.public_id
WHERE publication.owner_organization_public_id IS NULL
SQL,
                <<<'SQL'
ALTER TABLE publications
    ADD CONSTRAINT fk_publications_organization_owner
    FOREIGN KEY (site_key, owner_organization_public_id)
    REFERENCES organization_units (site_key, public_id)
    ON DELETE RESTRICT
SQL,
                <<<'SQL'
CREATE INDEX publications_organization_owner_idx
    ON publications (site_key, owner_organization_public_id, status, published_at)
SQL,
            ],
            default => throw new RuntimeException(
                "Unsupported migration driver: {$driver}"
            ),
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
};
