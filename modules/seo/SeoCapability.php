<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Seo;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Publications\Publication;
use Throwable;

final class SeoCapability
{
    /** @return array<string,mixed> */
    public function metaForPublication(
        Publication $publication,
    ): array {
        $repository = PublicationSeoRepository::fromDatabase();
        $meta = $repository->metaFor($publication);
        $form = $repository->formFor($publication);

        try {
            return (new PublicationStructuredMediaSeoService())
                ->enrichMeta(
                    $publication,
                    $meta,
                    trim((string) (
                        $form['social_image_url'] ?? ''
                    )) === '',
                );
        } catch (Throwable $error) {
            error_log(
                'ChurchCMS structured Media SEO: '
                . $error->getMessage()
            );

            return $meta;
        }
    }

    /** @return array<string,mixed> */
    public function formForPublication(
        Publication $publication,
    ): array {
        $form = PublicationSeoRepository::fromDatabase()
            ->formFor($publication);

        try {
            return array_replace(
                $form,
                (new PublicationStructuredMediaSeoService())
                    ->formForPublication($publication),
            );
        } catch (Throwable) {
            return $form + [
                'seo_image_media_id' => '',
                'seo_video_media_id' => '',
                'seo_video_thumbnail_media_id' => '',
            ];
        }
    }

    /**
     * @param list<string>|null $ownerPublicIds
     * @return array{
     *     images:list<array<string,mixed>>,
     *     videos:list<array<string,mixed>>
     * }
     */
    public function structuredMediaOptions(
        ?array $ownerPublicIds,
        string $siteKey = 'default',
    ): array {
        try {
            return (new PublicationStructuredMediaSeoService())
                ->options(
                    $ownerPublicIds,
                    $siteKey,
                );
        } catch (Throwable) {
            return [
                'images' => [],
                'videos' => [],
            ];
        }
    }

    /**
     * @param array<string,mixed> $input
     * @param list<string>|null $ownerPublicIds
     * @return array<string,string>
     */
    public function validateStructuredMediaSelection(
        array $input,
        ?array $ownerPublicIds,
        string $siteKey = 'default',
    ): array {
        return (new PublicationStructuredMediaSeoService())
            ->validateSelection(
                $input,
                $ownerPublicIds,
                $siteKey,
            );
    }

    /**
     * @param array<string,mixed> $input
     * @param list<string>|null $ownerPublicIds
     */
    public function savePublication(
        Publication $publication,
        array $input,
        ?array $ownerPublicIds = null,
    ): void {
        $pdo = DatabaseManager::getInstance()->connection();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            (new PublicationSeoRepository($pdo))
                ->save(
                    $publication,
                    $input,
                );

            (new PublicationStructuredMediaSeoService())
                ->save(
                    $publication,
                    $input,
                    $ownerPublicIds,
                );

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $pdo->inTransaction()
            ) {
                $pdo->rollBack();
            }

            throw $error;
        }
    }
}
