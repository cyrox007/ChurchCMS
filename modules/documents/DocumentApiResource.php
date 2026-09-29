<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\ApiResource;
use DateTimeImmutable;
use DateTimeZone;

final class DocumentApiResource implements ApiResource
{
    public function __construct(
        private readonly DocumentRecord $document,
    ) {
    }

    /**
     * Безопасная projection для federation/partner API.
     *
     * Файл и путь к нему намеренно не передаются: Documents пока хранит
     * только редакционную карточку, а blob появится через проверенный Media.
     *
     * @return array<string,mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->document->publicId,
            'type' => 'document',
            'title' => $this->document->title,
            'document_type' => $this->document->documentType,
            'document_number' => $this->document->documentNumber,
            'issued_on' => $this->document->issuedOn,
            'summary' => $this->document->summary,
            'organization_owner_id' =>
                $this->document->ownerOrganizationPublicId,
            'updated_at' => self::timestamp(
                $this->document->updatedAt,
            ),
            'url' => null,
        ];
    }

    private static function timestamp(string $value): string
    {
        return (new DateTimeImmutable(
            $value,
            new DateTimeZone('UTC'),
        ))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);
    }
}
