<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\PrayerRequests;

use InvalidArgumentException;

final readonly class PrayerRequestReceipt
{
    public function __construct(
        public string $providerId,
        public string $externalId,
        public ?string $statusUrl = null,
    ) {
        if (trim($providerId) === '' || trim($externalId) === '') {
            throw new InvalidArgumentException('Провайдер вернул неполный идентификатор заявки.');
        }

        if ($statusUrl === null) {
            return;
        }

        $parts = parse_url($statusUrl);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || trim((string) ($parts['host'] ?? '')) === '') {
            throw new InvalidArgumentException('Адрес статуса заявки должен использовать HTTPS.');
        }
    }
}
