<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use RuntimeException;

final class LogManager
{
    public const DEFAULT_MAX_BYTES = 10485760;
    public const DEFAULT_MAX_ARCHIVES = 10;
    public const DEFAULT_MAX_AGE_DAYS = 30;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
        private readonly int $maxArchives = self::DEFAULT_MAX_ARCHIVES,
        private readonly int $maxAgeDays = self::DEFAULT_MAX_AGE_DAYS,
    ) {
        if ($this->maxBytes < 65536) {
            throw new RuntimeException('Лимит журнала не может быть меньше 64 КиБ.');
        }
        if ($this->maxArchives < 1 || $this->maxArchives > 100) {
            throw new RuntimeException('Количество архивов должно быть от 1 до 100.');
        }
        if ($this->maxAgeDays < 1 || $this->maxAgeDays > 3650) {
            throw new RuntimeException('Срок хранения журналов должен быть от 1 до 3650 дней.');
        }
    }

    public static function forErrorLog(string $root): self
    {
        $directory = rtrim($root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'logs';

        return new self(
            directory: $directory,
            maxBytes: self::configInt('operations.logs.max_bytes', self::DEFAULT_MAX_BYTES),
            maxArchives: self::configInt('operations.logs.max_archives', self::DEFAULT_MAX_ARCHIVES),
            maxAgeDays: self::configInt('operations.logs.max_age_days', self::DEFAULT_MAX_AGE_DAYS),
        );
    }

    public function append(string $name, string $record): bool
    {
        $this->assertName($name);
        if (!$this->usableDirectory()) {
            return false;
        }

        $lockPath = $this->directory . DIRECTORY_SEPARATOR . '.' . $name . '.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false) {
            return false;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return false;
            }

            $path = $this->path($name);
            $this->assertRegularOrMissing($path);
            $size = is_file($path) ? (int) filesize($path) : 0;
            if ($size > 0 && $size + strlen($record) > $this->maxBytes) {
                $this->rotateLocked($name, $path);
            }

            $written = @file_put_contents($path, $record, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                return false;
            }
            @chmod($path, 0640);
            $this->pruneLocked($name);

            return true;
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{rotated:bool,removed:int,current_bytes:int,archives:int} */
    public function maintain(string $name = 'error.log', bool $forceRotate = false): array
    {
        $this->assertName($name);
        if (!$this->usableDirectory()) {
            throw new RuntimeException('Каталог журналов недоступен для записи.');
        }

        $lockPath = $this->directory . DIRECTORY_SEPARATOR . '.' . $name . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Не удалось получить блокировку журнала.');
        }

        try {
            $path = $this->path($name);
            $this->assertRegularOrMissing($path);
            $size = is_file($path) ? (int) filesize($path) : 0;
            $rotated = false;
            if ($size > 0 && ($forceRotate || $size >= $this->maxBytes)) {
                $this->rotateLocked($name, $path);
                $rotated = true;
            }
            $removed = $this->pruneLocked($name);
            $currentBytes = is_file($path) ? (int) filesize($path) : 0;

            return [
                'rotated' => $rotated,
                'removed' => $removed,
                'current_bytes' => $currentBytes,
                'archives' => count($this->archives($name)),
            ];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function rotateLocked(string $name, string $path): void
    {
        $suffix = gmdate('Ymd-His') . '-' . getmypid() . '-' . bin2hex(random_bytes(3));
        $archive = $this->directory . DIRECTORY_SEPARATOR . $name . '.' . $suffix;
        if (!@rename($path, $archive)) {
            throw new RuntimeException('Не удалось ротировать журнал.');
        }
        @chmod($archive, 0640);
    }

    private function pruneLocked(string $name): int
    {
        $archives = $this->archives($name);
        $cutoff = time() - ($this->maxAgeDays * 86400);
        $removed = 0;

        foreach ($archives as $index => $archive) {
            $mtime = @filemtime($archive);
            $tooOld = $mtime !== false && $mtime < $cutoff;
            $tooMany = $index >= $this->maxArchives;
            if (!$tooOld && !$tooMany) {
                continue;
            }
            if (is_link($archive) || !is_file($archive)) {
                continue;
            }
            if (@unlink($archive)) {
                ++$removed;
            }
        }

        return $removed;
    }

    /** @return list<string> */
    private function archives(string $name): array
    {
        $matches = glob($this->directory . DIRECTORY_SEPARATOR . $name . '.*');
        if (!is_array($matches)) {
            return [];
        }

        $archives = array_values(array_filter(
            $matches,
            static fn (string $path): bool => is_file($path) && !is_link($path),
        ));
        usort(
            $archives,
            static fn (string $a, string $b): int => ((int) @filemtime($b)) <=> ((int) @filemtime($a)),
        );

        return $archives;
    }

    private function usableDirectory(): bool
    {
        return is_dir($this->directory)
            && !is_link($this->directory)
            && is_writable($this->directory);
    }

    private function path(string $name): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $name;
    }

    private function assertRegularOrMissing(string $path): void
    {
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('Небезопасный путь журнала.');
        }
    }

    private function assertName(string $name): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}\.log$/D', $name) !== 1) {
            throw new RuntimeException('Некорректное имя журнала.');
        }
    }

    private static function configInt(string $key, int $default): int
    {
        if (!class_exists(Config::class, false)) {
            return $default;
        }
        $value = Config::get($key, $default);

        return is_int($value) ? $value : $default;
    }
}
