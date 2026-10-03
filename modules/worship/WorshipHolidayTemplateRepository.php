<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class WorshipHolidayTemplateRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /** @return array<string,mixed>|null */
    public function find(
        string $publicId,
        string $siteKey = 'default',
    ): ?array {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_holiday_templates
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($publicId),
            'site_key' => $siteKey,
        ]);

        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function items(int $templateId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_holiday_template_items
             WHERE template_id = :template_id
             ORDER BY sort_order ASC, id ASC'
        );
        $statement->execute([
            'template_id' => $templateId,
        ]);

        return $statement->fetchAll();
    }

    public function applicationExists(
        int $itemId,
        string $siteKey,
        string $ownerPublicId,
        string $feastDate,
    ): bool {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM worship_holiday_applications
             WHERE template_item_id = :item_id
               AND site_key = :site_key
               AND owner_organization_public_id = :owner
               AND feast_date = :feast_date
             LIMIT 1'
        );
        $statement->execute([
            'item_id' => $itemId,
            'site_key' => $siteKey,
            'owner' => $ownerPublicId,
            'feast_date' => $feastDate,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function recordApplication(
        int $templateId,
        int $itemId,
        string $siteKey,
        string $ownerPublicId,
        string $feastDate,
        string $worshipPublicId,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO worship_holiday_applications (
                template_id,
                template_item_id,
                site_key,
                owner_organization_public_id,
                feast_date,
                worship_public_id,
                created_at
             ) VALUES (
                :template_id,
                :item_id,
                :site_key,
                :owner,
                :feast_date,
                :worship_public_id,
                :created_at
             )'
        );
        $statement->execute([
            'template_id' => $templateId,
            'item_id' => $itemId,
            'site_key' => $siteKey,
            'owner' => $ownerPublicId,
            'feast_date' => $feastDate,
            'worship_public_id' => $worshipPublicId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
