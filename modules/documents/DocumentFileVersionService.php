<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class DocumentFileVersionService
{
    private const CONSUMER_TYPE = 'document-version';
    private const FILE_SLOT = 'file';

    private DocumentRepository $documents;
    private DocumentFileVersionRepository $versions;

    public function __construct(private readonly PDO $pdo)
    {
        $this->documents = new DocumentRepository($pdo);
        $this->versions = new DocumentFileVersionRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function record(
        string $documentPublicId,
        string $mediaPublicId,
        ?string $note = null,
        string $siteKey = 'default',
    ): DocumentFileVersion {
        $document = $this->documents->findByPublicId(
            trim($documentPublicId),
            $siteKey,
        );

        if ($document === null || $document->status === 'archived') {
            throw new InvalidArgumentException(
                'Документ недоступен для добавления версии файла.'
            );
        }

        $mediaPublicId = trim($mediaPublicId);
        $this->assertDocumentMedia(
            $mediaPublicId,
            $siteKey,
        );
        $note = self::note($note);
        $publicId = Uuid::v4();

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $versionNumber = $this->versions->nextVersionNumber(
                $document->id,
            );
            $this->versions->create(
                $publicId,
                $document,
                $versionNumber,
                $mediaPublicId,
                $note,
            );

            self::mediaCapability()->replaceConsumerReferences(
                self::CONSUMER_TYPE,
                $publicId,
                [self::FILE_SLOT => $mediaPublicId],
                $siteKey,
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }

        $versions = $this->versions->forDocument(
            $document->id,
            1,
        );

        if ($versions === []) {
            throw new RuntimeException(
                'Созданная версия файла документа не найдена.'
            );
        }

        return $versions[0];
    }

    /**
     * @return list<DocumentFileVersion>
     */
    public function forDocument(
        string $documentPublicId,
        string $siteKey = 'default',
        int $limit = 100,
    ): array {
        $document = $this->documents->findByPublicId(
            trim($documentPublicId),
            $siteKey,
        );

        if ($document === null) {
            return [];
        }

        return $this->versions->forDocument(
            $document->id,
            $limit,
        );
    }

    private function assertDocumentMedia(
        string $mediaPublicId,
        string $siteKey,
    ): void {
        $asset = self::mediaCapability()->assetDescriptor(
            $mediaPublicId,
            $siteKey,
        );

        if (
            !is_array($asset)
            || ($asset['media_type'] ?? null) !== 'document'
        ) {
            throw new InvalidArgumentException(
                'Версией документа может быть только Media-файл типа document.'
            );
        }
    }

    private static function note(
        ?string $value,
    ): ?string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length > 500) {
            throw new InvalidArgumentException(
                'Примечание к версии файла слишком длинное.'
            );
        }

        return $value;
    }

    private static function mediaCapability(): object
    {
        $capability = ModuleRuntimeLoader::capability(
            'media',
            'media.usage-references',
        );

        if (
            $capability === null
            || !method_exists($capability, 'assetDescriptor')
            || !method_exists(
                $capability,
                'replaceConsumerReferences',
            )
        ) {
            throw new RuntimeException(
                'Media usage capability недоступен.'
            );
        }

        return $capability;
    }
}
