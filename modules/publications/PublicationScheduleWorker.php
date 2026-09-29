<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\AuditLog;
use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Core\PageCache;
use DateTimeImmutable;

final class PublicationScheduleWorker
{
    public function __construct(
        private readonly PublicationRepository $repository,
    ) {
    }

    public static function fromDatabase(): self
    {
        return new self(
            PublicationRepository::fromDatabase(),
        );
    }

    /**
     * @return array{published:int,public_ids:list<string>}
     */
    public function run(
        int $limit = 50,
        string $siteKey = 'default',
        ?DateTimeImmutable $now = null,
    ): array {
        $publicIds = $this->repository->publishDueScheduled(
            $siteKey,
            $limit,
            $now,
        );

        if ($publicIds !== []) {
            PageCache::bumpVersion();

            $channels = ModuleRuntimeLoader::capability(
                'social',
                'social.publication',
            );

            foreach ($publicIds as $publicId) {
                $publication = $this->repository->findByPublicId(
                    $publicId,
                    $siteKey,
                );
                if (
                    $publication !== null
                    && $channels !== null
                    && method_exists(
                        $channels,
                        'queuePublication',
                    )
                ) {
                    $channels->queuePublication(
                        $publication->id,
                    );
                }

                AuditLog::emit(
                    eventType: 'publication.scheduled_published',
                    subjectType: 'publication',
                    subjectId: $publicId,
                    metadata: [
                        'source' => 'schedule_worker',
                    ],
                );
            }
        }

        return [
            'published' => count($publicIds),
            'public_ids' => $publicIds,
        ];
    }
}
