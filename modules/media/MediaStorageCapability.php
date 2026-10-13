<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

final class MediaStorageCapability
{
    /** @return array<string,mixed> */
    public function report(bool $recordSnapshot = false): array
    {
        return MediaStorageReportService::fromConfig()->report($recordSnapshot);
    }

    public function archive(): MediaArchiveService
    {
        return MediaArchiveService::fromConfig();
    }

    public function maintenance(): MediaStorageMaintenanceService
    {
        return MediaStorageMaintenanceService::fromConfig();
    }
}
