<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/modules/migration/VpdsImportPort.php';
require_once $root . '/modules/migration/VpdsImportReport.php';
require_once $root . '/modules/migration/VpdsManifestReader.php';
require_once $root . '/modules/migration/VpdsImportService.php';

use ChurchCMS\Modules\Migration\VpdsImportPort;
use ChurchCMS\Modules\Migration\VpdsImportService;
use ChurchCMS\Modules\Migration\VpdsManifestReader;

function vpdsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class VpdsSmokePort implements VpdsImportPort
{
    /** @var array<string,string> */
    public array $checksums = [];
    /** @var list<array<string,mixed>> */
    public array $users = [];
    /** @var list<array<string,mixed>> */
    public array $content = [];
    /** @var list<array<string,mixed>> */
    public array $media = [];
    /** @var list<array{from:string,to:string,dry_run:bool}> */
    public array $redirects = [];

    public function importedChecksum(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
    ): ?string {
        return $this->checksums[$this->key(
            $organizationId,
            $sourceSite,
            $entityType,
            $sourceId,
        )] ?? null;
    }

    public function mapUser(
        int $organizationId,
        array $user,
        bool $dryRun,
    ): ?string {
        $this->users[] = $user + ['dry_run' => $dryRun];
        return ($user['email'] ?? '') === 'editor@example.test'
            ? '11111111-1111-4111-8111-111111111111'
            : null;
    }

    public function upsertContent(
        int $organizationId,
        array $content,
        bool $dryRun,
    ): string {
        $this->content[] = $content + ['dry_run' => $dryRun];
        return 'publication-' . (string) $content['id'];
    }

    public function importMedia(
        int $organizationId,
        array $media,
        string $sourcePath,
        bool $dryRun,
    ): string {
        $this->media[] = $media + [
            'source_path' => $sourcePath,
            'dry_run' => $dryRun,
        ];
        return 'media-' . (string) $media['id'];
    }

    public function saveRedirect(
        int $organizationId,
        string $from,
        string $to,
        bool $dryRun,
    ): void {
        $this->redirects[] = [
            'from' => $from,
            'to' => $to,
            'dry_run' => $dryRun,
        ];
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

        $this->checksums[$this->key(
            $organizationId,
            $sourceSite,
            $entityType,
            $sourceId,
        )] = $checksum;
    }

    private function key(
        int $organizationId,
        string $sourceSite,
        string $entityType,
        string $sourceId,
    ): string {
        return implode(':', [
            (string) $organizationId,
            $sourceSite,
            $entityType,
            $sourceId,
        ]);
    }
}

$temporary = sys_get_temp_dir() . '/churchcms-vpds-smoke-' . bin2hex(random_bytes(6));
$mediaRoot = $temporary . '/media';
if (!mkdir($mediaRoot, 0700, true) && !is_dir($mediaRoot)) {
    throw new RuntimeException('Не удалось создать временный каталог VPDS smoke.');
}

try {
    $mediaPath = $mediaRoot . '/photo.txt';
    file_put_contents($mediaPath, 'churchcms-vpds-media');
    $hash = hash_file('sha256', $mediaPath);
    $bytes = filesize($mediaPath);
    vpdsAssert(is_string($hash) && is_int($bytes), 'Не удалось подготовить тестовый Media-файл.');

    $manifest = [
        'format' => 'churchcms-vpds-export',
        'version' => 1,
        'source' => [
            'site_id' => 'legacy-parish',
        ],
        'users' => [
            [
                'id' => 'u-1',
                'email' => 'Editor@Example.Test',
                'name' => 'Редактор',
                'password_hash' => 'legacy-must-not-leak',
            ],
            [
                'id' => 'u-2',
                'email' => 'not-an-email',
            ],
        ],
        'content' => [
            [
                'id' => 'post-1',
                'type' => 'article',
                'status' => 'published',
                'slug' => 'first-post',
                'title' => 'Первый материал',
                'author_id' => 'u-1',
                'body' => '<p><a href="/old-post">Старая ссылка</a> <a href="/lost">Потерянная</a></p>',
                'excerpt' => '<a href="/old-post">Анонс</a>',
            ],
        ],
        'media' => [
            [
                'id' => 'media-1',
                'path' => 'photo.txt',
                'name' => 'photo.txt',
                'sha256' => $hash,
                'bytes' => $bytes,
            ],
        ],
        'redirects' => [
            ['from' => '/old-post', 'to' => '/news/first-post'],
            ['from' => '/cycle-a', 'to' => '/cycle-b'],
            ['from' => '/cycle-b', 'to' => '/cycle-a'],
        ],
    ];

    $manifestPath = $temporary . '/manifest.json';
    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );

    $reader = new VpdsManifestReader();
    $read = $reader->read($manifestPath);
    vpdsAssert(($read['source']['site_id'] ?? null) === 'legacy-parish', 'Reader потерял source.site_id.');

    $badPath = $temporary . '/bad.json';
    file_put_contents($badPath, '{bad json');
    try {
        $reader->read($badPath);
        throw new RuntimeException('Reader принял некорректный JSON.');
    } catch (InvalidArgumentException) {
    }

    $wrongPath = $temporary . '/wrong.json';
    $wrong = $manifest;
    $wrong['version'] = 2;
    file_put_contents($wrongPath, json_encode($wrong, JSON_THROW_ON_ERROR));
    try {
        $reader->read($wrongPath);
        throw new RuntimeException('Reader принял неподдерживаемую версию manifest.');
    } catch (InvalidArgumentException) {
    }

    $dryPort = new VpdsSmokePort();
    $dryReport = (new VpdsImportService($dryPort))->import(
        17,
        $read,
        $mediaRoot,
        true,
    )->toArray();

    vpdsAssert(($dryReport['dry_run'] ?? false) === true, 'Dry-run не отмечен в отчёте.');
    vpdsAssert(($dryReport['counters']['errors'] ?? -1) === 0, 'Dry-run вернул неожиданные ошибки.');
    vpdsAssert(count($dryPort->users) === 1, 'Невалидный пользователь не должен доходить до адаптера.');
    vpdsAssert(!array_key_exists('password_hash', $dryPort->users[0]), 'Legacy password_hash попал в адаптер.');
    vpdsAssert(($dryPort->content[0]['author_target_id'] ?? null) === '11111111-1111-4111-8111-111111111111', 'Автор не сопоставлен.');
    vpdsAssert(str_contains((string) $dryPort->content[0]['body'], 'href="/news/first-post"'), 'Старая ссылка не переписана.');
    vpdsAssert(($dryReport['counters']['unresolved_links'] ?? 0) === 1, 'Несопоставленная ссылка не попала в диагностику.');
    vpdsAssert(($dryReport['counters']['redirects_saved'] ?? 0) === 1, 'Безопасный redirect не принят.');
    vpdsAssert(($dryReport['counters']['redirects_rejected'] ?? 0) === 2, 'Циклические redirect не отклонены.');
    vpdsAssert($dryPort->checksums === [], 'Dry-run изменил состояние идемпотентности.');

    $port = new VpdsSmokePort();
    $service = new VpdsImportService($port);
    $first = $service->import(17, $read, $mediaRoot, false)->toArray();
    vpdsAssert(($first['success'] ?? false) === true, 'Первый импорт завершился ошибкой.');
    vpdsAssert(count($port->content) === 1, 'Материал не импортирован один раз.');
    vpdsAssert(count($port->media) === 1, 'Media не импортирован один раз.');
    vpdsAssert(count($port->checksums) === 2, 'Состояние идемпотентности не сохранено.');

    $second = $service->import(17, $read, $mediaRoot, false)->toArray();
    vpdsAssert(($second['counters']['content_skipped'] ?? 0) === 1, 'Повторный материал не пропущен.');
    vpdsAssert(($second['counters']['media_skipped'] ?? 0) === 1, 'Повторный Media не пропущен.');
    vpdsAssert(count($port->content) === 1, 'Повторный импорт создал лишнюю запись контента.');
    vpdsAssert(count($port->media) === 1, 'Повторный импорт создал лишнюю запись Media.');

    $changed = $read;
    $changed['content'][0]['title'] = 'Обновлённый материал';
    $third = $service->import(17, $changed, $mediaRoot, false)->toArray();
    vpdsAssert(($third['counters']['content_created_or_updated'] ?? 0) === 1, 'Изменённый материал не обновлён.');
    vpdsAssert(count($port->content) === 2, 'Изменённый материал не дошёл до адаптера.');
    vpdsAssert(($port->content[1]['title'] ?? '') === 'Обновлённый материал', 'Обновлённый заголовок потерян.');

    $broken = $read;
    $broken['media'][0]['sha256'] = str_repeat('0', 64);
    $brokenReport = (new VpdsImportService(new VpdsSmokePort()))
        ->import(17, $broken, $mediaRoot, true)
        ->toArray();
    vpdsAssert(($brokenReport['counters']['media_checksum_failed'] ?? 0) === 1, 'Ошибка SHA-256 Media не обнаружена.');
    vpdsAssert(($brokenReport['success'] ?? true) === false, 'Ошибка Media не повлияла на итог отчёта.');

    fwrite(STDOUT, "VPDS smoke: успешно.\n");
} finally {
    $files = glob($temporary . '/*') ?: [];
    foreach ($files as $file) {
        if (is_dir($file)) {
            foreach (glob($file . '/*') ?: [] as $nested) {
                @unlink($nested);
            }
            @rmdir($file);
            continue;
        }
        @unlink($file);
    }
    @rmdir($temporary);
}
