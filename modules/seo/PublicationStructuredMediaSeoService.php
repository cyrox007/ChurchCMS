<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Seo;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\SeoRenderer;
use ChurchCMS\Modules\Publications\Publication;
use ChurchCMS\Modules\Publications\PublicationType;
use InvalidArgumentException;

final class PublicationStructuredMediaSeoService
{
    private const CONSUMER_TYPE = 'publication-seo';
    private const SLOT_IMAGE = 'image';
    private const SLOT_VIDEO = 'video';
    private const SLOT_VIDEO_THUMBNAIL = 'video-thumbnail';

    /**
     * @return array{
     *     seo_image_media_id:string,
     *     seo_video_media_id:string,
     *     seo_video_thumbnail_media_id:string
     * }
     */
    public function formForPublication(
        Publication $publication,
    ): array {
        $selected = [
            self::SLOT_IMAGE => '',
            self::SLOT_VIDEO => '',
            self::SLOT_VIDEO_THUMBNAIL => '',
        ];

        foreach (
            $this->mediaCapability()->referencesForConsumer(
                self::CONSUMER_TYPE,
                $publication->publicId,
                $publication->siteKey,
            ) as $reference
        ) {
            if (array_key_exists(
                $reference->usageKey,
                $selected,
            )) {
                $selected[$reference->usageKey] =
                    $reference->mediaPublicId;
            }
        }

        return [
            'seo_image_media_id' =>
                $selected[self::SLOT_IMAGE],
            'seo_video_media_id' =>
                $selected[self::SLOT_VIDEO],
            'seo_video_thumbnail_media_id' =>
                $selected[self::SLOT_VIDEO_THUMBNAIL],
        ];
    }

    /**
     * @param list<string>|null $ownerPublicIds
     * @return array{
     *     images:list<array<string,mixed>>,
     *     videos:list<array<string,mixed>>
     * }
     */
    public function options(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
    ): array {
        $images = [];
        $videos = [];

        foreach (
            $this->mediaCapability()->structuredSeoAssets(
                $ownerPublicIds,
                $siteKey,
            ) as $asset
        ) {
            if (($asset['media_type'] ?? null) === 'image') {
                $images[] = $asset;
                continue;
            }

            if (($asset['media_type'] ?? null) === 'video') {
                $videos[] = $asset;
            }
        }

        return [
            'images' => $images,
            'videos' => $videos,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param list<string>|null $ownerPublicIds
     */
    public function save(
        Publication $publication,
        array $input,
        ?array $ownerPublicIds,
    ): void {
        $options = $this->options(
            $ownerPublicIds,
            $publication->siteKey,
        );
        $allowedImages = self::byPublicId(
            $options['images'],
        );
        $allowedVideos = self::byPublicId(
            $options['videos'],
        );

        $imageId = self::id(
            $input['seo_image_media_id'] ?? ''
        );
        $videoId = self::id(
            $input['seo_video_media_id'] ?? ''
        );
        $videoThumbnailId = self::id(
            $input['seo_video_thumbnail_media_id'] ?? ''
        );

        if (
            $imageId !== ''
            && !isset($allowedImages[$imageId])
        ) {
            throw new InvalidArgumentException(
                'SEO-изображение недоступно.'
            );
        }

        if (
            $videoId !== ''
            && !isset($allowedVideos[$videoId])
        ) {
            throw new InvalidArgumentException(
                'SEO-видео недоступно.'
            );
        }

        if (
            $videoThumbnailId !== ''
            && !isset($allowedImages[$videoThumbnailId])
        ) {
            throw new InvalidArgumentException(
                'Превью SEO-видео недоступно.'
            );
        }

        if (
            $videoId !== ''
            && $videoThumbnailId === ''
        ) {
            throw new InvalidArgumentException(
                'Для SEO-видео обязательно выберите публичное превью.'
            );
        }

        if (
            $videoId === ''
            && $videoThumbnailId !== ''
        ) {
            throw new InvalidArgumentException(
                'Превью видео нельзя сохранить без самого SEO-видео.'
            );
        }

        $references = [];

        if ($imageId !== '') {
            $references[self::SLOT_IMAGE] = $imageId;
        }

        if ($videoId !== '') {
            $references[self::SLOT_VIDEO] = $videoId;
            $references[self::SLOT_VIDEO_THUMBNAIL] =
                $videoThumbnailId;
        }

        $this->mediaCapability()->replaceConsumerReferences(
            self::CONSUMER_TYPE,
            $publication->publicId,
            $references,
            $publication->siteKey,
        );
    }

    /**
     * @param array<string,mixed> $baseMeta
     * @return array<string,mixed>
     */
    public function enrichMeta(
        Publication $publication,
        array $baseMeta,
        bool $preferStructuredImage = true,
    ): array {
        $form = $this->formForPublication($publication);
        $image = $this->descriptor(
            $form['seo_image_media_id'],
            $publication->siteKey,
            'image',
        );
        $video = $this->descriptor(
            $form['seo_video_media_id'],
            $publication->siteKey,
            'video',
        );
        $videoThumbnail = $this->descriptor(
            $form['seo_video_thumbnail_media_id'],
            $publication->siteKey,
            'image',
        );

        $canonical = SeoRenderer::absoluteUrl(
            (string) ($baseMeta['canonical'] ?? '')
        );
        $description = trim(
            (string) ($baseMeta['description']
                ?? $publication->excerpt)
        );

        $article = [
            '@context' => 'https://schema.org',
            '@type' => $publication->type === PublicationType::News
                ? 'NewsArticle'
                : 'Article',
            'headline' => $publication->title,
            'dateModified' =>
                $publication->updatedAt->format(DATE_ATOM),
        ];

        if ($canonical !== '') {
            $article['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $canonical,
            ];
        }

        if ($publication->publishedAt !== null) {
            $article['datePublished'] =
                $publication->publishedAt->format(DATE_ATOM);
        }

        if ($description !== '') {
            $article['description'] = $description;
        }

        if (
            $publication->authorName !== null
            && trim($publication->authorName) !== ''
        ) {
            $article['author'] = [
                '@type' => 'Person',
                'name' => trim($publication->authorName),
            ];
        } else {
            $siteName = trim(
                (string) Config::get(
                    'site.name',
                    Config::get('app.name', 'ChurchCMS'),
                )
            );

            if ($siteName !== '') {
                $article['author'] = [
                    '@type' => 'Organization',
                    'name' => $siteName,
                ];
            }
        }

        if ($image !== null) {
            $article['image'] = self::imageObject($image);

            if ($preferStructuredImage) {
                $baseMeta['image'] =
                    SeoRenderer::absoluteUrl(
                        (string) $image['url']
                    );
            }
        }

        if (
            $video !== null
            && $videoThumbnail !== null
        ) {
            $videoObject = self::videoObject(
                $publication,
                $video,
                $videoThumbnail,
                $description,
            );

            if ($videoObject !== null) {
                $article['video'] = $videoObject;
            }
        }

        $baseMeta['structured_data'] = [$article];

        return $baseMeta;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function descriptor(
        string $publicId,
        string $siteKey,
        string $expectedType,
    ): ?array {
        if ($publicId === '') {
            return null;
        }

        $descriptor = $this->mediaCapability()
            ->structuredSeoDescriptor(
                $publicId,
                $siteKey,
            );

        return is_array($descriptor)
            && ($descriptor['media_type'] ?? null)
                === $expectedType
            ? $descriptor
            : null;
    }

    /**
     * @param array<string,mixed> $image
     * @return array<string,mixed>
     */
    private static function imageObject(array $image): array
    {
        $result = [
            '@type' => 'ImageObject',
            'url' => SeoRenderer::absoluteUrl(
                (string) ($image['url'] ?? '')
            ),
            'contentUrl' => SeoRenderer::absoluteUrl(
                (string) ($image['url'] ?? '')
            ),
        ];

        if (is_int($image['width'] ?? null)) {
            $result['width'] = $image['width'];
        }

        if (is_int($image['height'] ?? null)) {
            $result['height'] = $image['height'];
        }

        $caption = trim((string) (
            $image['alt_text']
            ?? $image['title']
            ?? ''
        ));

        if ($caption !== '') {
            $result['caption'] = $caption;
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $video
     * @param array<string,mixed> $thumbnail
     * @return array<string,mixed>|null
     */
    private static function videoObject(
        Publication $publication,
        array $video,
        array $thumbnail,
        string $description,
    ): ?array {
        $contentUrl = SeoRenderer::absoluteUrl(
            (string) ($video['url'] ?? '')
        );
        $thumbnailUrl = SeoRenderer::absoluteUrl(
            (string) ($thumbnail['url'] ?? '')
        );
        $createdAt = trim(
            (string) ($video['created_at'] ?? '')
        );

        if (
            $contentUrl === ''
            || $thumbnailUrl === ''
            || (
                $publication->publishedAt === null
                && $createdAt === ''
            )
        ) {
            return null;
        }

        try {
            $uploadDate = $publication->publishedAt !== null
                ? $publication->publishedAt
                    ->setTimezone(
                        new \DateTimeZone('UTC')
                    )
                    ->format(DATE_ATOM)
                : (new \DateTimeImmutable(
                    $createdAt,
                    new \DateTimeZone('UTC'),
                ))
                    ->setTimezone(
                        new \DateTimeZone('UTC')
                    )
                    ->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }

        $name = trim(
            (string) ($video['title']
                ?? $publication->title)
        );

        if ($name === '') {
            $name = $publication->title;
        }

        $result = [
            '@type' => 'VideoObject',
            'name' => $name,
            'thumbnailUrl' => [$thumbnailUrl],
            'uploadDate' => $uploadDate,
            'contentUrl' => $contentUrl,
        ];

        if ($description !== '') {
            $result['description'] = $description;
        }

        return $result;
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return array<string,array<string,mixed>>
     */
    private static function byPublicId(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $publicId = self::id(
                $item['public_id'] ?? ''
            );

            if ($publicId !== '') {
                $result[$publicId] = $item;
            }
        }

        return $result;
    }

    private static function id(mixed $value): string
    {
        return is_string($value)
            ? trim($value)
            : '';
    }

    private function mediaCapability(): object
    {
        $capability = ModuleRuntimeLoader::capability(
            'media',
            'media.usage-references',
        );

        if (
            $capability === null
            || !method_exists(
                $capability,
                'referencesForConsumer',
            )
            || !method_exists(
                $capability,
                'replaceConsumerReferences',
            )
            || !method_exists(
                $capability,
                'structuredSeoAssets',
            )
            || !method_exists(
                $capability,
                'structuredSeoDescriptor',
            )
        ) {
            throw new InvalidArgumentException(
                'Media capability для structured SEO недоступен.'
            );
        }

        return $capability;
    }
}
