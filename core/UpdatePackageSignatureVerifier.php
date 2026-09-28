<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use JsonException;
use RuntimeException;

final class UpdatePackageSignatureVerifier
{
    private const ALGORITHM = 'openssl-sha256';
    private const KEY_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';
    private const MAX_SIGNATURE_BYTES = 16384;

    /** @var array<string,mixed>|null */
    private ?array $trustedKeys;

    /**
     * @param array<string,mixed>|null $trustedKeys
     */
    public function __construct(?array $trustedKeys = null)
    {
        $this->trustedKeys = $trustedKeys;
    }

    /**
     * Проверяет происхождение манифеста по закреплённому публичному ключу.
     *
     * @param array<string,mixed> $manifest
     * @return array{algorithm:string,key_id:string}
     */
    public function verify(array $manifest): array
    {
        if (
            !function_exists('openssl_verify')
            || !function_exists('openssl_pkey_get_public')
        ) {
            throw new RuntimeException(
                'Для проверки подписи обновления требуется расширение OpenSSL.'
            );
        }

        $signature = $manifest['signature'] ?? null;
        if (!is_array($signature)) {
            throw new RuntimeException(
                'Манифест обновления не содержит цифровую подпись.'
            );
        }

        $algorithm = trim(
            (string) ($signature['algorithm'] ?? '')
        );
        $keyId = trim((string) ($signature['key_id'] ?? ''));
        $encoded = trim((string) ($signature['value'] ?? ''));

        if ($algorithm !== self::ALGORITHM) {
            throw new RuntimeException(
                'Алгоритм подписи пакета обновления не поддерживается.'
            );
        }

        if (preg_match(self::KEY_ID_PATTERN, $keyId) !== 1) {
            throw new RuntimeException(
                'Идентификатор ключа подписи пакета некорректен.'
            );
        }

        $rawSignature = base64_decode($encoded, true);
        if (
            !is_string($rawSignature)
            || $rawSignature === ''
            || strlen($rawSignature) > self::MAX_SIGNATURE_BYTES
        ) {
            throw new RuntimeException(
                'Цифровая подпись пакета обновления повреждена.'
            );
        }

        $trustedKeys = $this->trustedKeys
            ?? Config::get(
                'operations.update_trusted_public_keys',
                [],
            );
        if (!is_array($trustedKeys)) {
            throw new RuntimeException(
                'Список доверенных ключей обновления настроен некорректно.'
            );
        }

        $publicKeyPem = $trustedKeys[$keyId] ?? null;
        if (
            !is_string($publicKeyPem)
            || trim($publicKeyPem) === ''
        ) {
            throw new RuntimeException(
                'Пакет подписан неизвестным ключом обновления.'
            );
        }

        $publicKey = openssl_pkey_get_public($publicKeyPem);
        if ($publicKey === false) {
            throw new RuntimeException(
                'Доверенный публичный ключ обновления имеет неверный формат.'
            );
        }

        $verified = openssl_verify(
            self::payload($manifest),
            $rawSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256,
        );

        if ($verified !== 1) {
            throw new RuntimeException(
                'Цифровая подпись пакета обновления не подтверждена.'
            );
        }

        return [
            'algorithm' => $algorithm,
            'key_id' => $keyId,
        ];
    }

    /**
     * Возвращает однозначное представление подписываемого манифеста.
     *
     * Поле signature намеренно исключается: подпись покрывает все остальные
     * поля манифеста, включая версии, список файлов, удаления и SHA-256.
     *
     * @param array<string,mixed> $manifest
     */
    public static function payload(array $manifest): string
    {
        unset($manifest['signature']);

        try {
            return json_encode(
                self::canonicalize($manifest),
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $error) {
            throw new RuntimeException(
                'Манифест обновления нельзя подготовить к проверке подписи.',
                0,
                $error,
            );
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            if (
                $value === null
                || is_string($value)
                || is_int($value)
                || is_float($value)
                || is_bool($value)
            ) {
                return $value;
            }

            throw new RuntimeException(
                'Манифест обновления содержит неподдерживаемое значение.'
            );
        }

        if (array_is_list($value)) {
            return array_map(
                static fn(mixed $item): mixed =>
                    self::canonicalize($item),
                $value,
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new RuntimeException(
                    'Манифест обновления содержит некорректный ключ.'
                );
            }

            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
