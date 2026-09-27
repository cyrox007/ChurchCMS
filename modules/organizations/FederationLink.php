<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

final readonly class FederationLink
{
    /**
     * @param list<string> $inboundScopes
     * @param list<string> $outboundScopes
     */
    public function __construct(
        public int $id,
        public string $publicId,
        public string $siteKey,
        public int $localOrganizationId,
        public string $relation,
        public string $status,
        public string $remoteInstanceId,
        public string $remoteOrganizationPublicId,
        public string $remoteBaseUrl,
        public ?string $remoteProfile,
        public ?string $remoteName,
        public array $inboundScopes,
        public array $outboundScopes,
        public ?string $syncCursor,
        public ?string $lastSeenAt,
        public ?string $lastError,
    ) {
    }
}
