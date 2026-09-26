<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class SecretVault
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Cannot encrypt an empty secret.');
        }

        $key = self::key();
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength <= 0) {
            throw new RuntimeException('Unable to determine encryption IV length.');
        }

        $iv = random_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16,
        );

        if (!is_string($ciphertext) || $ciphertext === '') {
            throw new RuntimeException('Unable to encrypt secret.');
        }

        return base64_encode(json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($ciphertext),
        ], JSON_THROW_ON_ERROR));
    }

    public static function decrypt(string $encoded): string
    {
        $json = base64_decode($encoded, true);
        if (!is_string($json)) {
            throw new RuntimeException('Invalid encrypted secret.');
        }

        $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || ($payload['v'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported encrypted secret format.');
        }

        $iv = base64_decode((string) ($payload['iv'] ?? ''), true);
        $tag = base64_decode((string) ($payload['tag'] ?? ''), true);
        $ciphertext = base64_decode((string) ($payload['data'] ?? ''), true);

        if (!is_string($iv) || !is_string($tag) || !is_string($ciphertext)) {
            throw new RuntimeException('Invalid encrypted secret payload.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (!is_string($plaintext)) {
            throw new RuntimeException('Unable to decrypt secret.');
        }

        return $plaintext;
    }

    private static function key(): string
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('OpenSSL extension is required for secret storage.');
        }

        $encoded = trim((string) Config::get('security.secret_key', ''));
        $key = base64_decode($encoded, true);

        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException(
                'security.secret_key must be a base64-encoded 32-byte value.'
            );
        }

        return $key;
    }
}
