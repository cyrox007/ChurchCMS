<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\PrayerRequests;

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Modules\Organizations\OrganizationRepository;
use InvalidArgumentException;
use PDO;

final class PrayerRequestService
{
    private OrganizationRepository $organizations;

    public function __construct(PDO $pdo)
    {
        $this->organizations = new OrganizationRepository($pdo);
    }

    public static function fromDatabase(): self
    {
        return new self(DatabaseManager::getInstance()->connection());
    }

    /** @param list<string> $names */
    public function submit(
        string $providerId,
        string $organizationPublicId,
        string $serviceCode,
        array $names,
        ?string $comment,
        string $returnPath,
        string $siteKey = 'default',
    ): PrayerRequestReceipt {
        $provider = PrayerRequestProviderRegistry::provider($providerId);
        if ($provider === null) {
            throw new InvalidArgumentException('Провайдер записок недоступен.');
        }

        $siteKey = self::siteKey($siteKey);
        $organization = $this->organizations->findByPublicId(trim($organizationPublicId), $siteKey);
        if ($organization === null || $organization->status !== 'active') {
            throw new InvalidArgumentException('Организация-получатель недоступна.');
        }

        $serviceCode = trim($serviceCode);
        if (!self::providerSupports($provider, $serviceCode)) {
            throw new InvalidArgumentException('Выбранный вид записки недоступен у провайдера.');
        }

        $normalizedNames = self::names($names);
        $comment = self::comment($comment);
        $returnPath = self::returnPath($returnPath);

        $receipt = $provider->submit(new PrayerRequestSubmission(
            siteKey: $siteKey,
            organizationPublicId: $organization->publicId,
            serviceCode: $serviceCode,
            names: $normalizedNames,
            comment: $comment,
            returnPath: $returnPath,
        ));

        if ($receipt->providerId !== $provider->id()) {
            throw new InvalidArgumentException('Провайдер вернул идентификатор другого адаптера.');
        }

        return $receipt;
    }

    private static function providerSupports(PrayerRequestProvider $provider, string $serviceCode): bool
    {
        foreach ($provider->services() as $service) {
            if (($service['code'] ?? '') === $serviceCode) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $names @return list<string> */
    private static function names(array $names): array
    {
        if ($names === [] || count($names) > 100) {
            throw new InvalidArgumentException('Укажите от одного до 100 имён.');
        }

        $result = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                throw new InvalidArgumentException('Имя в записке должно быть текстом.');
            }
            $normalized = preg_replace('/\s+/u', ' ', trim(strip_tags($name)));
            if (!is_string($normalized) || $normalized === '' || mb_strlen($normalized) > 120) {
                throw new InvalidArgumentException('Некорректное имя в записке.');
            }
            $result[] = $normalized;
        }
        return $result;
    }

    private static function comment(?string $comment): ?string
    {
        $comment = trim(strip_tags((string) ($comment ?? '')));
        if ($comment === '') {
            return null;
        }
        if (mb_strlen($comment) > 1000) {
            throw new InvalidArgumentException('Комментарий к записке слишком длинный.');
        }
        return $comment;
    }

    private static function siteKey(string $siteKey): string
    {
        $siteKey = trim($siteKey);
        if ($siteKey === '' || strlen($siteKey) > 64) {
            throw new InvalidArgumentException('Некорректный site_key.');
        }
        return $siteKey;
    }

    private static function returnPath(string $returnPath): string
    {
        $returnPath = trim($returnPath);
        if ($returnPath === '' || !str_starts_with($returnPath, '/') || str_starts_with($returnPath, '//') || preg_match('/[\x00-\x1F\x7F]/', $returnPath) === 1) {
            throw new InvalidArgumentException('Некорректный путь возврата после отправки записки.');
        }
        $parts = parse_url($returnPath);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            throw new InvalidArgumentException('Путь возврата должен быть локальным.');
        }
        return $returnPath;
    }
}
