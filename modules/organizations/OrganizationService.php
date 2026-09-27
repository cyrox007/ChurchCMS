<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Organizations;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\HtmlSanitizer;
use ChurchCMS\Core\SiteProfileCatalog;
use ChurchCMS\Core\Slugger;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;
use PDOException;
use Throwable;

final class OrganizationService
{
    private OrganizationRepository $repository;

    public function __construct(private readonly PDO $pdo)
    {
        $this->repository = new OrganizationRepository(
            $pdo,
        );
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    public function ensureSiteRoot(
        string $name,
        string $profile,
        string $siteKey = 'default',
    ): OrganizationUnit {
        $existing = $this->repository->siteRoot($siteKey);
        if ($existing !== null) {
            return $existing;
        }

        if (!SiteProfileCatalog::exists($profile)) {
            throw new InvalidArgumentException(
                'Неизвестный профиль сайта.'
            );
        }

        $type = SiteProfileCatalog::organizationType(
            $profile,
        );
        if ($type === null) {
            throw new InvalidArgumentException(
                'Для профиля не определён тип организации.'
            );
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $publicId = $this->create(
                name: $name,
                type: $type,
                parentPublicId: null,
                slug: '',
                siteKey: $siteKey,
            );
            $root = $this->repository->findByPublicId(
                $publicId,
                $siteKey,
            );

            if ($root === null) {
                throw new \RuntimeException(
                    'Не удалось получить корневую организацию.'
                );
            }

            $now = gmdate('Y-m-d H:i:s');
            $statement = $this->pdo->prepare(
                'INSERT INTO organization_site_roots (
                    site_key,
                    organization_id,
                    profile,
                    created_at,
                    updated_at
                 ) VALUES (
                    :site_key,
                    :organization_id,
                    :profile,
                    :created_at,
                    :updated_at
                 )'
            );
            $statement->execute([
                'site_key' => $siteKey,
                'organization_id' => $root->id,
                'profile' => $profile,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }

            return $root;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $current = $this->repository->siteRoot(
                $siteKey,
            );
            if ($current !== null) {
                return $current;
            }

            throw $error;
        }
    }

    public function create(
        string $name,
        string $type,
        ?string $parentPublicId = null,
        string $slug = '',
        ?string $shortName = null,
        ?string $legalName = null,
        string $descriptionInput = '',
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): string {
        $name = self::name($name);
        $type = self::type($type);
        $siteKey = self::siteKey($siteKey);
        $sortOrder = self::sortOrder($sortOrder);
        $shortName = self::optionalName(
            $shortName,
            255,
            'Краткое название',
        );
        $legalName = self::optionalName(
            $legalName,
            500,
            'Юридическое название',
        );

        $parent = null;
        if (
            $parentPublicId !== null
            && trim($parentPublicId) !== ''
        ) {
            $parent = $this->repository->findByPublicId(
                $parentPublicId,
                $siteKey,
            );

            if ($parent === null) {
                throw new InvalidArgumentException(
                    'Родительская организация не найдена.'
                );
            }
        }

        $slug = Slugger::fromText(
            trim($slug) === '' ? $name : $slug,
            'organization',
        );

        if ($slug === '' || strlen($slug) > 180) {
            throw new InvalidArgumentException(
                'Некорректный адрес организации.'
            );
        }

        $path = $parent === null
            ? '/' . $slug
            : rtrim($parent->path, '/') . '/' . $slug;

        if (strlen($path) > 700) {
            throw new InvalidArgumentException(
                'Организационная иерархия слишком глубока.'
            );
        }

        if (
            $this->repository->findByPath(
                $path,
                $siteKey,
            ) !== null
        ) {
            throw new InvalidArgumentException(
                'В этом разделе уже существует организация с таким адресом.'
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO organization_units (
                public_id,
                site_key,
                parent_id,
                unit_type,
                status,
                slug,
                path,
                name,
                short_name,
                legal_name,
                description_html,
                sort_order,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :parent_id,
                :unit_type,
                :status,
                :slug,
                :path,
                :name,
                :short_name,
                :legal_name,
                :description_html,
                :sort_order,
                :created_at,
                :updated_at
             )'
        );

        try {
            $statement->execute([
                'public_id' => $publicId,
                'site_key' => $siteKey,
                'parent_id' => $parent?->id,
                'unit_type' => $type,
                'status' => 'active',
                'slug' => $slug,
                'path' => $path,
                'name' => $name,
                'short_name' => $shortName,
                'legal_name' => $legalName,
                'description_html' => HtmlSanitizer::fromEditorInput(
                    $descriptionInput,
                ),
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $error) {
            if (self::isUniqueViolation($error)) {
                throw new InvalidArgumentException(
                    'Организация с таким адресом уже существует.',
                    0,
                    $error,
                );
            }

            throw $error;
        }

        return $publicId;
    }

    public function update(
        string $publicId,
        string $name,
        string $type,
        ?string $parentPublicId = null,
        string $slug = '',
        ?string $shortName = null,
        ?string $legalName = null,
        string $descriptionInput = '',
        int $sortOrder = 0,
        string $siteKey = 'default',
    ): void {
        self::assertUuid($publicId);

        $name = self::name($name);
        $type = self::type($type);
        $siteKey = self::siteKey($siteKey);
        $sortOrder = self::sortOrder($sortOrder);
        $shortName = self::optionalName(
            $shortName,
            255,
            'Краткое название',
        );
        $legalName = self::optionalName(
            $legalName,
            500,
            'Юридическое название',
        );
        $slug = Slugger::fromText(
            trim($slug) === '' ? $name : $slug,
            'organization',
        );

        if ($slug === '' || strlen($slug) > 180) {
            throw new InvalidArgumentException(
                'Некорректный адрес организации.'
            );
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $unit = $this->requiredUnit(
                $publicId,
                $siteKey,
            );
            $root = $this->repository->siteRoot(
                $siteKey,
            );
            $parent = $this->parentUnit(
                $parentPublicId,
                $siteKey,
            );

            if (
                $root !== null
                && $unit->id === $root->id
                && $parent !== null
            ) {
                throw new InvalidArgumentException(
                    'Корневая организация сайта не может иметь родителя.'
                );
            }

            if ($parent !== null) {
                if ($parent->id === $unit->id) {
                    throw new InvalidArgumentException(
                        'Организация не может быть родителем самой себя.'
                    );
                }

                if (
                    $parent->path === $unit->path
                    || str_starts_with(
                        $parent->path,
                        $unit->path . '/',
                    )
                ) {
                    throw new InvalidArgumentException(
                        'Нельзя переместить организацию внутрь её собственного подразделения.'
                    );
                }
            }

            $newPath = self::buildPath(
                $parent?->path,
                $slug,
            );

            $this->relocateSubtree(
                $unit,
                $newPath,
            );

            $statement = $this->pdo->prepare(
                'UPDATE organization_units
                 SET parent_id = :parent_id,
                     unit_type = :unit_type,
                     slug = :slug,
                     path = :path,
                     name = :name,
                     short_name = :short_name,
                     legal_name = :legal_name,
                     description_html = :description_html,
                     sort_order = :sort_order,
                     updated_at = :updated_at
                 WHERE id = :id'
            );
            $statement->execute([
                'parent_id' => $parent?->id,
                'unit_type' => $type,
                'slug' => $slug,
                'path' => $newPath,
                'name' => $name,
                'short_name' => $shortName,
                'legal_name' => $legalName,
                'description_html' => HtmlSanitizer::fromEditorInput(
                    $descriptionInput,
                ),
                'sort_order' => $sortOrder,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'id' => $unit->id,
            ]);

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            self::rollbackOwnedTransaction(
                $this->pdo,
                $ownsTransaction,
            );

            if (
                $error instanceof PDOException
                && self::isUniqueViolation($error)
            ) {
                throw new InvalidArgumentException(
                    'В этом разделе уже существует организация с таким адресом.',
                    0,
                    $error,
                );
            }

            throw $error;
        }
    }

    public function archiveSubtree(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        $this->setSubtreeStatus(
            $publicId,
            'archived',
            $siteKey,
        );
    }

    public function restoreSubtree(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        $unit = $this->requiredUnit(
            $publicId,
            $siteKey,
        );

        if ($unit->parentId !== null) {
            $parent = $this->repository->findById(
                $unit->parentId,
                $siteKey,
            );

            if (
                $parent === null
                || $parent->status !== 'active'
            ) {
                throw new InvalidArgumentException(
                    'Сначала восстановите родительскую организацию.'
                );
            }
        }

        $this->setSubtreeStatus(
            $publicId,
            'active',
            $siteKey,
        );
    }

    private function setSubtreeStatus(
        string $publicId,
        string $status,
        string $siteKey,
    ): void {
        self::assertUuid($publicId);
        $siteKey = self::siteKey($siteKey);

        if (!in_array($status, ['active', 'archived'], true)) {
            throw new InvalidArgumentException(
                'Некорректный статус организации.'
            );
        }

        $unit = $this->requiredUnit(
            $publicId,
            $siteKey,
        );
        $root = $this->repository->siteRoot(
            $siteKey,
        );

        if (
            $status === 'archived'
            && $root !== null
            && $root->id === $unit->id
        ) {
            throw new InvalidArgumentException(
                'Корневую организацию сайта нельзя архивировать.'
            );
        }

        $subtree = $this->repository->subtree($unit);
        $statement = $this->pdo->prepare(
            'UPDATE organization_units
             SET status = :status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $now = gmdate('Y-m-d H:i:s');

        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            foreach ($subtree as $item) {
                $statement->execute([
                    'status' => $status,
                    'updated_at' => $now,
                    'id' => $item->id,
                ]);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            self::rollbackOwnedTransaction(
                $this->pdo,
                $ownsTransaction,
            );
            throw $error;
        }
    }

    private function relocateSubtree(
        OrganizationUnit $root,
        string $newRootPath,
    ): void {
        $subtree = $this->repository->subtree($root);
        $subtreeIds = array_fill_keys(
            array_map(
                static fn(OrganizationUnit $unit): int =>
                    $unit->id,
                $subtree,
            ),
            true,
        );
        $paths = [];

        foreach ($subtree as $unit) {
            $suffix = $unit->id === $root->id
                ? ''
                : substr(
                    $unit->path,
                    strlen($root->path),
                );
            $candidate = $newRootPath . $suffix;

            self::assertPath($candidate);

            $existing = $this->repository->findByPath(
                $candidate,
                $root->siteKey,
            );

            if (
                $existing !== null
                && !isset($subtreeIds[$existing->id])
            ) {
                throw new InvalidArgumentException(
                    'Перемещение создаёт конфликт адресов организаций.'
                );
            }

            $paths[$unit->id] = $candidate;
        }

        $update = $this->pdo->prepare(
            'UPDATE organization_units
             SET path = :path,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $now = gmdate('Y-m-d H:i:s');

        foreach ($subtree as $unit) {
            if ($unit->id === $root->id) {
                continue;
            }

            $update->execute([
                'path' => $paths[$unit->id],
                'updated_at' => $now,
                'id' => $unit->id,
            ]);
        }
    }

    private function requiredUnit(
        string $publicId,
        string $siteKey,
    ): OrganizationUnit {
        $unit = $this->repository->findByPublicId(
            $publicId,
            $siteKey,
        );

        if ($unit === null) {
            throw new InvalidArgumentException(
                'Организация не найдена.'
            );
        }

        return $unit;
    }

    private function parentUnit(
        ?string $publicId,
        string $siteKey,
    ): ?OrganizationUnit {
        if (
            $publicId === null
            || trim($publicId) === ''
        ) {
            return null;
        }

        self::assertUuid($publicId);

        return $this->requiredUnit(
            $publicId,
            $siteKey,
        );
    }

    private static function buildPath(
        ?string $parentPath,
        string $slug,
    ): string {
        $path = $parentPath === null
            ? '/' . $slug
            : rtrim($parentPath, '/') . '/' . $slug;

        self::assertPath($path);

        return $path;
    }

    private static function assertPath(string $path): void
    {
        if ($path === '' || strlen($path) > 700) {
            throw new InvalidArgumentException(
                'Организационная иерархия слишком глубока.'
            );
        }
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
                'Некорректный public ID организации.'
            );
        }
    }

    private static function rollbackOwnedTransaction(
        PDO $pdo,
        bool $ownsTransaction,
    ): void {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private static function name(string $value): string
    {
        $value = trim($value);
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($value === '' || $length > 255) {
            throw new InvalidArgumentException(
                'Название организации обязательно и должно быть не длиннее 255 символов.'
            );
        }

        return $value;
    }

    private static function optionalName(
        ?string $value,
        int $limit,
        string $label,
    ): ?string {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length > $limit) {
            throw new InvalidArgumentException(
                $label . ' слишком длинное.'
            );
        }

        return $value;
    }

    private static function type(string $value): string
    {
        $value = trim($value);

        if (
            preg_match(
                '/^[a-z][a-z0-9_.-]{1,63}$/D',
                $value,
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Некорректный тип организации.'
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

    private static function sortOrder(int $value): int
    {
        if ($value < 0 || $value > 1000000) {
            throw new InvalidArgumentException(
                'Порядок организации вне допустимого диапазона.'
            );
        }

        return $value;
    }

    private static function isUniqueViolation(
        PDOException $error,
    ): bool {
        $sqlState = (string) (
            $error->errorInfo[0]
            ?? $error->getCode()
        );

        return in_array(
            $sqlState,
            ['23000', '23505'],
            true,
        );
    }
}
