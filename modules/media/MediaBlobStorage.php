<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Media;

use ChurchCMS\Core\Config;
use InvalidArgumentException;
use RuntimeException;

final class MediaBlobStorage
{
    /**
     * MIME-типы первой безопасной версии.
     *
     * SVG/HTML и архивы намеренно не принимаются: активный или составной
     * контент требует отдельной политики обработки.
     *
     * @var array<string,string>
     */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'image/avif' => 'image',
        'video/mp4' => 'video',
        'video/webm' => 'video',
        'video/quicktime' => 'video',
        'video/x-matroska' => 'video',
        'audio/mpeg' => 'audio',
        'audio/ogg' => 'audio',
        'audio/wav' => 'audio',
        'audio/x-wav' => 'audio',
        'audio/mp4' => 'audio',
        'application/pdf' => 'document',
    ];

    public function __construct(
        private readonly string $root,
        private readonly int $maxBytes,
        private readonly string $applicationRoot,
    ) {
        if ($maxBytes <= 0) {
            throw new InvalidArgumentException(
                'Лимит размера Media должен быть положительным.'
            );
        }

        $this->assertSafeRoot();
    }

    public static function fromConfig(): self
    {
        $applicationRoot = dirname(__DIR__, 2);
        $root = trim((string) Config::get(
            'media.storage_path',
            '',
        ));

        if ($root === '') {
            throw new RuntimeException(
                'media.storage_path не настроен.'
            );
        }

        return new self(
            $root,
            (int) Config::get(
                'media.max_upload_bytes',
                536870912,
            ),
            $applicationRoot,
        );
    }

    public function storeFile(string $sourcePath): MediaStoredBlob
    {
        $sourcePath = trim($sourcePath);

        if (
            $sourcePath === ''
            || is_link($sourcePath)
            || !is_file($sourcePath)
            || !is_readable($sourcePath)
        ) {
            throw new InvalidArgumentException(
                'Исходный файл Media недоступен или небезопасен.'
            );
        }

        $sourceSize = filesize($sourcePath);
        if (
            !is_int($sourceSize)
            || $sourceSize <= 0
            || $sourceSize > $this->maxBytes
        ) {
            throw new InvalidArgumentException(
                'Размер Media-файла недопустим.'
            );
        }

        $this->ensureDirectory($this->root);

        $temporary = tempnam(
            $this->root,
            '.churchcms-media-',
        );
        if (!is_string($temporary)) {
            throw new RuntimeException(
                'Не удалось создать временный Media-файл.'
            );
        }

        try {
            [$sha256, $bytes] = $this->copyAndHash(
                $sourcePath,
                $temporary,
            );

            $mimeType = $this->sniffMime($temporary);
            $mediaType = self::ALLOWED_MIME[$mimeType] ?? null;

            if ($mediaType === null) {
                throw new InvalidArgumentException(
                    'Тип загружаемого файла не разрешён Media-политикой.'
                );
            }

            $target = $this->pathForHash($sha256);
            $this->ensureDirectory(dirname($target));

            if (is_file($target)) {
                $this->assertExistingBlob(
                    $target,
                    $sha256,
                    $bytes,
                );
                return new MediaStoredBlob(
                    $sha256,
                    $bytes,
                    $mimeType,
                    $mediaType,
                    false,
                );
            }

            if (!@rename($temporary, $target)) {
                if (!is_file($target)) {
                    throw new RuntimeException(
                        'Не удалось атомарно сохранить Media blob.'
                    );
                }

                $this->assertExistingBlob(
                    $target,
                    $sha256,
                    $bytes,
                );

                return new MediaStoredBlob(
                    $sha256,
                    $bytes,
                    $mimeType,
                    $mediaType,
                    false,
                );
            }

            @chmod($target, 0600);
            $temporary = '';

            return new MediaStoredBlob(
                $sha256,
                $bytes,
                $mimeType,
                $mediaType,
                true,
            );
        } finally {
            if (
                $temporary !== ''
                && is_file($temporary)
            ) {
                @unlink($temporary);
            }
        }
    }

    public function pathForHash(string $sha256): string
    {
        $sha256 = strtolower(trim($sha256));

        if (preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new InvalidArgumentException(
                'Некорректная SHA-256 сумма Media blob.'
            );
        }

        return $this->root
            . DIRECTORY_SEPARATOR
            . 'sha256'
            . DIRECTORY_SEPARATOR
            . substr($sha256, 0, 2)
            . DIRECTORY_SEPARATOR
            . substr($sha256, 2, 2)
            . DIRECTORY_SEPARATOR
            . $sha256
            . '.blob';
    }

    public function exists(string $sha256): bool
    {
        $path = $this->pathForHash($sha256);

        return is_file($path)
            && !is_link($path);
    }

    /**
     * @return array{string,int}
     */
    private function copyAndHash(
        string $source,
        string $target,
    ): array {
        $input = fopen($source, 'rb');
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }

            throw new RuntimeException(
                'Не удалось открыть Media-файл для безопасного копирования.'
            );
        }

        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (!feof($input)) {
                $chunk = fread($input, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException(
                        'Ошибка чтения Media-файла.'
                    );
                }

                if ($chunk === '') {
                    continue;
                }

                $bytes += strlen($chunk);
                if ($bytes > $this->maxBytes) {
                    throw new InvalidArgumentException(
                        'Media-файл превышает разрешённый размер.'
                    );
                }

                hash_update($hash, $chunk);

                $written = fwrite($output, $chunk);
                if (
                    $written === false
                    || $written !== strlen($chunk)
                ) {
                    throw new RuntimeException(
                        'Ошибка записи Media blob.'
                    );
                }
            }

            fflush($output);
        } finally {
            fclose($input);
            fclose($output);
        }

        if ($bytes <= 0) {
            throw new InvalidArgumentException(
                'Пустой Media-файл не допускается.'
            );
        }

        return [
            hash_final($hash),
            $bytes,
        ];
    }

    private function sniffMime(string $path): string
    {
        if (!class_exists(\finfo::class)) {
            throw new RuntimeException(
                'Для Media MIME sniffing требуется ext-fileinfo.'
            );
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        if (!is_string($mime) || $mime === '') {
            throw new RuntimeException(
                'Не удалось определить MIME Media-файла.'
            );
        }

        return strtolower(trim($mime));
    }

    private function assertExistingBlob(
        string $path,
        string $sha256,
        int $bytes,
    ): void {
        if (is_link($path)) {
            throw new RuntimeException(
                'Media blob не может быть символической ссылкой.'
            );
        }

        $size = filesize($path);
        $actualHash = hash_file('sha256', $path);

        if (
            !is_int($size)
            || $size !== $bytes
            || !is_string($actualHash)
            || !hash_equals($sha256, $actualHash)
        ) {
            throw new RuntimeException(
                'Существующий Media blob не прошёл проверку целостности.'
            );
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                0700,
                true,
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Не удалось создать каталог Media storage.'
            );
        }

        if (is_link($directory)) {
            throw new RuntimeException(
                'Каталог Media storage не может быть символической ссылкой.'
            );
        }
    }

    private function assertSafeRoot(): void
    {
        if (
            $this->root === ''
            || $this->root[0] !== DIRECTORY_SEPARATOR
        ) {
            throw new InvalidArgumentException(
                'media.storage_path должен быть абсолютным путём.'
            );
        }

        $root = self::normalizePath($this->root);
        $application = self::normalizePath(
            $this->applicationRoot,
        );

        if (
            $root === $application
            || str_starts_with(
                $root . DIRECTORY_SEPARATOR,
                $application . DIRECTORY_SEPARATOR,
            )
        ) {
            throw new InvalidArgumentException(
                'Media storage должен находиться вне корня ChurchCMS.'
            );
        }
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            trim($path),
        );

        $segments = [];
        foreach (
            explode(DIRECTORY_SEPARATOR, $path)
            as $segment
        ) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, $segments);
    }
}
