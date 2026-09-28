<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use JsonException;
use RuntimeException;

final class UpdatePackageTrustVerifier
{
    private const FORMAT = 'churchcms-update-signature-v1';

    /** @var array<string,string> */
    private array $trustedKeys;

    /**
     * @param array<string,string>|null $trustedKeys key_id => base64 public key
     */
    public function __construct(?array $trustedKeys = null)
    {
        $configured = $trustedKeys
            ?? Config::get('operations.update_trusted_keys', []);

        $this->trustedKeys = is_array($configured)
            ? $configured
            : [];
    }

    /**
     * Проверяет detached-подпись исходных байтов manifest.json.
     */
    public function verify(string $directory): string
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException(
                'Проверка подписи обновления недоступна: расширение Sodium не загружено.'
            );
        }

        $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
        $signaturePath = $directory . DIRECTORY_SEPARATOR . 'manifest.sig';

        if (
            !is_file($manifestPath)
            || is_link($manifestPath)
            || !is_file($signaturePath)
            || is_link($signaturePath)
        ) {
            throw new RuntimeException(
                'Для доверенного пакета нужны безопасные manifest.json и manifest.sig.'
            );
        }

        $manifest = file_get_contents($manifestPath);
        $signatureRaw = file_get_contents($signaturePath);

        if (!is_string($manifest) || !is_string($signatureRaw)) {
            throw new RuntimeException(
                'Не удалось прочитать данные подписи пакета обновления.'
            );
        }

        try {
            $metadata = json_decode(
                $signatureRaw,
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Файл подписи пакета обновления повреждён.',
                0,
                $error,
            );
        }

        if (
            !is_array($metadata)
            || ($metadata['format'] ?? null) !== self::FORMAT
            || ($metadata['algorithm'] ?? null) !== 'ed25519'
        ) {
            throw new RuntimeException(
                'Формат подписи пакета обновления не поддерживается.'
            );
        }

        $keyId = trim((string) ($metadata['key_id'] ?? ''));
        $signatureBase64 = trim((string) ($metadata['signature'] ?? ''));
        $publicKeyBase64 = $this->trustedKeys[$keyId] ?? null;

        if (
            $keyId === ''
            || !is_string($publicKeyBase64)
            || trim($publicKeyBase64) === ''
        ) {
            throw new RuntimeException(
                'Пакет подписан неизвестным ключом обновлений.'
            );
        }

        $signature = base64_decode($signatureBase64, true);
        $publicKey = base64_decode(trim($publicKeyBase64), true);

        if (
            !is_string($signature)
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || !is_string($publicKey)
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        ) {
            throw new RuntimeException(
                'Ключ или подпись пакета обновления имеют некорректный формат.'
            );
        }

        if (!sodium_crypto_sign_verify_detached(
            $signature,
            $manifest,
            $publicKey,
        )) {
            throw new RuntimeException(
                'Подпись пакета обновления не прошла проверку.'
            );
        }

        return $keyId;
    }
}
