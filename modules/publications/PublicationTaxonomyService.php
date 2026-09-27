<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\DatabaseManager;
use InvalidArgumentException;
use PDO;

final class PublicationTaxonomyService
{
    private const CATEGORY_LIMIT = 8;
    private const TAG_LIMIT = 20;
    private const NAME_LIMIT = 120;

    private PublicationTaxonomyRepository $repository;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->repository = new PublicationTaxonomyRepository(
            $pdo,
        );
    }

    public static function fromDatabase(): self
    {
        return new self(
            DatabaseManager::getInstance()->connection(),
        );
    }

    /**
     * @return list<string>
     */
    public static function categoriesFromInput(
        string $input,
    ): array {
        return self::normalizeNames(
            $input,
            self::CATEGORY_LIMIT,
            'категорий',
        );
    }

    /**
     * @return list<string>
     */
    public static function tagsFromInput(
        string $input,
    ): array {
        return self::normalizeNames(
            $input,
            self::TAG_LIMIT,
            'тегов',
        );
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    public static function categoriesFromNames(
        array $names,
    ): array {
        return self::normalizeParts(
            $names,
            self::CATEGORY_LIMIT,
            'категорий',
        );
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    public static function tagsFromNames(
        array $names,
    ): array {
        return self::normalizeParts(
            $names,
            self::TAG_LIMIT,
            'тегов',
        );
    }

    /**
     * @param list<string> $categories
     * @param list<string> $tags
     */
    public function replaceForPublication(
        int $publicationId,
        string $siteKey,
        array $categories,
        array $tags,
    ): void {
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->repository->replaceForPublication(
                $publicationId,
                $siteKey,
                $categories,
                $tags,
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * @return array{
     *     categories:list<array{public_id:string,name:string,slug:string}>,
     *     tags:list<array{public_id:string,name:string,slug:string}>
     * }
     */
    public function forPublication(int $publicationId): array
    {
        return $this->repository->forPublication(
            $publicationId,
        );
    }

    /**
     * @param list<int> $publicationIds
     * @return array<int,array{
     *     categories:list<array{public_id:string,name:string,slug:string}>,
     *     tags:list<array{public_id:string,name:string,slug:string}>
     * }>
     */
    public function forPublications(array $publicationIds): array
    {
        return $this->repository->forPublications(
            $publicationIds,
        );
    }

    /**
     * @param list<array{public_id:string,name:string,slug:string}> $terms
     */
    public static function names(array $terms): string
    {
        return implode(', ', array_map(
            static fn(array $term): string =>
                (string) ($term['name'] ?? ''),
            $terms,
        ));
    }

    /**
     * @return list<string>
     */
    private static function normalizeNames(
        string $input,
        int $limit,
        string $label,
    ): array {
        $input = trim($input);
        if ($input === '') {
            return [];
        }

        $parts = preg_split(
            '/[,;\n\r]+/u',
            $input,
        );
        if (!is_array($parts)) {
            return [];
        }

        return self::normalizeParts(
            array_values(array_filter($parts, 'is_string')),
            $limit,
            $label,
        );
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    private static function normalizeParts(
        array $parts,
        int $limit,
        string $label,
    ): array {
        $result = [];
        $seen = [];

        foreach ($parts as $part) {
            $name = preg_replace(
                '/\s+/u',
                ' ',
                trim($part),
            );
            $name = is_string($name) ? $name : '';

            if ($name === '') {
                continue;
            }

            $length = function_exists('mb_strlen')
                ? mb_strlen($name, 'UTF-8')
                : strlen($name);

            if ($length > self::NAME_LIMIT) {
                throw new InvalidArgumentException(
                    'Название категории или тега слишком длинное.'
                );
            }

            $key = function_exists('mb_strtolower')
                ? mb_strtolower($name, 'UTF-8')
                : strtolower($name);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $name;

            if (count($result) > $limit) {
                throw new InvalidArgumentException(
                    'Слишком много ' . $label . ' у одной публикации.'
                );
            }
        }

        return $result;
    }
}
