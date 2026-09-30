<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\ModuleRuntimeLoader;
use InvalidArgumentException;
use RuntimeException;

final class DocumentMediaService
{
    private const CONSUMER_TYPE = 'document';
    private const FILE_SLOT = 'file';

    public function __construct(
        private readonly DocumentRepository $documents,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            DocumentRepository::fromDatabase(),
        );
    }

    public function attachFile(
        string $documentPublicId,
        string $mediaPublicId,
        string $siteKey = 'default',
    ): void {
        $document = $this->documents->findByPublicId(
            trim($documentPublicId),
            $siteKey,
        );

        if (
            $document === null
            || $document->status === 'archived'
        ) {
            throw new InvalidArgumentException(
                'Документ недоступен для прикрепления файла.'
            );
        }

        $capability = self::mediaCapability();
        $asset = $capability->assetDescriptor(
            trim($mediaPublicId),
            $siteKey,
        );

        if (
            !is_array($asset)
            || ($asset['media_type'] ?? null) !== 'document'
        ) {
            throw new InvalidArgumentException(
                'К документу можно прикрепить только Media-файл типа document.'
            );
        }

        $capability->replaceConsumerReferences(
            self::CONSUMER_TYPE,
            $document->publicId,
            [
                self::FILE_SLOT =>
                    (string) $asset['public_id'],
            ],
            $siteKey,
        );
    }

    public function detachFile(
        string $documentPublicId,
        string $siteKey = 'default',
    ): void {
        $document = $this->documents->findByPublicId(
            trim($documentPublicId),
            $siteKey,
        );

        if ($document === null) {
            throw new InvalidArgumentException(
                'Документ не найден.'
            );
        }

        self::mediaCapability()->replaceConsumerReferences(
            self::CONSUMER_TYPE,
            $document->publicId,
            [],
            $siteKey,
        );
    }

    public function fileMediaPublicId(
        string $documentPublicId,
        string $siteKey = 'default',
    ): ?string {
        $document = $this->documents->findByPublicId(
            trim($documentPublicId),
            $siteKey,
        );

        if ($document === null) {
            return null;
        }

        foreach (
            self::mediaCapability()->referencesForConsumer(
                self::CONSUMER_TYPE,
                $document->publicId,
                $siteKey,
            ) as $reference
        ) {
            if ($reference->usageKey === self::FILE_SLOT) {
                return $reference->mediaPublicId;
            }
        }

        return null;
    }

    private static function mediaCapability(): object
    {
        $capability = ModuleRuntimeLoader::capability(
            'media',
            'media.usage-references',
        );

        if (
            $capability === null
            || !method_exists(
                $capability,
                'replaceConsumerReferences',
            )
            || !method_exists(
                $capability,
                'referencesForConsumer',
            )
            || !method_exists(
                $capability,
                'assetDescriptor',
            )
        ) {
            throw new RuntimeException(
                'Media usage capability недоступен.'
            );
        }

        return $capability;
    }
}
