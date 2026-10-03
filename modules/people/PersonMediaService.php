<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\People;

use ChurchCMS\Core\ModuleRuntimeLoader;
use InvalidArgumentException;
use RuntimeException;

final class PersonMediaService
{
    private const CONSUMER_TYPE = 'person';
    private const PORTRAIT_SLOT = 'portrait';

    public function attachPortrait(
        string $personPublicId,
        string $mediaPublicId,
        string $siteKey = 'default',
    ): void {
        $person = PeopleRepository::fromDatabase()
            ->findPersonByPublicId(
                trim($personPublicId),
                $siteKey,
            );

        if ($person === null || $person->status !== 'active') {
            throw new InvalidArgumentException(
                'Карточка человека недоступна для привязки фотографии.'
            );
        }

        $capability = self::mediaCapability();
        $asset = $capability->assetDescriptor(
            trim($mediaPublicId),
            $siteKey,
        );

        if (
            !is_array($asset)
            || ($asset['media_type'] ?? null) !== 'image'
        ) {
            throw new InvalidArgumentException(
                'Портретом может быть только изображение из Media.'
            );
        }

        $capability->replaceConsumerReferences(
            self::CONSUMER_TYPE,
            $person->publicId,
            [
                self::PORTRAIT_SLOT =>
                    (string) $asset['public_id'],
            ],
            $siteKey,
        );
    }

    public function detachPortrait(
        string $personPublicId,
        string $siteKey = 'default',
    ): void {
        $person = PeopleRepository::fromDatabase()
            ->findPersonByPublicId(
                trim($personPublicId),
                $siteKey,
            );

        if ($person === null) {
            throw new InvalidArgumentException(
                'Карточка человека не найдена.'
            );
        }

        self::mediaCapability()->replaceConsumerReferences(
            self::CONSUMER_TYPE,
            $person->publicId,
            [],
            $siteKey,
        );
    }

    public function portraitMediaPublicId(
        string $personPublicId,
        string $siteKey = 'default',
    ): ?string {
        foreach (
            self::mediaCapability()->referencesForConsumer(
                self::CONSUMER_TYPE,
                trim($personPublicId),
                $siteKey,
            ) as $reference
        ) {
            if ($reference->usageKey === self::PORTRAIT_SLOT) {
                return $reference->mediaPublicId;
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    public function publicPortraitDescriptor(
        string $personPublicId,
        string $siteKey = 'default',
    ): ?array {
        $mediaPublicId = $this->portraitMediaPublicId(
            $personPublicId,
            $siteKey,
        );

        if ($mediaPublicId === null) {
            return null;
        }

        $capability = self::mediaCapability();
        if (!method_exists(
            $capability,
            'publicAssetDescriptor',
        )) {
            return null;
        }

        $descriptor = $capability->publicAssetDescriptor(
            $mediaPublicId,
            $siteKey,
        );

        return is_array($descriptor)
            && ($descriptor['media_type'] ?? null) === 'image'
                ? $descriptor
                : null;
    }

    /**
     * @param list<string>|null $ownerPublicIds
     * @return list<array<string,mixed>>
     */
    public function availablePortraits(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
    ): array {
        $capability = self::mediaCapability();

        if (!method_exists(
            $capability,
            'selectableAssets',
        )) {
            throw new RuntimeException(
                'Media capability не предоставляет список изображений.'
            );
        }

        return $capability->selectableAssets(
            $ownerPublicIds,
            'image',
            $siteKey,
        );
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
