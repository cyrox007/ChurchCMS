<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Pages;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\PageCache;
use ChurchCMS\Core\Slugger;
use ChurchCMS\Core\Uuid;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class PageService
{
    private const MAX_PATH_LENGTH = 700;
    private const MAX_SORT_ORDER = 1000000;

    private PageRepository $repository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new PageRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function createDraft(
        string $title,
        string $slug = '',
        string $bodyInput = '',
        ?string $navigationTitle = null,
        ?string $parentPublicId = null,
        int $sortOrder = 0,
        string $siteKey = 'default',
        ?string $ownerOrganizationPublicId = null,
    ): string {
        $title = self::title($title);
        $slug = self::slug($slug, $title);
        $navigationTitle = self::navigationTitle(
            $navigationTitle,
        );
        $sortOrder = self::sortOrder($sortOrder);
        $siteKey = self::siteKey($siteKey);
        $ownerOrganizationPublicId = $this->organizationOwner(
            $ownerOrganizationPublicId,
            $siteKey,
        );

        $parent = $this->parent(
            $parentPublicId,
            $siteKey,
        );
        $path = self::buildPath($parent?->path, $slug);

        if ($this->repository->pathExists($path, $siteKey)) {
            throw new InvalidArgumentException(
                'В этом разделе уже существует страница с таким адресом.'
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO pages (
                    public_id,
                    site_key,
                    owner_organization_public_id,
                    parent_id,
                    status,
                    slug,
                    path,
                    title,
                    navigation_title,
                    body_html,
                    sort_order,
                    published_at,
                    created_at,
                    updated_at
                 ) VALUES (
                    :public_id,
                    :site_key,
                    :owner_organization_public_id,
                    :parent_id,
                    :status,
                    :slug,
                    :path,
                    :title,
                    :navigation_title,
                    :body_html,
                    :sort_order,
                    NULL,
                    :created_at,
                    :updated_at
                 )'
            );
            $statement->execute([
                'public_id' => $publicId,
                'site_key' => $siteKey,
                'owner_organization_public_id' =>
                    $ownerOrganizationPublicId,
                'parent_id' => $parent?->id,
                'status' => PageStatus::Draft->value,
                'slug' => $slug,
                'path' => $path,
                'title' => $title,
                'navigation_title' => $navigationTitle,
                'body_html' => HtmlSanitizer::fromEditorInput(
                    $bodyInput,
                ),
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $error) {
            self::throwFriendlyUnique($error);
            throw $error;
        }

        PageCache::bumpVersion();

        return $publicId;
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

        $page = $this->requiredPage($publicId, $siteKey);
        $statement = $this->pdo->prepare(
            'UPDATE pages
             SET owner_organization_public_id = :owner,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'owner' => $organizationPublicId,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $page->id,
        ]);

        PageCache::bumpVersion();
    }

    public function updateContent(
        string $publicId,
        string $title,
        string $slug,
        string $bodyInput,
        ?string $navigationTitle = null,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        self::assertUuid($publicId);
        $title = self::title($title);
        $slug = self::slug($slug, $title);
        $navigationTitle = self::navigationTitle(
            $navigationTitle,
        );
        $sortOrder = self::sortOrder($sortOrder);
        $siteKey = self::siteKey($siteKey);

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $page = $this->requiredPage(
                $publicId,
                $siteKey,
            );
            $parent = $page->parentId !== null
                ? $this->repository->findById(
                    $page->parentId,
                    $siteKey,
                )
                : null;
            $newPath = self::buildPath(
                $parent?->path,
                $slug,
            );

            if ($newPath !== $page->path) {
                $this->relocateSubtree(
                    $page,
                    $parent,
                    $slug,
                    $sortOrder,
                );
            }

            $statement = $this->pdo->prepare(
                'UPDATE pages
                 SET slug = :slug,
                     title = :title,
                     navigation_title = :navigation_title,
                     body_html = :body_html,
                     sort_order = :sort_order,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $statement->execute([
                'slug' => $slug,
                'title' => $title,
                'navigation_title' => $navigationTitle,
                'body_html' => HtmlSanitizer::fromEditorInput(
                    $bodyInput,
                ),
                'sort_order' => $sortOrder,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => $page->id,
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            PageCache::bumpVersion();
        } catch (Throwable $error) {
            self::rollback(
                $this->pdo,
                $ownsTransaction,
            );

            if ($error instanceof PDOException) {
                self::throwFriendlyUnique($error);
            }

            throw $error;
        }
    }

    public function moveSubtree(
        string $publicId,
        ?string $newParentPublicId,
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        self::assertUuid($publicId);
        $sortOrder = self::sortOrder($sortOrder);
        $siteKey = self::siteKey($siteKey);

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $page = $this->requiredPage(
                $publicId,
                $siteKey,
            );
            $parent = $this->parent(
                $newParentPublicId,
                $siteKey,
            );

            if ($parent !== null) {
                if ($parent->id === $page->id) {
                    throw new InvalidArgumentException(
                        'Страница не может быть родителем самой себя.'
                    );
                }

                if (
                    $parent->path === $page->path
                    || str_starts_with(
                        $parent->path,
                        $page->path . '/',
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Нельзя переместить страницу внутрь её собственного подраздела.'
                    );
                }
            }

            $this->relocateSubtree(
                $page,
                $parent,
                $page->slug,
                $sortOrder,
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            PageCache::bumpVersion();
        } catch (Throwable $error) {
            self::rollback(
                $this->pdo,
                $ownsTransaction,
            );

            if ($error instanceof PDOException) {
                self::throwFriendlyUnique($error);
            }

            throw $error;
        }
    }

    private function relocateSubtree(
        Page $page,
        ?Page $newParent,
        string $newSlug,
        int $sortOrder,
    ): void {
        $newRootPath = self::buildPath(
            $newParent?->path,
            $newSlug,
        );
        $subtree = $this->repository->subtree($page);
        $ids = array_fill_keys(
            array_map(
                static fn(Page $item): int => $item->id,
                $subtree,
            ),
            true,
        );
        $newPaths = [];

        foreach ($subtree as $item) {
            $suffix = $item->id === $page->id
                ? ''
                : substr(
                    $item->path,
                    strlen($page->path),
                );
            $candidate = $newRootPath . $suffix;

            self::assertPathLength($candidate);

            $existing = $this->repository->findByPath(
                $candidate,
                $page->siteKey,
            );
            if (
                $existing !== null
                && !isset($ids[$existing->id])
            ) {
                throw new InvalidArgumentException(
                    'Перемещение создаёт конфликт адресов страниц.'
                );
            }

            $newPaths[$item->id] = $candidate;
        }

        $updateRoot = $this->pdo->prepare(
            'UPDATE pages
             SET parent_id = :parent_id,
                 slug = :slug,
                 path = :path,
                 sort_order = :sort_order,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $updateRoot->execute([
            'parent_id' => $newParent?->id,
            'slug' => $newSlug,
            'path' => $newPaths[$page->id],
            'sort_order' => $sortOrder,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $page->id,
        ]);

        $updateChild = $this->pdo->prepare(
            'UPDATE pages
             SET path = :path,
                 updated_at = :updated_at
             WHERE id = :id'
        );

        foreach ($subtree as $item) {
            if ($item->id === $page->id) {
                continue;
            }

            $updateChild->execute([
                'path' => $newPaths[$item->id],
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => $item->id,
            ]);
        }
    }

    private function requiredPage(
        string $publicId,
        string $siteKey,
    ): Page {
        $page = $this->repository->findByPublicId(
            $publicId,
            $siteKey,
        );

        if ($page === null) {
            throw new InvalidArgumentException(
                'Страница не найдена.'
            );
        }

        return $page;
    }

    private function parent(
        ?string $publicId,
        string $siteKey,
    ): ?Page {
        if ($publicId === null || trim($publicId) === '') {
            return null;
        }

        self::assertUuid($publicId);

        return $this->requiredPage(
            $publicId,
            $siteKey,
        );
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

        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $publicId,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID организации-владельца.'
            );
        }

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
                'Архивная организация не может владеть страницей.'
            );
        }

        return $organization->publicId;
    }

    private static function buildPath(
        ?string $parentPath,
        string $slug,
    ): string {
        $path = $parentPath === null
            ? '/' . $slug
            : rtrim($parentPath, '/') . '/' . $slug;

        self::assertPathLength($path);

        return $path;
    }

    private static function assertPathLength(string $path): void
    {
        if (
            $path === ''
            || strlen($path) > self::MAX_PATH_LENGTH
        ) {
            throw new InvalidArgumentException(
                'Адрес страницы слишком длинный.'
            );
        }
    }

    private static function title(string $value): string
    {
        $value = trim($value);
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($value === '' || $length > 255) {
            throw new InvalidArgumentException(
                'Название страницы обязательно и должно быть не длиннее 255 символов.'
            );
        }

        return $value;
    }

    private static function navigationTitle(
        ?string $value,
    ): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length > 255) {
            throw new InvalidArgumentException(
                'Название страницы для меню слишком длинное.'
            );
        }

        return $value;
    }

    private static function slug(
        string $value,
        string $fallback,
    ): string {
        $slug = Slugger::fromText(
            trim($value) === '' ? $fallback : $value,
            'page',
        );

        if (
            $slug === ''
            || strlen($slug) > 180
        ) {
            throw new InvalidArgumentException(
                'Некорректный адрес страницы.'
            );
        }

        return $slug;
    }

    private static function sortOrder(int $value): int
    {
        if (
            $value < 0
            || $value > self::MAX_SORT_ORDER
        ) {
            throw new InvalidArgumentException(
                'Порядок страницы вне допустимого диапазона.'
            );
        }

        return $value;
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
                'Некорректный идентификатор сайта.'
            );
        }

        return $value;
    }

    private static function assertUuid(string $value): void
    {
        if (
            preg_match(
                '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный public ID страницы.'
            );
        }
    }

    private static function rollback(
        PDO $pdo,
        bool $ownsTransaction,
    ): void {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private static function throwFriendlyUnique(
        PDOException $error,
    ): void {
        $sqlState = (string) (
            $error->errorInfo[0]
            ?? $error->getCode()
        );

        if (
            in_array(
                $sqlState,
                ['23000', '23505'],
                true,
            )
        ) {
            throw new InvalidArgumentException(
                'В этом разделе уже существует страница с таким адресом.',
                0,
                $error,
            );
        }
    }
}
