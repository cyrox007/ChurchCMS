<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Migration;

final class VpdsImportReport
{
    /** @var array<string,int> */
    private array $counters = [
        'users_mapped' => 0,
        'users_unmapped' => 0,
        'content_created_or_updated' => 0,
        'content_skipped' => 0,
        'media_imported_or_reused' => 0,
        'media_skipped' => 0,
        'media_missing' => 0,
        'media_checksum_failed' => 0,
        'redirects_saved' => 0,
        'redirects_rejected' => 0,
        'unresolved_links' => 0,
        'errors' => 0,
        'warnings' => 0,
    ];

    /** @var list<array{level:string,code:string,message:string,context:array<string,mixed>}> */
    private array $diagnostics = [];

    public function __construct(
        public readonly bool $dryRun,
        public readonly string $sourceSite,
        public readonly int $organizationId,
    ) {
    }

    public function increment(string $name): void
    {
        if (!array_key_exists($name, $this->counters)) {
            $this->counters[$name] = 0;
        }

        $this->counters[$name]++;
    }

    /** @param array<string,mixed> $context */
    public function warning(
        string $code,
        string $message,
        array $context = [],
    ): void {
        $this->increment('warnings');
        $this->diagnostics[] = [
            'level' => 'warning',
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @param array<string,mixed> $context */
    public function error(
        string $code,
        string $message,
        array $context = [],
    ): void {
        $this->increment('errors');
        $this->diagnostics[] = [
            'level' => 'error',
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'format' => 'churchcms-vpds-import-report',
            'version' => 1,
            'organization_id' => $this->organizationId,
            'source_site' => $this->sourceSite,
            'dry_run' => $this->dryRun,
            'success' => $this->counters['errors'] === 0,
            'counters' => $this->counters,
            'diagnostics' => $this->diagnostics,
        ];
    }

    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}