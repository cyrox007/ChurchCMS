<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Migration;

interface VpdsImportPort
{
    public function importedChecksum(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
    ): ?string;

    /** @param array<string,mixed> $user */
    public function mapUser(
        int $organizationId,
        array $user,
        bool $dryRun,
    ): ?string;

    /** @param array<string,mixed> $content */
    public function upsertContent(
        int $organizationId,
        array $content,
        bool $dryRun,
    ): string;

    /** @param array<string,mixed> $media */
    public function importMedia(
        int $organizationId,
        array $media,
        string $sourcePath,
        bool $dryRun,
    ): string;

    public function saveRedirect(
        int $organizationId,
        string $from,
        string $to,
        bool $dryRun,
    ): void;

    public function rememberImport(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
        string $checksum,
        string $targetId,
        bool $dryRun,
    ): void;
}