<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use InvalidArgumentException;
use PDO;
use Throwable;

final class MediaUsageService
{
    private MediaRepository $media;
    private MediaUsageRepository $usage;

    public function __construct(private readonly PDO $pdo)
    {
        $this->media = new MediaRepository($pdo);
        $this->usage = new MediaUsageRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * Атомарно заменяет все Media-ссылки одного потребителя.
     *
     * @param array<string,string> $references usage_key => media_public_id
     */
    public function replaceConsumerReferences(
        string $consumerType,
        string $consumerPublicId,
        array $references,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $consumerType = self::machineKey(
            $consumerType,
            'Некорректный тип потребителя Media.',
        );
        $consumerPublicId = self::consumerId(
            $consumerPublicId,
        );

        $normalized = [];
        foreach ($references as $usageKey => $mediaPublicId) {
            if (!is_string($usageKey) || !is_string($mediaPublicId)) {
                throw new InvalidArgumentException(
                    'Ссылки Media заполнены некорректно.'
                );
            }

            $usageKey = self::machineKey(
                $usageKey,
                'Некорректный slot использования Media.',
            );
            $mediaPublicId = trim($mediaPublicId);

            if (
                preg_match(
                    '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                    $mediaPublicId,
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Некорректный public ID Media asset.'
                );
            }

            $normalized[$usageKey] = strtolower($mediaPublicId);
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $assetIds = array_values(
                array_unique($normalized),
            );
            sort($assetIds, SORT_STRING);

            foreach ($assetIds as $mediaPublicId) {
                $asset = $this->media->findByPublicIdForUpdate(
                    $mediaPublicId,
                    $siteKey,
                );

                if ($asset === null) {
                    throw new InvalidArgumentException(
                        'Media asset для ссылки не найден.'
                    );
                }

                if ($asset->status === 'archived') {
                    throw new InvalidArgumentException(
                        'Архивный Media asset нельзя использовать.'
                    );
                }
            }

            $this->usage->replaceConsumer(
                $consumerType,
                $consumerPublicId,
                $normalized,
                $siteKey,
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * @return list<MediaUsageReference>
     */
    public function forAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): array {
        return $this->usage->forAsset(
            trim($mediaPublicId),
            self::siteKey($siteKey),
        );
    }

    /**
     * @return list<MediaUsageReference>
     */
    public function forConsumer(
        string $consumerType,
        string $consumerPublicId,
        string $siteKey = 'default',
    ): array {
        return $this->usage->forConsumer(
            self::machineKey(
                $consumerType,
                'Некорректный тип потребителя Media.',
            ),
            self::consumerId($consumerPublicId),
            self::siteKey($siteKey),
        );
    }

    /**
     * @return array{
     *     public_id:string,
     *     media_type:string,
     *     mime_type:string,
     *     status:string
     * }|null
     */
    public function assetDescriptor(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): ?array {
        $asset = $this->media->findByPublicId(
            trim($mediaPublicId),
            self::siteKey($siteKey),
        );

        if ($asset === null || $asset->status === 'archived') {
            return null;
        }

        return [
            'public_id' => $asset->publicId,
            'media_type' => $asset->mediaType,
            'mime_type' => $asset->mimeType,
            'status' => $asset->status,
        ];
    }

    public function countForAsset(
        string $mediaPublicId,
        string $siteKey = 'default',
    ): int {
        return $this->usage->countForAsset(
            trim($mediaPublicId),
            self::siteKey($siteKey),
        );
    }

    private static function siteKey(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_.-]{0,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный site key Media usage.'
            );
        }

        return $value;
    }

    private static function machineKey(
        string $value,
        string $message,
    ): string {
        $value = strtolower(trim($value));

        if (
            preg_match(
                '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function consumerId(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID потребителя Media.'
            );
        }

        return $value;
    }
}
