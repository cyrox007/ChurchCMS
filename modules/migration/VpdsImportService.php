<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Migration;

use InvalidArgumentException;
use Throwable;

final class VpdsImportService
{
    public function __construct(
        private readonly VpdsImportPort $port,
    ) {
    }

    /**
     * @param array<string,mixed> $manifest
     */
    public function import(
        int $organizationId,
        array $manifest,
        string $mediaRoot,
        bool $dryRun = false,
    ): VpdsImportReport {
        if ($organizationId <= 0) {
            throw new InvalidArgumentException('organizationId должен быть положительным.');
        }

        $source = $manifest['source'] ?? [];
        $sourceSite = trim((string) ($source['site_id'] ?? ''));
        if ($sourceSite === '') {
            throw new InvalidArgumentException('В manifest отсутствует source.site_id.');
        }

        $report = new VpdsImportReport($dryRun, $sourceSite, $organizationId);
        $userMap = $this->importUsers($organizationId, $manifest, $report, $dryRun);
        $urlMap = $this->buildUrlMap($manifest);

        $this->importMedia(
            $organizationId,
            $sourceSite,
            $manifest,
            $mediaRoot,
            $report,
            $dryRun,
        );
        $this->importContent(
            $organizationId,
            $sourceSite,
            $manifest,
            $userMap,
            $urlMap,
            $report,
            $dryRun,
        );
        $this->importRedirects(
            $organizationId,
            $manifest,
            $report,
            $dryRun,
        );

        return $report;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,string>
     */
    private function importUsers(
        int $organizationId,
        array $manifest,
        VpdsImportReport $report,
        bool $dryRun,
    ): array {
        $map = [];
        $emails = [];

        foreach ($manifest['users'] ?? [] as $user) {
            if (!is_array($user)) {
                $report->error('vpds_user_invalid', 'Запись пользователя VPDS имеет неверный формат.');
                continue;
            }

            $sourceId = trim((string) ($user['id'] ?? ''));
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($sourceId === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $report->increment('users_unmapped');
                $report->warning(
                    'vpds_user_unmapped',
                    'Пользователь VPDS не сопоставлен: отсутствует безопасный id или email.',
                    ['source_id' => $sourceId, 'email' => $email],
                );
                continue;
            }

            if (isset($emails[$email])) {
                $report->increment('users_unmapped');
                $report->warning(
                    'vpds_user_duplicate_email',
                    'Повторяющийся email пользователя VPDS пропущен.',
                    ['source_id' => $sourceId, 'email' => $email],
                );
                continue;
            }

            $emails[$email] = true;
            $user['email'] = $email;
            unset($user['password'], $user['password_hash'], $user['hash']);

            try {
                $targetId = $this->port->mapUser($organizationId, $user, $dryRun);
            } catch (Throwable $error) {
                $targetId = null;
                $report->error(
                    'vpds_user_mapping_failed',
                    'Не удалось сопоставить пользователя VPDS.',
                    ['source_id' => $sourceId, 'reason' => $error->getMessage()],
                );
            }

            if ($targetId === null || trim($targetId) === '') {
                $report->increment('users_unmapped');
                $report->warning(
                    'vpds_user_unmapped',
                    'Для пользователя VPDS не найден безопасный пользователь ChurchCMS.',
                    ['source_id' => $sourceId, 'email' => $email],
                );
                continue;
            }

            $map[$sourceId] = $targetId;
            $report->increment('users_mapped');
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private function importMedia(
        int $organizationId,
        string $sourceSite,
        array $manifest,
        string $mediaRoot,
        VpdsImportReport $report,
        bool $dryRun,
    ): void {
        $resolvedRoot = realpath($mediaRoot);

        foreach ($manifest['media'] ?? [] as $media) {
            if (!is_array($media)) {
                $report->error('vpds_media_invalid', 'Запись Media VPDS имеет неверный формат.');
                continue;
            }

            $sourceId = trim((string) ($media['id'] ?? ''));
            $relativePath = trim((string) ($media['path'] ?? ''));
            $expectedHash = strtolower(trim((string) ($media['sha256'] ?? '')));

            if (
                $sourceId === ''
                || $relativePath === ''
                || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1
            ) {
                $report->error(
                    'vpds_media_invalid',
                    'Media VPDS не содержит обязательные id, path или SHA-256.',
                    ['source_id' => $sourceId],
                );
                continue;
            }

            $checksum = $this->entityChecksum($media);
            if ($this->alreadyImported($organizationId, $sourceSite, 'media', $sourceId, $checksum)) {
                $report->increment('media_skipped');
                continue;
            }

            $sourcePath = $this->safeMediaPath($resolvedRoot, $relativePath);
            if ($sourcePath === null || !is_file($sourcePath) || !is_readable($sourcePath)) {
                $report->increment('media_missing');
                $report->error(
                    'vpds_media_missing',
                    'Media-файл VPDS не найден или путь небезопасен.',
                    ['source_id' => $sourceId, 'path' => $relativePath],
                );
                continue;
            }

            $actualHash = hash_file('sha256', $sourcePath);
            if (!is_string($actualHash) || !hash_equals($expectedHash, strtolower($actualHash))) {
                $report->increment('media_checksum_failed');
                $report->error(
                    'vpds_media_checksum_failed',
                    'Контрольная сумма Media VPDS не совпадает.',
                    ['source_id' => $sourceId, 'path' => $relativePath],
                );
                continue;
            }

            if (isset($media['bytes'])) {
                $expectedBytes = (int) $media['bytes'];
                $actualBytes = filesize($sourcePath);
                if ($expectedBytes <= 0 || !is_int($actualBytes) || $expectedBytes !== $actualBytes) {
                    $report->error(
                        'vpds_media_size_failed',
                        'Размер Media VPDS не совпадает с manifest.',
                        ['source_id' => $sourceId, 'path' => $relativePath],
                    );
                    continue;
                }
            }

            try {
                $targetId = $this->port->importMedia(
                    $organizationId,
                    $media,
                    $sourcePath,
                    $dryRun,
                );
                $this->port->rememberImport(
                    $organizationId,
                    $sourceSite,
                    'media',
                    $sourceId,
                    $checksum,
                    $targetId,
                    $dryRun,
                );
                $report->increment('media_imported_or_reused');
            } catch (Throwable $error) {
                $report->error(
                    'vpds_media_import_failed',
                    'Не удалось импортировать Media VPDS.',
                    ['source_id' => $sourceId, 'reason' => $error->getMessage()],
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $manifest
     * @param array<string,string> $userMap
     * @param array<string,string> $urlMap
     */
    private function importContent(
        int $organizationId,
        string $sourceSite,
        array $manifest,
        array $userMap,
        array $urlMap,
        VpdsImportReport $report,
        bool $dryRun,
    ): void {
        foreach ($manifest['content'] ?? [] as $content) {
            if (!is_array($content)) {
                $report->error('vpds_content_invalid', 'Запись контента VPDS имеет неверный формат.');
                continue;
            }

            $sourceId = trim((string) ($content['id'] ?? ''));
            $title = trim((string) ($content['title'] ?? ''));
            if ($sourceId === '' || $title === '') {
                $report->error(
                    'vpds_content_invalid',
                    'Контент VPDS не содержит обязательные id или title.',
                    ['source_id' => $sourceId],
                );
                continue;
            }

            $checksum = $this->entityChecksum($content);
            if ($this->alreadyImported($organizationId, $sourceSite, 'content', $sourceId, $checksum)) {
                $report->increment('content_skipped');
                continue;
            }

            $authorSourceId = trim((string) ($content['author_id'] ?? ''));
            $content['author_target_id'] = $userMap[$authorSourceId] ?? null;
            if ($authorSourceId !== '' && $content['author_target_id'] === null) {
                $report->warning(
                    'vpds_content_author_unmapped',
                    'Автор материала VPDS не сопоставлен; материал будет импортирован без старого пароля/учётной записи.',
                    ['source_id' => $sourceId, 'author_id' => $authorSourceId],
                );
            }

            foreach (['body', 'excerpt'] as $field) {
                if (!isset($content[$field]) || !is_string($content[$field])) {
                    continue;
                }

                [$rewritten, $unresolved] = $this->rewriteLinks($content[$field], $urlMap);
                $content[$field] = $rewritten;
                foreach ($unresolved as $url) {
                    $report->increment('unresolved_links');
                    $report->warning(
                        'vpds_unresolved_link',
                        'В материале осталась несопоставленная локальная ссылка VPDS.',
                        ['source_id' => $sourceId, 'url' => $url],
                    );
                }
            }

            try {
                $targetId = $this->port->upsertContent($organizationId, $content, $dryRun);
                $this->port->rememberImport(
                    $organizationId,
                    $sourceSite,
                    'content',
                    $sourceId,
                    $checksum,
                    $targetId,
                    $dryRun,
                );
                $report->increment('content_created_or_updated');
            } catch (Throwable $error) {
                $report->error(
                    'vpds_content_import_failed',
                    'Не удалось импортировать материал VPDS.',
                    ['source_id' => $sourceId, 'reason' => $error->getMessage()],
                );
            }
        }
    }

    /** @param array<string,mixed> $manifest */
    private function importRedirects(
        int $organizationId,
        array $manifest,
        VpdsImportReport $report,
        bool $dryRun,
    ): void {
        $redirects = [];
        foreach ($manifest['redirects'] ?? [] as $redirect) {
            if (!is_array($redirect)) {
                $report->increment('redirects_rejected');
                $report->warning('vpds_redirect_invalid', 'Некорректная запись redirect VPDS пропущена.');
                continue;
            }

            $from = $this->normalizeLocalPath((string) ($redirect['from'] ?? ''));
            $to = $this->normalizeLocalPath((string) ($redirect['to'] ?? ''));
            if ($from === null || $to === null || $from === $to) {
                $report->increment('redirects_rejected');
                $report->warning(
                    'vpds_redirect_invalid',
                    'Небезопасный или циклический redirect VPDS пропущен.',
                    ['from' => $redirect['from'] ?? null, 'to' => $redirect['to'] ?? null],
                );
                continue;
            }

            $redirects[$from] = $to;
        }

        foreach ($redirects as $from => $to) {
            if ($this->redirectHasCycle($from, $redirects)) {
                $report->increment('redirects_rejected');
                $report->warning(
                    'vpds_redirect_cycle',
                    'Цепочка redirect VPDS образует цикл и пропущена.',
                    ['from' => $from, 'to' => $to],
                );
                continue;
            }

            try {
                $this->port->saveRedirect($organizationId, $from, $to, $dryRun);
                $report->increment('redirects_saved');
            } catch (Throwable $error) {
                $report->error(
                    'vpds_redirect_failed',
                    'Не удалось сохранить redirect VPDS.',
                    ['from' => $from, 'to' => $to, 'reason' => $error->getMessage()],
                );
            }
        }
    }

    /** @param array<string,mixed> $manifest @return array<string,string> */
    private function buildUrlMap(array $manifest): array
    {
        $map = [];
        foreach ($manifest['redirects'] ?? [] as $redirect) {
            if (!is_array($redirect)) {
                continue;
            }

            $from = $this->normalizeLocalPath((string) ($redirect['from'] ?? ''));
            $to = $this->normalizeLocalPath((string) ($redirect['to'] ?? ''));
            if ($from !== null && $to !== null && $from !== $to) {
                $map[$from] = $to;
            }
        }

        return $map;
    }

    /** @param array<string,mixed> $value */
    private function entityChecksum(array $value): string
    {
        $this->sortRecursive($value);
        return hash('sha256', json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /** @param array<string,mixed> $value */
    private function sortRecursive(array &$value): void
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
        unset($item);
    }

    private function alreadyImported(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
        string $checksum,
    ): bool {
        return hash_equals(
            $checksum,
            $this->port->importedChecksum(
                $organizationId,
                $sourceSite,
                $entityType,
                $sourceId,
            ) ?? '',
        );
    }

    private function safeMediaPath(?string $root, string $relativePath): ?string
    {
        if ($root === null || str_contains($relativePath, "\0")) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($relativePath));
        if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return null;
        }

        $parts = array_values(array_filter(explode('/', $normalized), static fn (string $part): bool => $part !== ''));
        if (in_array('..', $parts, true)) {
            return null;
        }

        $candidate = realpath($root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
        if ($candidate === false) {
            return null;
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($candidate !== $root && !str_starts_with($candidate, $prefix)) {
            return null;
        }

        return $candidate;
    }

    private function normalizeLocalPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1 || str_starts_with($path, '//')) {
            return null;
        }

        $parsed = parse_url($path);
        if ($parsed === false || isset($parsed['scheme']) || isset($parsed['host'])) {
            return null;
        }

        $normalized = '/' . ltrim((string) ($parsed['path'] ?? '/'), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;
        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    /** @param array<string,string> $redirects */
    private function redirectHasCycle(string $start, array $redirects): bool
    {
        $seen = [];
        $current = $start;
        while (isset($redirects[$current])) {
            if (isset($seen[$current])) {
                return true;
            }

            $seen[$current] = true;
            $current = $redirects[$current];
        }

        return false;
    }

    /**
     * @param array<string,string> $urlMap
     * @return array{0:string,1:list<string>}
     */
    private function rewriteLinks(string $html, array $urlMap): array
    {
        $unresolved = [];
        $rewritten = preg_replace_callback(
            '/\b(href|src)=([' . "\"'" . '])([^' . "\"'" . ']+)\2/i',
            function (array $match) use ($urlMap, &$unresolved): string {
                $url = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $path = $this->normalizeLocalPath($url);
                if ($path === null) {
                    return $match[0];
                }

                if (isset($urlMap[$path])) {
                    return $match[1] . '=' . $match[2] . $urlMap[$path] . $match[2];
                }

                if (str_starts_with($path, '/')) {
                    $unresolved[] = $path;
                }

                return $match[0];
            },
            $html,
        );

        return [$rewritten ?? $html, array_values(array_unique($unresolved))];
    }
}