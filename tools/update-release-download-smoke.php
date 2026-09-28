<?php

declare(strict_types=1);

use ChurchCMS\Core\UpdatePackageSignatureVerifier;
use ChurchCMS\Core\UpdatePackageStager;
use ChurchCMS\Core\UpdateReleaseDownloader;
use ChurchCMS\Core\UpdateReleaseTransport;

$root = dirname(__DIR__);
require $root . '/core.php';

if (!function_exists('openssl_pkey_new')) {
    fwrite(STDERR, "Для smoke загрузки обновлений требуется OpenSSL.\n");
    exit(1);
}

$staging = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'churchcms-release-download-smoke-'
    . bin2hex(random_bytes(5));

$key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
if ($key === false) {
    fwrite(STDERR, "Не удалось создать тестовую пару ключей.\n");
    exit(1);
}

$details = openssl_pkey_get_details($key);
if (!is_array($details) || !is_string($details['key'] ?? null)) {
    fwrite(STDERR, "Не удалось получить тестовый публичный ключ.\n");
    exit(1);
}

$publicKey = $details['key'];
$keyId = 'ci-download-key';
$verifier = new UpdatePackageSignatureVerifier([
    $keyId => $publicKey,
]);

$payload = (string) file_get_contents($root . '/core/Uuid.php');
$manifestUrl = 'https://updates.example.test/releases/current/manifest.json';
$fileUrl = 'https://updates.example.test/releases/current/core/Uuid.php';

$manifest = [
    'format' => 'churchcms-update-v1',
    'from_version' => (string) \ChurchCMS\Core\Config::get(
        'app.version',
        '',
    ),
    'version' => '999.3.0-ci',
    'php_min' => '8.3',
    'files' => [[
        'path' => 'core/Uuid.php',
        'bytes' => strlen($payload),
        'sha256' => hash('sha256', $payload),
    ]],
    'deleted_files' => [],
];

$signature = '';
if (!openssl_sign(
    UpdatePackageSignatureVerifier::payload($manifest),
    $signature,
    $key,
    OPENSSL_ALGO_SHA256,
)) {
    fwrite(STDERR, "Не удалось подписать тестовый manifest.json.\n");
    exit(1);
}

$manifest['signature'] = [
    'algorithm' => 'openssl-sha256',
    'key_id' => $keyId,
    'value' => base64_encode($signature),
];

$manifestRaw = json_encode(
    $manifest,
    JSON_THROW_ON_ERROR
    | JSON_PRETTY_PRINT
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES,
) . PHP_EOL;

$transport = new class([
    $manifestUrl => $manifestRaw,
    $fileUrl => $payload,
]) implements UpdateReleaseTransport {
    /** @param array<string,string> $responses */
    public function __construct(private array $responses)
    {
    }

    /** @var list<string> */
    public array $requested = [];

    public function get(
        string $url,
        int $limit,
        callable $consumer,
    ): void {
        $this->requested[] = $url;
        $body = $this->responses[$url] ?? null;

        if (!is_string($body)) {
            throw new RuntimeException(
                'Smoke transport: неожиданный URL ' . $url
            );
        }

        if (strlen($body) > $limit) {
            throw new RuntimeException(
                'Smoke transport: превышен лимит ответа.'
            );
        }

        $consumer($body);
    }
};

try {
    $downloader = new UpdateReleaseDownloader(
        $root,
        $staging,
        $transport,
        $verifier,
    );
    $stage = $downloader->downloadAndStage($manifestUrl);

    $inspected = (new UpdatePackageStager(
        $root,
        $staging,
        $verifier,
    ))->inspect($stage['id']);

    if (
        ($stage['version'] ?? null) !== '999.3.0-ci'
        || ($stage['signature_key_id'] ?? null) !== $keyId
        || ($inspected['signature_key_id'] ?? null) !== $keyId
        || ($inspected['files']['core/Uuid.php']['sha256'] ?? null)
            !== hash('sha256', $payload)
        || $transport->requested !== [$manifestUrl, $fileUrl]
    ) {
        throw new RuntimeException(
            'Успешная загрузка подписанного пакета дала неверный результат.'
        );
    }

    $tamperedTransport = new class([
        $manifestUrl => $manifestRaw,
        $fileUrl => $payload . "\nповреждение",
    ]) implements UpdateReleaseTransport {
        /** @param array<string,string> $responses */
        public function __construct(private array $responses)
        {
        }

        public function get(
            string $url,
            int $limit,
            callable $consumer,
        ): void {
            $body = $this->responses[$url] ?? null;
            if (!is_string($body)) {
                throw new RuntimeException('Нет smoke-ответа.');
            }

            if (strlen($body) > $limit) {
                throw new RuntimeException(
                    'Smoke transport: превышен лимит ответа.'
                );
            }

            $consumer($body);
        }
    };

    try {
        (new UpdateReleaseDownloader(
            $root,
            $staging,
            $tamperedTransport,
            $verifier,
        ))->downloadAndStage($manifestUrl);

        throw new RuntimeException(
            'Повреждённый payload ошибочно принят.'
        );
    } catch (RuntimeException $error) {
        if (
            $error->getMessage()
            === 'Повреждённый payload ошибочно принят.'
        ) {
            throw $error;
        }
    }

    $unsafe = $manifest;
    $unsafe['files'] = [[
        'path' => '../config/local.php',
        'bytes' => 1,
        'sha256' => str_repeat('0', 64),
    ]];
    unset($unsafe['signature']);

    $unsafeSignature = '';
    openssl_sign(
        UpdatePackageSignatureVerifier::payload($unsafe),
        $unsafeSignature,
        $key,
        OPENSSL_ALGO_SHA256,
    );
    $unsafe['signature'] = [
        'algorithm' => 'openssl-sha256',
        'key_id' => $keyId,
        'value' => base64_encode($unsafeSignature),
    ];

    $unsafeRaw = json_encode(
        $unsafe,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
    );

    $unsafeTransport = new class(
        $manifestUrl,
        $unsafeRaw,
    ) implements UpdateReleaseTransport {
        public int $requests = 0;

        public function __construct(
            private string $manifestUrl,
            private string $manifest,
        ) {
        }

        public function get(
            string $url,
            int $limit,
            callable $consumer,
        ): void {
            $this->requests++;

            if ($url !== $this->manifestUrl) {
                throw new RuntimeException(
                    'Небезопасный payload не должен скачиваться.'
                );
            }

            $consumer($this->manifest);
        }
    };

    try {
        (new UpdateReleaseDownloader(
            $root,
            $staging,
            $unsafeTransport,
            $verifier,
        ))->downloadAndStage($manifestUrl);

        throw new RuntimeException(
            'Небезопасный путь ошибочно принят.'
        );
    } catch (RuntimeException $error) {
        if (
            $error->getMessage()
            === 'Небезопасный путь ошибочно принят.'
        ) {
            throw $error;
        }
    }

    if ($unsafeTransport->requests !== 1) {
        throw new RuntimeException(
            'После небезопасного manifest.json началась загрузка payload.'
        );
    }

    echo "Smoke сетевой загрузки подписанного обновления пройден\n";
} finally {
    deleteTree($staging);
}

function deleteTree(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }

    if (!is_dir($path)) {
        return;
    }

    foreach (new DirectoryIterator($path) as $entry) {
        if ($entry->isDot()) {
            continue;
        }

        deleteTree($entry->getPathname());
    }

    @rmdir($path);
}
