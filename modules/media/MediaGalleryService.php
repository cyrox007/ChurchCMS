<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use InvalidArgumentException;
use PDO;

final class MediaGalleryService
{
    private MediaGalleryRepository $galleries;
    private MediaRepository $media;
    private MediaUsageService $usage;
    private OrganizationRepository $organizations;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->galleries = new MediaGalleryRepository($pdo);
        $this->media = new MediaRepository($pdo);
        $this->usage = new MediaUsageService($pdo);
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function createDraft(
        string $title,
        ?string $ownerOrganizationPublicId = null,
        string $siteKey = 'default',
        string $description = '',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->resolveOrganization(
            $ownerOrganizationPublicId,
            $siteKey,
        );
        $title = self::requiredText(
            $title,
            255,
            'Название галереи обязательно.',
        );
        $description = self::text(
            $description,
            4000,
            'Описание галереи слишком длинное.',
        );

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO media_galleries (
                public_id,
                site_key,
                owner_organization_public_id,
                status,
                visibility,
                title,
                description,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :owner_organization_public_id,
                :status,
                :visibility,
                :title,
                :description,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'owner_organization_public_id' =>
                $owner->publicId,
            'status' => 'draft',
            'visibility' => 'private',
            'title' => $title,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    /**
     * @param list<string> $mediaPublicIds
     */
    public function replaceItems(
        string $galleryPublicId,
        array $mediaPublicIds,
        string $siteKey = 'default',
    ): void {
        $gallery = $this->requiredGallery(
            $galleryPublicId,
            $siteKey,
        );

        if ($gallery->status === 'archived') {
            throw new InvalidArgumentException(
                'Архивную галерею нельзя изменять.'
            );
        }

        if (count($mediaPublicIds) > 200) {
            throw new InvalidArgumentException(
                'Галерея не может содержать более 200 изображений.'
            );
        }

        $references = [];
        $seen = [];

        foreach (array_values($mediaPublicIds) as $index => $publicId) {
            if (!is_string($publicId)) {
                throw new InvalidArgumentException(
                    'Список изображений галереи заполнен некорректно.'
                );
            }

            $publicId = trim($publicId);
            if ($publicId === '') {
                continue;
            }

            if (isset($seen[$publicId])) {
                throw new InvalidArgumentException(
                    'Одно изображение нельзя добавить в галерею дважды.'
                );
            }

            $asset = $this->media->findByPublicId(
                $publicId,
                $siteKey,
            );

            if (
                $asset === null
                || $asset->status === 'archived'
                || $asset->mediaType !== 'image'
            ) {
                throw new InvalidArgumentException(
                    'Галерея может содержать только активные изображения Media.'
                );
            }

            $seen[$publicId] = true;
            $references[
                sprintf('item:%06d', $index + 1)
            ] = $publicId;
        }

        $this->usage->replaceConsumerReferences(
            'gallery',
            $gallery->publicId,
            $references,
            $siteKey,
        );

        $this->touch(
            $gallery->publicId,
            $siteKey,
        );
    }

    /**
     * @return list<MediaAsset>
     */
    public function items(
        string $galleryPublicId,
        string $siteKey = 'default',
    ): array {
        $gallery = $this->galleries->findByPublicId(
            trim($galleryPublicId),
            $siteKey,
        );

        if ($gallery === null) {
            return [];
        }

        $items = [];

        foreach (
            $this->usage->forConsumer(
                'gallery',
                $gallery->publicId,
                $siteKey,
            ) as $reference
        ) {
            if (!str_starts_with(
                $reference->usageKey,
                'item:',
            )) {
                continue;
            }

            $asset = $this->media->findByPublicId(
                $reference->mediaPublicId,
                $siteKey,
            );

            if (
                $asset !== null
                && $asset->status !== 'archived'
                && $asset->mediaType === 'image'
            ) {
                $items[] = $asset;
            }
        }

        return $items;
    }

    public function publish(
        string $galleryPublicId,
        string $siteKey = 'default',
    ): void {
        $gallery = $this->requiredGallery(
            $galleryPublicId,
            $siteKey,
        );

        if (
            $gallery->status === 'archived'
            || $this->items(
                $gallery->publicId,
                $siteKey,
            ) === []
        ) {
            throw new InvalidArgumentException(
                'Пустую или архивную галерею нельзя опубликовать.'
            );
        }

        $this->updateState(
            $gallery->publicId,
            'published',
            $gallery->visibility,
            $siteKey,
        );
    }

    public function withdraw(
        string $galleryPublicId,
        string $siteKey = 'default',
    ): void {
        $gallery = $this->requiredGallery(
            $galleryPublicId,
            $siteKey,
        );

        if ($gallery->status === 'archived') {
            throw new InvalidArgumentException(
                'Архивную галерею нельзя снять с публикации.'
            );
        }

        $this->updateState(
            $gallery->publicId,
            'draft',
            'private',
            $siteKey,
        );
    }

    public function archive(
        string $galleryPublicId,
        string $siteKey = 'default',
    ): void {
        $gallery = $this->requiredGallery(
            $galleryPublicId,
            $siteKey,
        );

        $this->usage->replaceConsumerReferences(
            'gallery',
            $gallery->publicId,
            [],
            $siteKey,
        );

        $this->updateState(
            $gallery->publicId,
            'archived',
            'private',
            $siteKey,
        );
    }

    public function setVisibility(
        string $galleryPublicId,
        string $visibility,
        string $siteKey = 'default',
    ): void {
        $gallery = $this->requiredGallery(
            $galleryPublicId,
            $siteKey,
        );
        $visibility = trim($visibility);

        if (!in_array(
            $visibility,
            ['private', 'public'],
            true,
        )) {
            throw new InvalidArgumentException(
                'Некорректная видимость галереи.'
            );
        }

        if (
            $visibility === 'public'
            && $gallery->status !== 'published'
        ) {
            throw new InvalidArgumentException(
                'Публичной может быть только опубликованная галерея.'
            );
        }

        $this->updateState(
            $gallery->publicId,
            $gallery->status,
            $visibility,
            $siteKey,
        );
    }

    private function requiredGallery(
        string $publicId,
        string $siteKey,
    ): MediaGallery {
        $gallery = $this->galleries->findByPublicId(
            trim($publicId),
            self::siteKey($siteKey),
        );

        if ($gallery === null) {
            throw new InvalidArgumentException(
                'Галерея не найдена.'
            );
        }

        return $gallery;
    }

    private function touch(
        string $publicId,
        string $siteKey,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE media_galleries
             SET updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);
    }

    private function updateState(
        string $publicId,
        string $status,
        string $visibility,
        string $siteKey,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE media_galleries
             SET status = :status,
                 visibility = :visibility,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => $status,
            'visibility' => $visibility,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);
    }

    private function resolveOrganization(
        ?string $publicId,
        string $siteKey,
    ): OrganizationUnit {
        $publicId = trim((string) ($publicId ?? ''));

        $organization = $publicId !== ''
            ? $this->organizations->findByPublicId(
                $publicId,
                $siteKey,
            )
            : $this->organizations->siteRoot($siteKey);

        if (
            $organization === null
            || $organization->status !== 'active'
        ) {
            throw new InvalidArgumentException(
                'Активная организация для галереи не найдена.'
            );
        }

        return $organization;
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
                'Некорректный site key галереи.'
            );
        }

        return $value;
    }

    private static function requiredText(
        string $value,
        int $limit,
        string $message,
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || self::length($value) > $limit
        ) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function text(
        string $value,
        int $limit,
        string $message,
    ): string {
        $value = trim($value);

        if (self::length($value) > $limit) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
