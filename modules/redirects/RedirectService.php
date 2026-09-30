<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Redirects;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\Uuid;
use InvalidArgumentException;
use PDO;

final class RedirectService
{
    private const ALLOWED_STATUS = [
        301,
        302,
        307,
        308,
    ];

    private const RESERVED_SOURCE_PREFIXES = [
        '/admin',
        '/api',
        '/install',
        '/health',
        '/media',
        '/assets',
        '/.well-known',
    ];

    public function __construct(
        private readonly PDO $pdo,
        private readonly RedirectRepository $repository,
    ) {
    }

    public static function fromDatabase(): self
    {
        $pdo = DatabaseManager::getInstance()->connection();

        return new self(
            $pdo,
            new RedirectRepository($pdo),
        );
    }

    public function create(
        string $sourcePath,
        string $targetPath,
        int $statusCode = 301,
        bool $enabled = true,
        string $siteKey = 'default',
    ): RedirectRule {
        $siteKey = self::siteKey($siteKey);
        $sourcePath = self::sourcePath($sourcePath);
        $targetPath = self::targetPath($targetPath);
        self::statusCode($statusCode);

        if (
            $this->repository->findBySource(
                $sourcePath,
                $siteKey,
            ) !== null
        ) {
            throw new InvalidArgumentException(
                'Для исходного пути уже существует правило.'
            );
        }

        if ($enabled) {
            $this->assertNoChain(
                $sourcePath,
                $targetPath,
                null,
                $siteKey,
            );
        }

        $publicId = Uuid::v4();
        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->pdo->prepare(
            'INSERT INTO redirect_rules (
                public_id,
                site_key,
                source_path,
                target_path,
                status_code,
                enabled,
                hit_count,
                last_hit_at,
                created_at,
                updated_at
             ) VALUES (
                :public_id,
                :site_key,
                :source_path,
                :target_path,
                :status_code,
                :enabled,
                0,
                NULL,
                :created_at,
                :updated_at
             )'
        );
        $statement->execute([
            'public_id' => $publicId,
            'site_key' => $siteKey,
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'status_code' => $statusCode,
            'enabled' => $enabled ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->required($publicId, $siteKey);
    }

    public function update(
        string $publicId,
        string $sourcePath,
        string $targetPath,
        int $statusCode,
        bool $enabled,
        string $siteKey = 'default',
    ): RedirectRule {
        $siteKey = self::siteKey($siteKey);
        $current = $this->required($publicId, $siteKey);
        $sourcePath = self::sourcePath($sourcePath);
        $targetPath = self::targetPath($targetPath);
        self::statusCode($statusCode);

        $sameSource = $this->repository->findBySource(
            $sourcePath,
            $siteKey,
        );
        if (
            $sameSource !== null
            && $sameSource->id !== $current->id
        ) {
            throw new InvalidArgumentException(
                'Для исходного пути уже существует правило.'
            );
        }

        if ($enabled) {
            $this->assertNoChain(
                $sourcePath,
                $targetPath,
                $current->id,
                $siteKey,
            );
        }

        $statement = $this->pdo->prepare(
            'UPDATE redirect_rules
             SET source_path = :source_path,
                 target_path = :target_path,
                 status_code = :status_code,
                 enabled = :enabled,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            'source_path' => $sourcePath,
            'target_path' => $targetPath,
            'status_code' => $statusCode,
            'enabled' => $enabled ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $current->id,
        ]);

        return $this->required($publicId, $siteKey);
    }

    public function delete(
        string $publicId,
        string $siteKey = 'default',
    ): void {
        $rule = $this->required(
            $publicId,
            self::siteKey($siteKey),
        );

        $this->repository->delete($rule->id);
    }

    public function match(
        string $requestPath,
        string $siteKey = 'default',
    ): ?RedirectRule {
        try {
            $sourcePath = self::sourcePath(
                $requestPath,
                allowRoot: true,
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->repository->enabledBySource(
            $sourcePath,
            self::siteKey($siteKey),
        );
    }

    public function recordHit(RedirectRule $rule): void
    {
        $this->repository->recordHit($rule->id);
    }

    private function required(
        string $publicId,
        string $siteKey,
    ): RedirectRule {
        $rule = $this->repository->findByPublicId(
            trim($publicId),
            $siteKey,
        );

        if ($rule === null) {
            throw new InvalidArgumentException(
                'Правило редиректа не найдено.'
            );
        }

        return $rule;
    }

    private function assertNoChain(
        string $sourcePath,
        string $targetPath,
        ?int $excludeId,
        string $siteKey,
    ): void {
        $targetSource = self::targetSourcePath(
            $targetPath,
        );

        if ($sourcePath === $targetSource) {
            throw new InvalidArgumentException(
                'Исходный и целевой пути совпадают.'
            );
        }

        foreach ($this->repository->all($siteKey) as $rule) {
            if (
                !$rule->enabled
                || (
                    $excludeId !== null
                    && $rule->id === $excludeId
                )
            ) {
                continue;
            }

            $ruleTarget = self::targetSourcePath(
                $rule->targetPath,
            );

            if (
                $rule->sourcePath === $targetSource
                || $ruleTarget === $sourcePath
            ) {
                throw new InvalidArgumentException(
                    'Активные редиректы не могут образовывать цепочки или циклы.'
                );
            }
        }
    }

    private static function sourcePath(
        string $value,
        bool $allowRoot = false,
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || !str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '?')
            || str_contains($value, '#')
            || str_contains($value, "\\")
        ) {
            throw new InvalidArgumentException(
                'Исходный URL должен быть локальным путём без query/fragment.'
            );
        }

        $path = self::canonicalPath($value);

        if (!$allowRoot && $path === '/') {
            throw new InvalidArgumentException(
                'Редирект корня сайта запрещён.'
            );
        }

        if (strlen($path) > 700) {
            throw new InvalidArgumentException(
                'Исходный путь редиректа слишком длинный.'
            );
        }

        foreach (self::RESERVED_SOURCE_PREFIXES as $prefix) {
            if (
                $path === $prefix
                || str_starts_with(
                    $path,
                    $prefix . '/',
                )
            ) {
                throw new InvalidArgumentException(
                    'Системный путь нельзя использовать как источник редиректа.'
                );
            }
        }

        return $path;
    }

    private static function targetPath(
        string $value,
    ): string {
        $value = trim($value);

        if (
            $value === ''
            || !str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $value,
            ) === 1
            || str_contains($value, '#')
        ) {
            throw new InvalidArgumentException(
                'Цель редиректа должна быть локальным путём без fragment.'
            );
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            throw new InvalidArgumentException(
                'Целевой URL заполнен некорректно.'
            );
        }

        if (
            isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            throw new InvalidArgumentException(
                'Внешние цели редиректа запрещены.'
            );
        }

        $path = self::canonicalPath(
            (string) ($parts['path'] ?? '/'),
        );
        $query = isset($parts['query'])
            && $parts['query'] !== ''
                ? '?' . $parts['query']
                : '';

        $result = $path . $query;

        if (strlen($result) > 2000) {
            throw new InvalidArgumentException(
                'Целевой путь редиректа слишком длинный.'
            );
        }

        return $result;
    }

    private static function targetSourcePath(
        string $targetPath,
    ): string {
        $path = parse_url(
            $targetPath,
            PHP_URL_PATH,
        );

        return self::canonicalPath(
            is_string($path) ? $path : '/',
        );
    }

    private static function canonicalPath(
        string $path,
    ): string {
        if ($path === '/') {
            return '/';
        }

        $segments = explode(
            '/',
            trim($path, '/'),
        );
        $encoded = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $decoded = rawurldecode($segment);

            if (
                $decoded === '.'
                || $decoded === '..'
                || str_contains($decoded, '/')
                || str_contains($decoded, "\\")
                || preg_match(
                    '/[\x00-\x1F\x7F]/',
                    $decoded,
                ) === 1
            ) {
                throw new InvalidArgumentException(
                    'Путь редиректа содержит небезопасный сегмент.'
                );
            }

            $encoded[] = rawurlencode($decoded);
        }

        return $encoded === []
            ? '/'
            : '/' . implode('/', $encoded);
    }

    private static function statusCode(int $statusCode): void
    {
        if (!in_array(
            $statusCode,
            self::ALLOWED_STATUS,
            true,
        )) {
            throw new InvalidArgumentException(
                'Разрешены только HTTP 301, 302, 307 и 308.'
            );
        }
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
            throw new InvalidArgumentException(
                'Некорректный site key редиректа.'
            );
        }

        return $siteKey;
    }
}
