#!/usr/bin/env php
<?php

declare(strict_types=1);

use ChurchCMS\Core\UpdatePackageSignatureVerifier;

$root = dirname(__DIR__);
require $root . '/core.php';

$manifestArgument = trim((string) ($argv[1] ?? ''));
$privateKeyArgument = trim((string) ($argv[2] ?? ''));
$keyId = trim((string) ($argv[3] ?? ''));

if (
    $manifestArgument === ''
    || $privateKeyArgument === ''
    || preg_match(
        '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D',
        $keyId,
    ) !== 1
) {
    fwrite(
        STDERR,
        "Использование: php tools/update-sign.php "
        . "/абсолютный/путь/manifest.json "
        . "/абсолютный/путь/private-key.pem <key-id>\n",
    );
    exit(2);
}

try {
    $manifestPath = safeFile(
        $manifestArgument,
        'манифест обновления',
    );
    $privateKeyPath = safeFile(
        $privateKeyArgument,
        'приватный ключ',
    );

    $repositoryRoot = realpath($root);
    if (
        $repositoryRoot !== false
        && pathInside($privateKeyPath, $repositoryRoot)
    ) {
        throw new RuntimeException(
            'Приватный ключ подписи нельзя хранить внутри репозитория ChurchCMS.'
        );
    }

    if (
        !function_exists('openssl_sign')
        || !function_exists('openssl_pkey_get_private')
    ) {
        throw new RuntimeException(
            'Для подписи релизного пакета требуется расширение OpenSSL.'
        );
    }

    $rawManifest = file_get_contents($manifestPath);
    if (!is_string($rawManifest)) {
        throw new RuntimeException(
            'Не удалось прочитать манифест обновления.'
        );
    }

    $manifest = json_decode(
        $rawManifest,
        true,
        64,
        JSON_THROW_ON_ERROR,
    );
    if (
        !is_array($manifest)
        || ($manifest['format'] ?? null)
            !== 'churchcms-update-v1'
    ) {
        throw new RuntimeException(
            'Формат манифеста обновления не поддерживается.'
        );
    }

    $privateKeyPem = file_get_contents($privateKeyPath);
    if (!is_string($privateKeyPem)) {
        throw new RuntimeException(
            'Не удалось прочитать приватный ключ подписи.'
        );
    }

    $passphrase = (string) (
        getenv('CHURCHCMS_UPDATE_SIGNING_PASSPHRASE')
        ?: ''
    );
    $privateKey = openssl_pkey_get_private(
        $privateKeyPem,
        $passphrase !== '' ? $passphrase : null,
    );
    if ($privateKey === false) {
        throw new RuntimeException(
            'Приватный ключ подписи не удалось открыть.'
        );
    }

    $signature = '';
    $signed = openssl_sign(
        UpdatePackageSignatureVerifier::payload($manifest),
        $signature,
        $privateKey,
        OPENSSL_ALGO_SHA256,
    );
    if ($signed !== true || $signature === '') {
        throw new RuntimeException(
            'Не удалось сформировать цифровую подпись манифеста.'
        );
    }

    $manifest['signature'] = [
        'algorithm' => 'openssl-sha256',
        'key_id' => $keyId,
        'value' => base64_encode($signature),
    ];

    $encoded = json_encode(
        $manifest,
        JSON_THROW_ON_ERROR
        | JSON_PRETTY_PRINT
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES,
    ) . PHP_EOL;

    $temporary = $manifestPath
        . '.churchcms-sign-'
        . bin2hex(random_bytes(6));

    if (file_put_contents($temporary, $encoded, LOCK_EX) === false) {
        throw new RuntimeException(
            'Не удалось записать подписанный манифест.'
        );
    }

    @chmod($temporary, 0600);

    if (!rename($temporary, $manifestPath)) {
        @unlink($temporary);
        throw new RuntimeException(
            'Не удалось атомарно заменить манифест подписью.'
        );
    }

    echo sprintf(
        "Манифест подписан доверенным ключом %s.\n",
        $keyId,
    );
    exit(0);
} catch (Throwable $error) {
    fwrite(
        STDERR,
        "Подпись обновления не выполнена: "
        . $error->getMessage()
        . "\n",
    );
    exit(1);
}

function safeFile(
    string $path,
    string $label,
): string {
    if (!isAbsolutePath($path) || is_link($path)) {
        throw new RuntimeException(
            ucfirst($label)
            . ' должен быть обычным файлом по абсолютному пути.'
        );
    }

    $resolved = realpath($path);
    if (
        $resolved === false
        || !is_file($resolved)
        || is_link($resolved)
    ) {
        throw new RuntimeException(
            ucfirst($label) . ' не найден или небезопасен.'
        );
    }

    return $resolved;
}

function isAbsolutePath(string $path): bool
{
    if ($path === '') {
        return false;
    }

    if ($path[0] === '/' || $path[0] === '\\') {
        return true;
    }

    return preg_match(
        '/^[A-Za-z]:[\\\\\/]/D',
        $path,
    ) === 1;
}

function pathInside(
    string $path,
    string $parent,
): bool {
    $path = rtrim(
        str_replace('\\', '/', $path),
        '/',
    );
    $parent = rtrim(
        str_replace('\\', '/', $parent),
        '/',
    );

    if (DIRECTORY_SEPARATOR === '\\') {
        $path = strtolower($path);
        $parent = strtolower($parent);
    }

    return $path === $parent
        || str_starts_with(
            $path . '/',
            $parent . '/',
        );
}
