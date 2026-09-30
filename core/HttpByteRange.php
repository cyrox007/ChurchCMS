<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

use InvalidArgumentException;

final readonly class HttpByteRange
{
    public function __construct(
        public int $start,
        public int $end,
        public int $total,
    ) {
        if (
            $total <= 0
            || $start < 0
            || $end < $start
            || $end >= $total
        ) {
            throw new InvalidArgumentException(
                'Некорректный диапазон байтов.'
            );
        }
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    public static function fromHeader(
        ?string $header,
        int $total,
    ): ?self {
        $header = trim((string) $header);

        if ($header === '') {
            return null;
        }

        if (
            $total <= 0
            || preg_match(
                '/^bytes=(\d*)-(\d*)$/D',
                $header,
                $matches,
            ) !== 1
            || ($matches[1] === '' && $matches[2] === '')
        ) {
            throw new InvalidArgumentException(
                'Некорректный HTTP Range.'
            );
        }

        if ($matches[1] === '') {
            $suffix = (int) $matches[2];

            if ($suffix <= 0) {
                throw new InvalidArgumentException(
                    'Некорректный суффиксный HTTP Range.'
                );
            }

            $length = min($suffix, $total);

            return new self(
                $total - $length,
                $total - 1,
                $total,
            );
        }

        $start = (int) $matches[1];

        if ($start >= $total) {
            throw new InvalidArgumentException(
                'HTTP Range выходит за размер файла.'
            );
        }

        if ($matches[2] === '') {
            return new self(
                $start,
                $total - 1,
                $total,
            );
        }

        $end = min(
            (int) $matches[2],
            $total - 1,
        );

        if ($end < $start) {
            throw new InvalidArgumentException(
                'Конец HTTP Range меньше начала.'
            );
        }

        return new self(
            $start,
            $end,
            $total,
        );
    }
}
