<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Documents;

use ChurchCMS\Core\DatabaseManager;
use InvalidArgumentException;
use PDO;
use Throwable;

final class DocumentCategoryService
{
    private const CATEGORY_LIMIT = 8;
    private const NAME_LIMIT = 120;

    private DocumentCategoryRepository $categories;

    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->categories = new DocumentCategoryRepository(
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
    public static function fromInput(
        string $input,
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

        return self::normalize(
            array_values(array_filter($parts, 'is_string')),
        );
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    public static function fromNames(
        array $names,
    ): array {
        return self::normalize($names);
    }

    /**
     * @param list<string> $names
     */
    public function replaceForDocument(
        int $documentId,
        string $siteKey,
        array $names,
    ): void {
        $normalized = self::normalize($names);
        $ownsTransaction = !$this->pdo->inTransaction();

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $this->categories->replaceForDocument(
                $documentId,
                $siteKey,
                $normalized,
            );

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $error) {
            if (
                $ownsTransaction
                && $this->pdo->inTransaction()
            ) {
                $this->pdo->rollBack();
            }

            throw $error;
        }
    }

    /**
     * @return list<array{public_id:string,name:string,slug:string}>
     */
    public function forDocument(
        int $documentId,
    ): array {
        return $this->categories->forDocument(
            $documentId,
        );
    }

    /**
     * @param list<int> $documentIds
     * @return array<int,list<array{public_id:string,name:string,slug:string}>>
     */
    public function forDocuments(
        array $documentIds,
    ): array {
        return $this->categories->forDocuments(
            $documentIds,
        );
    }

    /**
     * @return list<array{public_id:string,name:string,slug:string}>
     */
    public function allForSite(
        string $siteKey = 'default',
    ): array {
        return $this->categories->allForSite(
            $siteKey,
        );
    }

    /**
     * @param list<array{public_id:string,name:string,slug:string}> $categories
     */
    public static function names(
        array $categories,
    ): string {
        return implode(', ', array_map(
            static fn(array $category): string =>
                (string) ($category['name'] ?? ''),
            $categories,
        ));
    }

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    private static function normalize(
        array $parts,
    ): array {
        $result = [];
        $seen = [];

        foreach ($parts as $part) {
            $name = preg_replace(
                '/\s+/u',
                ' ',
                trim($part),
            );
            $name = is_string($name)
                ? $name
                : '';

            if ($name === '') {
                continue;
            }

            $length = function_exists('mb_strlen')
                ? mb_strlen($name, 'UTF-8')
                : strlen($name);

            if ($length > self::NAME_LIMIT) {
                throw new InvalidArgumentException(
                    'Название рубрики документа слишком длинное.'
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

            if (count($result) > self::CATEGORY_LIMIT) {
                throw new InvalidArgumentException(
                    'У одного документа может быть не более восьми рубрик.'
                );
            }
        }

        return $result;
    }
}
