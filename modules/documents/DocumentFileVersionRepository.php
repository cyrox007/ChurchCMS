<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\DatabaseManager;
use PDO;

final class DocumentFileVersionRepository
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

    /**
     * @return list<DocumentFileVersion>
     */
    public function forDocument(
        int $documentId,
        int $limit = 100,
    ): array {
        if ($documentId <= 0) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $statement = $this->pdo->prepare(
            'SELECT *
             FROM document_file_versions
             WHERE document_id = :document_id
             ORDER BY version_number DESC, id DESC
             LIMIT ' . $limit
        );
        $statement->execute([
            'document_id' => $documentId,
        ]);

        return array_map(
            self::hydrate(...),
            $statement->fetchAll(),
        );
    }

    public function nextVersionNumber(
        int $documentId,
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(version_number), 0)
             FROM document_file_versions
             WHERE document_id = :document_id'
        );
        $statement->execute([
            'document_id' => $documentId,
        ]);

        return ((int) $statement->fetchColumn()) + 1;
    }

    public function create(
        string $publicId,
        DocumentRecord $document,
        int $versionNumber,
        string $mediaPublicId,
        ?string $note,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO document_file_versions (
                public_id,
                site_key,
                document_id,
                version_number,
                media_public_id,
                note,
                created_at
             ) VALUES (
                :public_id,
                :site_key,
                :document_id,
                :version_number,
                :media_public_id,
                :note,
                :created_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $document->siteKey,
            'document_id' => $document->id,
            'version_number' => $versionNumber,
            'media_public_id' => $mediaPublicId,
            'note' => $note,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private static function hydrate(
        array $row,
    ): DocumentFileVersion {
        return new DocumentFileVersion(
            id: (int) $row['id'],
            publicId: (string) $row['public_id'],
            siteKey: (string) $row['site_key'],
            documentId: (int) $row['document_id'],
            versionNumber: (int) $row['version_number'],
            mediaPublicId: (string) $row['media_public_id'],
            note: self::nullable($row['note'] ?? null),
            createdAt: (string) $row['created_at'],
        );
    }

    private static function nullable(
        mixed $value,
    ): ?string {
        return is_string($value) && $value !== ''
            ? $value
            : null;
    }
}
