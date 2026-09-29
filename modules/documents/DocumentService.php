<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use ChurchCMS\Modules\Organizations\OrganizationUnit;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class DocumentService
{
    private DocumentRepository $documents;
    private OrganizationRepository $organizations;

    public function __construct(private readonly PDO $pdo)
    {
        $this->documents = new DocumentRepository($pdo);
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
        string $documentType = 'document',
        ?string $documentNumber = null,
        ?string $issuedOn = null,
        string $summary = '',
    ): string {
        $siteKey = self::siteKey($siteKey);
        $owner = $this->resolveOrganization(
            $ownerOrganizationPublicId,
            $siteKey,
        );
        $title = self::requiredText(
            $title,
            255,
            'Название документа обязательно.',
        );
        $documentType = self::machineKey(
            $documentType,
            'Некорректный тип документа.',
        );
        $documentNumber = self::optionalText(
            $documentNumber,
            120,
            'Номер документа слишком длинный.',
        );
        $issuedOn = self::date($issuedOn);
        $summary = self::text(
            $summary,
            4000,
            'Краткое описание документа слишком длинное.',
        );

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO documents (
                public_id,
                site_key,
                owner_organization_public_id,
                status,
                title,
                document_type,
                document_number,
                issued_on,
                summary,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :owner_organization_public_id,
                :status,
                :title,
                :document_type,
                :document_number,
                :issued_on,
                :summary,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'owner_organization_public_id' => $owner->publicId,
            'status' => 'draft',
            'title' => $title,
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'issued_on' => $issuedOn,
            'summary' => $summary,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $publicId;
    }

    public function assignOrganizationOwner(
        string $documentPublicId,
        string $organizationPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $document = $this->documents->findByPublicId(
            $documentPublicId,
            $siteKey,
        );

        if ($document === null) {
            throw new InvalidArgumentException(
                'Документ не найден.'
            );
        }

        $organization = $this->resolveOrganization(
            $organizationPublicId,
            $siteKey,
        );

        $statement = $this->pdo->prepare(
            'UPDATE documents
             SET owner_organization_public_id = :organization_id,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'organization_id' => $organization->publicId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $document->publicId,
            'site_key' => $siteKey,
        ]);
    }

    public function archive(
        string $documentPublicId,
        string $siteKey = 'default',
    ): void {
        $siteKey = self::siteKey($siteKey);
        $document = $this->documents->findByPublicId(
            $documentPublicId,
            $siteKey,
        );

        if ($document === null) {
            throw new InvalidArgumentException(
                'Документ не найден.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE documents
             SET status = :status,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => 'archived',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $document->publicId,
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
                'Активная организация для документа не найдена.'
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
                'Некорректный site key документов.'
            );
        }

        return $value;
    }

    private static function machineKey(
        string $value,
        string $message,
    ): string {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z][a-z0-9_.:-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function requiredText(
        string $value,
        int $limit,
        string $message,
    ): string {
        $value = trim($value);

        if ($value === '' || self::length($value) > $limit) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private static function optionalText(
        ?string $value,
        int $limit,
        string $message,
    ): ?string {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (self::length($value) > $limit) {
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

    private static function date(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    ($errors['warning_count'] ?? 0) > 0
                    || ($errors['error_count'] ?? 0) > 0
                )
            )
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException(
                'Некорректная дата документа.'
            );
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
