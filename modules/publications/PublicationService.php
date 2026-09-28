<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\PageCache;
use ChurchCMS\Core\Slugger;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class PublicationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /**
     * @param list<string> $syndicationTargets
     * @param list<string> $categoryNames
     * @param list<string> $tagNames
     */
    public function createDraft(
        string $title,
        string $slug = '',
        PublicationType $type = PublicationType::News,
        string $excerpt = '',
        string $bodyHtml = '',
        ?string $authorName = null,
        array $syndicationTargets = [],
        bool $commentsEnabled = false,
        string $siteKey = 'default',
        array $categoryNames = [],
        array $tagNames = [],
        ?string $ownerOrganizationPublicId = null,
    ): string {
        $data = $this->normalizeEditorData(
            title: $title,
            slug: $slug,
            type: $type,
            excerpt: $excerpt,
            bodyInput: $bodyHtml,
            authorName: $authorName,
            syndicationTargets: $syndicationTargets,
            commentsEnabled: $commentsEnabled,
            siteKey: $siteKey,
        );
        $categories = PublicationTaxonomyService::categoriesFromNames(
            $categoryNames,
        );
        $tags = PublicationTaxonomyService::tagsFromNames(
            $tagNames,
        );
        $ownerOrganizationPublicId = $this->organizationOwner(
            $ownerOrganizationPublicId,
            $data['site_key'],
        );

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO publications (
                    public_id, site_key, owner_organization_public_id,
                    type, status, slug, title, excerpt, body_html,
                    author_name, published_at, created_at, updated_at, syndication_targets,
                    syndication_title, syndication_excerpt, comments_enabled
                 ) VALUES (
                    :public_id, :site_key, :owner_organization_public_id,
                    :type, :status, :slug, :title, :excerpt, :body_html,
                    :author_name, NULL, :created_at, :updated_at, :syndication_targets,
                    NULL, NULL, :comments_enabled
                 )'
            );

            $statement->execute([
                'public_id' => $publicId,
                'site_key' => $data['site_key'],
                'owner_organization_public_id' =>
                    $ownerOrganizationPublicId,
                'type' => $data['type'],
                'status' => PublicationStatus::Draft->value,
                'slug' => $data['slug'],
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'body_html' => $data['body_html'],
                'author_name' => $data['author_name'],
                'created_at' => $now,
                'updated_at' => $now,
                'syndication_targets' => json_encode(
                    $data['syndication_targets'],
                    JSON_THROW_ON_ERROR,
                ),
                'comments_enabled' => $data['comments_enabled'] ? 1 : 0,
            ]);

            $publicationId = $this->publicationDatabaseId(
                $publicId,
                $data['site_key'],
            );
            (new PublicationTaxonomyService($this->pdo))
                ->replaceForPublication(
                    $publicationId,
                    $data['site_key'],
                    $categories,
                    $tags,
                );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (PDOException $error) {
            $this->rollbackOwnedTransaction($ownsTransaction);

            if (self::isUniqueViolation($error)) {
                throw new InvalidArgumentException(
                    'Адрес публикации уже используется.',
                    0,
                    $error,
                );
            }

            throw $error;
        } catch (Throwable $error) {
            $this->rollbackOwnedTransaction($ownsTransaction);
            throw $error;
        }

        return $publicId;
    }

    /**
     * @param list<string> $syndicationTargets
     * @param list<string> $categoryNames
     * @param list<string> $tagNames
     */
    public function update(
        string $publicId,
        string $title,
        string $slug,
        PublicationType $type,
        string $excerpt,
        string $bodyHtml,
        ?string $authorName,
        array $syndicationTargets,
        bool $commentsEnabled,
        string $siteKey = 'default',
        array $categoryNames = [],
        array $tagNames = [],
        ?string $ownerOrganizationPublicId = null,
    ): void {
        self::assertUuid($publicId);

        $data = $this->normalizeEditorData(
            title: $title,
            slug: $slug,
            type: $type,
            excerpt: $excerpt,
            bodyInput: $bodyHtml,
            authorName: $authorName,
            syndicationTargets: $syndicationTargets,
            commentsEnabled: $commentsEnabled,
            siteKey: $siteKey,
        );
        $categories = PublicationTaxonomyService::categoriesFromNames(
            $categoryNames,
        );
        $tags = PublicationTaxonomyService::tagsFromNames(
            $tagNames,
        );
        $ownerOrganizationPublicId =
            $ownerOrganizationPublicId !== null
                ? $this->organizationOwner(
                    $ownerOrganizationPublicId,
                    $data['site_key'],
                )
                : null;

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $publicationId = $this->publicationDatabaseId(
                $publicId,
                $data['site_key'],
            );

            $ownerAssignment = $ownerOrganizationPublicId !== null
                ? 'owner_organization_public_id = :owner_organization_public_id,'
                : '';
            $statement = $this->pdo->prepare(
                'UPDATE publications
                 SET type = :type,
                     slug = :slug,
                     title = :title,
                     excerpt = :excerpt,
                     body_html = :body_html,
                     author_name = :author_name,
                     ' . $ownerAssignment . '
                     syndication_targets = :syndication_targets,
                     comments_enabled = :comments_enabled,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $parameters = [
                'type' => $data['type'],
                'slug' => $data['slug'],
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'body_html' => $data['body_html'],
                'author_name' => $data['author_name'],
                'syndication_targets' => json_encode(
                    $data['syndication_targets'],
                    JSON_THROW_ON_ERROR,
                ),
                'comments_enabled' => $data['comments_enabled'] ? 1 : 0,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => $publicationId,
            ];

            if ($ownerOrganizationPublicId !== null) {
                $parameters['owner_organization_public_id'] =
                    $ownerOrganizationPublicId;
            }

            $statement->execute($parameters);

            (new PublicationTaxonomyService($this->pdo))
                ->replaceForPublication(
                    $publicationId,
                    $data['site_key'],
                    $categories,
                    $tags,
                );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            PageCache::bumpVersion();
        } catch (PDOException $error) {
            $this->rollbackOwnedTransaction($ownsTransaction);

            if (self::isUniqueViolation($error)) {
                throw new InvalidArgumentException(
                    'Адрес публикации уже используется.',
                    0,
                    $error,
                );
            }

            throw $error;
        } catch (Throwable $error) {
            $this->rollbackOwnedTransaction($ownsTransaction);
            throw $error;
        }
    }

    public function schedule(
        string $publicId,
        DateTimeImmutable $when,
    ): void {
        self::assertUuid($publicId);

        $utc = $when->setTimezone(
            new \DateTimeZone('UTC'),
        );
        $now = new DateTimeImmutable(
            'now',
            new \DateTimeZone('UTC'),
        );

        if ($utc <= $now) {
            throw new InvalidArgumentException(
                'Время отложенной публикации должно быть в будущем.'
            );
        }

        $statusStatement = $this->pdo->prepare(
            'SELECT status FROM publications
             WHERE public_id = :public_id
             LIMIT 1'
        );
        $statusStatement->execute([
            'public_id' => $publicId,
        ]);
        $currentStatus = $statusStatement->fetchColumn();

        if ($currentStatus === false) {
            throw new InvalidArgumentException(
                'Публикация не найдена.'
            );
        }

        if (
            (string) $currentStatus
            === PublicationStatus::Published->value
        ) {
            throw new InvalidArgumentException(
                'Опубликованный материал сначала нужно снять с публикации.'
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET status = :status,
                 published_at = :published_at,
                 updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => PublicationStatus::Scheduled->value,
            'published_at' => $utc->format('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);

        PageCache::bumpVersion();
    }

    public function unschedule(string $publicId): void
    {
        self::assertUuid($publicId);

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET status = :draft,
                 published_at = NULL,
                 updated_at = :updated_at
             WHERE public_id = :public_id
               AND status = :scheduled'
        );
        $statement->execute([
            'draft' => PublicationStatus::Draft->value,
            'scheduled' => PublicationStatus::Scheduled->value,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException(
                'Запланированная публикация не найдена.'
            );
        }

        PageCache::bumpVersion();
    }

    public function publish(string $publicId, ?DateTimeImmutable $when = null): void
    {
        self::assertUuid($publicId);
        $publishedAt = ($when ?? new DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET status = :status, published_at = :published_at, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'status' => PublicationStatus::Published->value,
            'published_at' => $publishedAt->format('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);

        PageCache::bumpVersion();
    }

    public function withdraw(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        self::assertUuid($publicId);
        $siteKey = self::siteKey($siteKey);

        $publication = (new PublicationRepository($this->pdo))
            ->findByPublicId($publicId, $siteKey);
        $partnerVisible = $publication !== null
            && $publication->status === PublicationStatus::Published
            && $publication->publishedAt !== null
            && $publication->publishedAt <= new DateTimeImmutable('now')
            && in_array(
                'diocese',
                $publication->syndicationTargets,
                true,
            );

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $updatedAt = gmdate('Y-m-d H:i:s');
            $statement = $this->pdo->prepare(
                'UPDATE publications
                 SET status = :status, updated_at = :updated_at
                 WHERE public_id = :public_id
                   AND site_key = :site_key'
            );
            $statement->execute([
                'status' => PublicationStatus::Withdrawn->value,
                'updated_at' => $updatedAt,
                'public_id' => $publicId,
                'site_key' => $siteKey,
            ]);

            if ($partnerVisible && $publication !== null) {
                (new PublicationPartnerTombstoneRepository($this->pdo))
                    ->record(
                        $publication,
                        withdrawnAt: new DateTimeImmutable(
                            $updatedAt . ' UTC',
                        ),
                    );
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }

        PageCache::bumpVersion();
    }

    /** @param list<string> $targets */
    public function setSyndicationTargets(string $publicId, array $targets): void
    {
        self::assertUuid($publicId);
        $targets = self::normalizeTargets($targets);

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET syndication_targets = :targets, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'targets' => json_encode($targets, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);

        PageCache::bumpVersion();
    }

    public function setCommentsEnabled(string $publicId, bool $enabled): void
    {
        self::assertUuid($publicId);

        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET comments_enabled = :comments_enabled, updated_at = :updated_at
             WHERE public_id = :public_id'
        );
        $statement->execute([
            'comments_enabled' => $enabled ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'public_id' => $publicId,
        ]);

        PageCache::bumpVersion();
    }

    public function assignOrganizationOwner(
        string $publicId,
        string $organizationPublicId,
        string $siteKey = 'default',
    ): void {
        self::assertUuid($publicId);
        $siteKey = self::siteKey($siteKey);
        $organizationPublicId = $this->organizationOwner(
            $organizationPublicId,
            $siteKey,
        );

        if ($organizationPublicId === null) {
            throw new InvalidArgumentException(
                'Организация-владелец не найдена.'
            );
        }

        $publicationId = $this->publicationDatabaseId(
            $publicId,
            $siteKey,
        );
        $statement = $this->pdo->prepare(
            'UPDATE publications
             SET owner_organization_public_id = :owner,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'owner' => $organizationPublicId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $publicationId,
        ]);

        PageCache::bumpVersion();
    }

    /**
     * @param list<string> $syndicationTargets
     * @return array{
     *   title:string,slug:string,type:string,excerpt:string,body_html:string,
     *   author_name:?string,syndication_targets:list<string>,comments_enabled:bool,site_key:string
     * }
     */
    private function normalizeEditorData(
        string $title,
        string $slug,
        PublicationType $type,
        string $excerpt,
        string $bodyInput,
        ?string $authorName,
        array $syndicationTargets,
        bool $commentsEnabled,
        string $siteKey,
    ): array {
        $title = trim($title);
        $siteKey = trim($siteKey);

        $titleLength = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($title === '' || $titleLength > 255) {
            throw new InvalidArgumentException('Publication title is required and must be <= 255 characters.');
        }

        $slug = trim($slug);
        if ($slug === '') {
            $slug = Slugger::fromText($title);
        } else {
            $slug = Slugger::fromText($slug);
        }

        if ($slug === '' || strlen($slug) > 180) {
            throw new InvalidArgumentException('Invalid publication URL.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $siteKey) !== 1) {
            throw new InvalidArgumentException('Invalid site key.');
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'type' => $type->value,
            'excerpt' => trim($excerpt),
            'body_html' => HtmlSanitizer::fromEditorInput($bodyInput),
            'author_name' => $authorName !== null && trim($authorName) !== '' ? trim($authorName) : null,
            'syndication_targets' => self::normalizeTargets($syndicationTargets),
            'comments_enabled' => $commentsEnabled,
            'site_key' => $siteKey,
        ];
    }

    /** @param list<string> $targets
     *  @return list<string>
     */
    private static function normalizeTargets(array $targets): array
    {
        $allowed = [];
        foreach ($targets as $target) {
            if (!is_string($target) || preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $target) !== 1) {
                throw new InvalidArgumentException('Invalid syndication target.');
            }
            $allowed[$target] = true;
        }

        $result = array_keys($allowed);
        sort($result, SORT_STRING);
        return $result;
    }

    private function publicationDatabaseId(
        string $publicId,
        string $siteKey,
    ): int {
        $statement = $this->pdo->prepare(
            'SELECT id FROM publications
             WHERE public_id = :public_id
               AND site_key = :site_key
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
        ]);

        $id = $statement->fetchColumn();
        if ($id === false || (int) $id <= 0) {
            throw new InvalidArgumentException(
                'Публикация не найдена.'
            );
        }

        return (int) $id;
    }

    private function organizationOwner(
        ?string $publicId,
        string $siteKey,
    ): ?string {
        $repository = new OrganizationRepository($this->pdo);
        $publicId = $publicId !== null
            ? trim($publicId)
            : '';

        if ($publicId === '') {
            return $repository->siteRoot($siteKey)?->publicId;
        }

        self::assertUuid($publicId);
        $organization = $repository->findByPublicId(
            $publicId,
            $siteKey,
        );

        if ($organization === null) {
            throw new InvalidArgumentException(
                'Организация-владелец не найдена на этом сайте.'
            );
        }

        if ($organization->status !== 'active') {
            throw new InvalidArgumentException(
                'Архивная организация не может владеть публикацией.'
            );
        }

        return $organization->publicId;
    }

    private static function siteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9_.-]{0,63}$/D',
                $siteKey,
            ) !== 1
        ) {
            throw new InvalidArgumentException('Invalid site key.');
        }

        return $siteKey;
    }

    private function rollbackOwnedTransaction(
        bool $ownsTransaction,
    ): void {
        if ($ownsTransaction && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function assertUuid(string $value): void
    {
        if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value) !== 1) {
            throw new InvalidArgumentException('Invalid public id.');
        }
    }

    private static function isUniqueViolation(PDOException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
        return in_array($sqlState, ['23000', '23505'], true);
    }
}
