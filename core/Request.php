<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use JsonException;

final class Request
{
    private const MAX_JSON_BYTES = 1048576;

    private array $json = [];
    private ?string $jsonError = null;
    private array $attributes = [];

    public function __construct(
        private readonly array $get = [],
        private readonly array $post = [],
        private readonly array $files = [],
        private readonly array $server = [],
    ) {
        $this->parseJson();
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $default;
    }

    public function files(string $key, mixed $default = null): mixed
    {
        return $this->files[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $normalized = strtoupper(str_replace('-', '_', $name));
        $key = in_array($normalized, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
            ? $normalized
            : 'HTTP_' . $normalized;

        return $this->server[$key] ?? $default;
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '');
        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function hasInvalidJson(): bool
    {
        return $this->jsonError !== null;
    }

    public function jsonError(): ?string
    {
        return $this->jsonError;
    }

    private function parseJson(): void
    {
        $contentType = strtolower(trim((string) ($this->server['CONTENT_TYPE'] ?? '')));
        if (!str_starts_with($contentType, 'application/json')) {
            return;
        }

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_JSON_BYTES + 1);
        if ($raw === false || strlen($raw) > self::MAX_JSON_BYTES) {
            $this->jsonError = 'json_body_unreadable_or_too_large';
            return;
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            $this->json = is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            $this->jsonError = 'json_body_invalid';
        }
    }
}
