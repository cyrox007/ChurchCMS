<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Migration;

use InvalidArgumentException;
use JsonException;

final class VpdsManifestReader
{
    /** @return array<string,mixed> */
    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Manifest VPDS недоступен для чтения.');
        }

        $json = file_get_contents($path);
        if (!is_string($json)) {
            throw new InvalidArgumentException('Не удалось прочитать manifest VPDS.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException(
                'Manifest VPDS содержит некорректный JSON: ' . $error->getMessage(),
                0,
                $error,
            );
        }

        if (!is_array($manifest)) {
            throw new InvalidArgumentException('Корень manifest VPDS должен быть объектом.');
        }

        if (($manifest['format'] ?? null) !== 'churchcms-vpds-export') {
            throw new InvalidArgumentException('Неизвестный формат manifest VPDS.');
        }

        if (($manifest['version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Поддерживается только версия 1 manifest VPDS.');
        }

        $source = $manifest['source'] ?? null;
        if (!is_array($source) || trim((string) ($source['site_id'] ?? '')) === '') {
            throw new InvalidArgumentException('В manifest VPDS отсутствует source.site_id.');
        }

        foreach (['users', 'content', 'media', 'redirects'] as $section) {
            if (!isset($manifest[$section])) {
                $manifest[$section] = [];
            }

            if (!is_array($manifest[$section]) || !array_is_list($manifest[$section])) {
                throw new InvalidArgumentException(
                    sprintf('Раздел %s в manifest VPDS должен быть списком.', $section),
                );
            }
        }

        return $manifest;
    }
}