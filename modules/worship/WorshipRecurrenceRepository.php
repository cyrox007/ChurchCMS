<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Worship;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class WorshipRecurrenceRepository
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

    public function findByPublicId(
        string $publicId,
        string $siteKey = 'default',
    ): ?WorshipRecurrenceRule {
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_recurrence_rules
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

    /** @return list<WorshipRecurrenceRule> */
    public function activeRules(
        string $siteKey = 'default',
        int $limit = 200,
    ): array {
        $limit = max(1, min(500, $limit));

        $statement = $this->pdo->prepare(
            'SELECT *
             FROM worship_recurrence_rules
             WHERE site_key = :site_key
               AND status = :status
             ORDER BY starts_on ASC, id ASC
             LIMIT ' . $limit
        );
        $statement->execute([
            'site_key' => $siteKey,
            'status' => 'active',
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    public function occurrenceExists(
        int $ruleId,
        string $occurrenceDate,
    ): bool {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM worship_recurrence_occurrences
             WHERE rule_id = :rule_id
               AND occurrence_date = :occurrence_date
             LIMIT 1'
        );
        $statement->execute([
            'rule_id' => $ruleId,
            'occurrence_date' => $occurrenceDate,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function recordOccurrence(
        int $ruleId,
        string $occurrenceDate,
        string $worshipPublicId,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO worship_recurrence_occurrences (
                rule_id,
                occurrence_date,
                worship_public_id,
                created_at
             ) VALUES (
                :rule_id,
                :occurrence_date,
                :worship_public_id,
                :created_at
             )'
        );
        $statement->execute([
            'rule_id' => $ruleId,
            'occurrence_date' => $occurrenceDate,
            'worship_public_id' => $worshipPublicId,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private static function hydrate(
        array $row,
    ): WorshipRecurrenceRule {
        return new WorshipRecurrenceRule(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            ownerOrganizationPublicId:
                (string) $row['owner_organization_public_id'],
            status: (string) $row['status'],
            title: (string) $row['title'],
            serviceType: (string) $row['service_type'],
            frequency: (string) $row['frequency'],
            weekday: isset($row['weekday'])
                ? (int) $row['weekday']
                : null,
            localTime: (string) $row['local_time'],
            timezone: (string) $row['timezone'],
            durationMinutes: isset($row['duration_minutes'])
                ? (int) $row['duration_minutes']
                : null,
            startsOn: (string) $row['starts_on'],
            endsOn: self::nullable($row['ends_on'] ?? null),
            locationName: self::nullable(
                $row['location_name'] ?? null,
            ),
            descriptionHtml: (string) $row['description_html'],
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
