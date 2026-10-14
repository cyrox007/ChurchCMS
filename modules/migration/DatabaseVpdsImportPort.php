<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Migration;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Media\MediaUploadService;
use ChurchCMS\Modules\Publications\PublicationService;
use ChurchCMS\Modules\Publications\PublicationType;
use ChurchCMS\Modules\Redirects\RedirectRepository;
use ChurchCMS\Modules\Redirects\RedirectService;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class DatabaseVpdsImportPort implements VpdsImportPort
{
    private PublicationService $publications;
    private RedirectRepository $redirectRepository;

    public function __construct(
        private readonly PDO $pdo,
        private readonly MediaUploadService $mediaUpload,
        private readonly RedirectService $redirects,
    ) {
        $this->publications = new PublicationService($pdo);
        $this->redirectRepository = new RedirectRepository($pdo);
    }

    public static function fromRuntime(): self
    {
        $pdo = DatabaseManager::getInstance()->connection();

        return new self(
            $pdo,
            MediaUploadService::fromConfig(),
            new RedirectService($pdo, new RedirectRepository($pdo)),
        );
    }

    public function importedChecksum(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
    ): ?string {
        $statement = $this->pdo->prepare(
            'SELECT checksum
             FROM vpds_import_mappings
             WHERE organization_id = :organization_id
               AND source_site = :source_site
               AND entity_type = :entity_type
               AND source_id = :source_id
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'source_site' => $sourceSite,
            'entity_type' => $entityType,
            'source_id' => $sourceId,
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function mapUser(
        int $organizationId,
        array $user,
        bool $dryRun,
    ): ?string {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $organization = $this->organization($organizationId);
        $statement = $this->pdo->prepare(
            'SELECT id, public_id
             FROM admin_users
             WHERE LOWER(email) = :email
               AND status = :status
             ORDER BY id ASC
             LIMIT 2'
        );
        $statement->execute([
            'email' => $email,
            'status' => 'active',
        ]);
        $matches = $statement->fetchAll();

        if (count($matches) !== 1) {
            return null;
        }

        $match = $matches[0];
        $userId = (int) ($match['id'] ?? 0);
        if (!$this->userCanAccessOrganization($userId, $organization)) {
            return null;
        }

        return (string) $match['public_id'];
    }

    public function upsertContent(
        int $organizationId,
        array $content,
        bool $dryRun,
    ): string {
        $sourceId = trim((string) ($content['id'] ?? ''));
        if ($sourceId === '') {
            throw new InvalidArgumentException('Материал VPDS не содержит id.');
        }

        if ($dryRun) {
            return 'dry-run:content:' . $sourceId;
        }

        $organization = $this->organization($organizationId);
        $title = trim((string) ($content['title'] ?? ''));
        $slug = trim((string) ($content['slug'] ?? ''));
        $excerpt = (string) ($content['excerpt'] ?? '');
        $body = (string) ($content['body_html'] ?? $content['body'] ?? '');
        $authorName = $this->authorName($content);
        $type = $this->publicationType((string) ($content['type'] ?? 'article'));
        $categories = $this->stringList($content['categories'] ?? []);
        $tags = $this->stringList($content['tags'] ?? []);
        $targets = $this->stringList($content['syndication_targets'] ?? []);
        $comments = (bool) ($content['comments_enabled'] ?? false);
        $targetId = $this->previousTarget($organizationId, 'content', $sourceId);

        if ($targetId === null) {
            $targetId = $this->publications->createDraft(
                title: $title,
                slug: $slug,
                type: $type,
                excerpt: $excerpt,
                bodyHtml: $body,
                authorName: $authorName,
                syndicationTargets: $targets,
                commentsEnabled: $comments,
                siteKey: $organization['site_key'],
                categoryNames: $categories,
                tagNames: $tags,
                ownerOrganizationPublicId: $organization['public_id'],
            );
        } else {
            $this->publications->update(
                publicId: $targetId,
                title: $title,
                slug: $slug,
                type: $type,
                excerpt: $excerpt,
                bodyHtml: $body,
                authorName: $authorName,
                syndicationTargets: $targets,
                commentsEnabled: $comments,
                siteKey: $organization['site_key'],
                categoryNames: $categories,
                tagNames: $tags,
                ownerOrganizationPublicId: $organization['public_id'],
            );
        }

        $this->applyPublicationState(
            $targetId,
            $organization['site_key'],
            (string) ($content['status'] ?? 'draft'),
            isset($content['published_at']) ? (string) $content['published_at'] : null,
        );

        return $targetId;
    }

    public function importMedia(
        int $organizationId,
        array $media,
        string $sourcePath,
        bool $dryRun,
    ): string {
        $sourceId = trim((string) ($media['id'] ?? ''));
        if ($sourceId === '') {
            throw new InvalidArgumentException('Media VPDS не содержит id.');
        }

        if ($dryRun) {
            return 'dry-run:media:' . $sourceId;
        }

        $organization = $this->organization($organizationId);
        $sha256 = strtolower(trim((string) ($media['sha256'] ?? '')));
        $existing = $this->mediaByHash(
            $organization['site_key'],
            $organization['public_id'],
            $sha256,
        );
        if ($existing !== null) {
            return $existing;
        }

        $originalName = trim((string) ($media['name'] ?? basename($sourcePath)));
        $targetId = $this->mediaUpload->importFile(
            sourcePath: $sourcePath,
            originalName: $originalName,
            ownerOrganizationPublicId: $organization['public_id'],
            siteKey: $organization['site_key'],
            title: isset($media['title']) ? (string) $media['title'] : null,
            altText: isset($media['alt']) ? (string) $media['alt'] : null,
        );

        $storedHash = $this->mediaHash($targetId, $organization['site_key']);
        if ($storedHash === null || !hash_equals($sha256, $storedHash)) {
            throw new RuntimeException(
                'SHA-256 сохранённого Media не совпадает с manifest VPDS.'
            );
        }

        return $targetId;
    }

    public function saveRedirect(
        int $organizationId,
        string $from,
        string $to,
        bool $dryRun,
    ): void {
        if ($dryRun) {
            return;
        }

        $organization = $this->organization($organizationId);
        $current = $this->redirectRepository->findBySource(
            $from,
            $organization['site_key'],
        );

        if ($current === null) {
            $this->redirects->create(
                $from,
                $to,
                301,
                true,
                $organization['site_key'],
            );
            return;
        }

        if ($current->targetPath === $to && $current->statusCode === 301 && $current->enabled) {
            return;
        }

        $this->redirects->update(
            $current->publicId,
            $from,
            $to,
            301,
            true,
            $organization['site_key'],
        );
    }

    public function rememberImport(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
        string $checksum,
        string $targetId,
        bool $dryRun,
    ): void {
        if ($dryRun) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $update = $this->pdo->prepare(
            'UPDATE vpds_import_mappings
             SET checksum = :checksum,
                 target_id = :target_id,
                 updated_at = :updated_at
             WHERE organization_id = :organization_id
               AND source_site = :source_site
               AND entity_type = :entity_type
               AND source_id = :source_id'
        );
        $parameters = [
            'organization_id' => $organizationId,
            'source_site' => $sourceSite,
            'entity_type' => $entityType,
            'source_id' => $sourceId,
            'checksum' => $checksum,
            'target_id' => $targetId,
            'updated_at' => $now,
        ];
        $update->execute($parameters);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO vpds_import_mappings (
                organization_id, source_site, entity_type, source_id,
                checksum, target_id, created_at, updated_at
             ) VALUES (
                :organization_id, :source_site, :entity_type, :source_id,
                :checksum, :target_id, :created_at, :updated_at
             )'
        );

        try {
            $insert->execute($parameters + ['created_at' => $now]);
        } catch (PDOException $error) {
            $update->execute($parameters);
            if ($update->rowCount() === 0) {
                throw $error;
            }
        }
    }

    /** @return array{public_id:string,site_key:string} */
    private function organization(int $organizationId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT public_id, site_key
             FROM organization_units
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $organizationId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new InvalidArgumentException('Организация для импорта VPDS не найдена.');
        }

        return [
            'public_id' => (string) $row['public_id'],
            'site_key' => (string) $row['site_key'],
        ];
    }

    /** @param array{public_id:string,site_key:string} $organization */
    private function userCanAccessOrganization(int $userId, array $organization): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT r.role_key, s.site_key, s.scope_type, s.scope_key
             FROM admin_user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             LEFT JOIN admin_role_scopes s
               ON s.user_id = ur.user_id AND s.role_id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY ur.role_id'
        );
        $statement->execute(['user_id' => $userId]);
        $rows = $statement->fetchAll();

        if ($rows === []) {
            return false;
        }

        $roleScopes = [];
        foreach ($rows as $row) {
            if ((string) ($row['role_key'] ?? '') === 'superadmin') {
                return true;
            }

            $roleKey = (string) ($row['role_key'] ?? '');
            $roleScopes[$roleKey] ??= [];
            if (($row['scope_key'] ?? null) === null) {
                return true;
            }
            $roleScopes[$roleKey][] = $row;
        }

        foreach ($roleScopes as $scopes) {
            foreach ($scopes as $scope) {
                if ((string) ($scope['site_key'] ?? '') !== $organization['site_key']) {
                    continue;
                }
                if (
                    (string) ($scope['scope_type'] ?? '') === 'organization'
                    && (string) ($scope['scope_key'] ?? '') === $organization['public_id']
                ) {
                    return true;
                }
                if (
                    (string) ($scope['scope_type'] ?? '') === 'site'
                    && (string) ($scope['scope_key'] ?? '') === $organization['site_key']
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function previousTarget(
        int $organizationId,
        string $entityType,
        string $sourceId,
    ): ?string {
        $statement = $this->pdo->prepare(
            'SELECT target_id
             FROM vpds_import_mappings
             WHERE organization_id = :organization_id
               AND entity_type = :entity_type
               AND source_id = :source_id
             ORDER BY updated_at DESC, id DESC
             LIMIT 2'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'entity_type' => $entityType,
            'source_id' => $sourceId,
        ]);
        $rows = $statement->fetchAll();

        if (count($rows) !== 1) {
            return null;
        }

        $targetId = trim((string) ($rows[0]['target_id'] ?? ''));
        return $targetId !== '' ? $targetId : null;
    }

    /** @param array<string,mixed> $content */
    private function authorName(array $content): ?string
    {
        $direct = trim((string) ($content['author_name'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $publicId = trim((string) ($content['author_target_id'] ?? ''));
        if ($publicId === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT display_name FROM admin_users WHERE public_id = :public_id LIMIT 1'
        );
        $statement->execute(['public_id' => $publicId]);
        $name = $statement->fetchColumn();

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function publicationType(string $value): PublicationType
    {
        $normalized = strtolower(trim($value));
        $normalized = match ($normalized) {
            'post', 'material', 'article' => 'article',
            'news', 'новость' => 'news',
            'announcement', 'announce', 'объявление' => 'announcement',
            'sermon', 'проповедь' => 'sermon',
            'interview', 'интервью' => 'interview',
            'document', 'документ' => 'document',
            default => 'article',
        };

        return PublicationType::from($normalized);
    }

    private function applyPublicationState(
        string $publicId,
        string $siteKey,
        string $status,
        ?string $publishedAt,
    ): void {
        $status = strtolower(trim($status));
        $status = match ($status) {
            'published', 'publish', 'public', 'active', 'опубликовано' => 'published',
            'review', 'moderation', 'на проверке' => 'review',
            'scheduled', 'future', 'отложено' => 'scheduled',
            'withdrawn', 'archived', 'deleted', 'removed', 'архив' => 'withdrawn',
            default => 'draft',
        };

        $timestamp = null;
        if (in_array($status, ['published', 'scheduled', 'withdrawn'], true)) {
            $timestamp = $this->utcTimestamp($publishedAt) ?? gmdate('Y-m-d H:i:s');
        }

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET status = :status,
                 published_at = :published_at,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND site_key = :site_key'
        );
        $statement->execute([
            'status' => $status,
            'published_at' => $timestamp,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Не удалось применить состояние публикации VPDS.');
        }
    }

    private function utcTimestamp(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Некорректная дата публикации VPDS.');
        }
    }

    private function mediaByHash(string $siteKey, string $ownerPublicId, string $sha256): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT public_id
             FROM media_assets
             WHERE site_key = :site_key
               AND owner_organization_public_id = :owner
               AND sha256 = :sha256
               AND status <> :archived
             ORDER BY id ASC
             LIMIT 1'
        );
        $statement->execute([
            'site_key' => $siteKey,
            'owner' => $ownerPublicId,
            'sha256' => $sha256,
            'archived' => 'archived',
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function mediaHash(string $publicId, string $siteKey): ?string
    {
        $statement = $this->pdo->prepare(
            'SELECT sha256 FROM media_assets
             WHERE public_id = :public_id AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);
        $value = $statement->fetchColumn();

        return is_string($value) ? strtolower($value) : null;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $result[$item] = true;
            }
        }

        return array_keys($result);
    }
}
