<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\AuditLog;
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

            foreach ($publicIds as $publicId) {
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
