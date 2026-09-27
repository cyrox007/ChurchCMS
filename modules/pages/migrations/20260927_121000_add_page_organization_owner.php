<?php

declare(strict_types=1);

use ChurchCMS\Core\Migration;
use PDO;
use RuntimeException;

return new class implements Migration {
    public function id(): string
    {
        return '20260927_121000_add_page_organization_owner';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $statements = match ($driver) {
            'pgsql' => [
                <<<'SQL'
ALTER TABLE pages
    ADD COLUMN owner_organization_public_id VARCHAR(36) NULL
SQL,
                <<<'SQL'
UPDATE pages AS page
SET owner_organization_public_id = organization.public_id
FROM organization_site_roots AS site_root
INNER JOIN organization_units AS organization
    ON organization.id = site_root.organization_id
   AND organization.site_key = site_root.site_key
WHERE page.site_key = site_root.site_key
  AND page.owner_organization_public_id IS NULL
SQL,
                <<<'SQL'
ALTER TABLE pages
    ADD CONSTRAINT fk_pages_organization_owner
    FOREIGN KEY (site_key, owner_organization_public_id)
    REFERENCES organization_units (site_key, public_id)
    ON DELETE RESTRICT
SQL,
                <<<'SQL'
CREATE INDEX pages_organization_owner_idx
    ON pages (site_key, owner_organization_public_id, status, id)
SQL,
            ],
            'mysql' => [
                <<<'SQL'
ALTER TABLE pages
    ADD COLUMN owner_organization_public_id VARCHAR(36) NULL
SQL,
                <<<'SQL'
UPDATE pages AS page
INNER JOIN organization_site_roots AS site_root
    ON site_root.site_key = page.site_key
INNER JOIN organization_units AS organization
    ON organization.id = site_root.organization_id
   AND organization.site_key = site_root.site_key
SET page.owner_organization_public_id = organization.public_id
WHERE page.owner_organization_public_id IS NULL
SQL,
                <<<'SQL'
ALTER TABLE pages
    ADD CONSTRAINT fk_pages_organization_owner
    FOREIGN KEY (site_key, owner_organization_public_id)
    REFERENCES organization_units (site_key, public_id)
    ON DELETE RESTRICT
SQL,
                <<<'SQL'
CREATE INDEX pages_organization_owner_idx
    ON pages (site_key, owner_organization_public_id, status, id)
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
