<?php

declare(strict_types=1);

use ChurchCMS\Core\ModuleRuntimeLoader;
use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\PrayerRequests\PrayerRequestCapability;
use ChurchCMS\Modules\PrayerRequests\PrayerRequestProvider;
use ChurchCMS\Modules\PrayerRequests\PrayerRequestProviderRegistry;
use ChurchCMS\Modules\PrayerRequests\PrayerRequestReceipt;
use ChurchCMS\Modules\PrayerRequests\PrayerRequestSubmission;
use InvalidArgumentException;

require dirname(__DIR__) . '/core.php';

final class SmokePrayerRequestProvider implements PrayerRequestProvider
{
    public ?PrayerRequestSubmission $lastSubmission = null;

    public function id(): string
    {
        return 'smoke-notes';
    }

    public function label(): string
    {
        return 'Тестовый провайдер записок';
    }

    public function services(): array
    {
        return [
            ['code' => 'liturgy', 'label' => 'Литургия'],
            ['code' => 'moleben', 'label' => 'Молебен'],
        ];
    }

    public function submit(PrayerRequestSubmission $submission): PrayerRequestReceipt
    {
        $this->lastSubmission = $submission;
        return new PrayerRequestReceipt(
            providerId: $this->id(),
            externalId: 'request-001',
            statusUrl: 'https://notes.example.test/status/request-001',
        );
    }
}

$capability = ModuleRuntimeLoader::capability('prayer_requests', 'prayer_requests.submit');
if (!$capability instanceof PrayerRequestCapability) {
    fwrite(STDERR, "Capability записок не зарегистрирована.\n");
    exit(1);
}

$provider = new SmokePrayerRequestProvider();
PrayerRequestProviderRegistry::register($provider);
if (($capability->providers()[0]['services'][0]['code'] ?? '') !== 'liturgy') {
    fwrite(STDERR, "Виды записок провайдера не доступны через capability.\n");
    exit(1);
}

$organizations = OrganizationService::fromDatabase();
$siteKey = 'prayer-requests-smoke';
$root = $organizations->ensureSiteRoot('Тестовый приход записок', 'parish', $siteKey);

$receipt = $capability->submit(
    providerId: 'smoke-notes',
    organizationPublicId: $root->publicId,
    serviceCode: 'moleben',
    names: ['  Иоанн  ', '<b>Мария</b>'],
    comment: '<i>О здравии</i>',
    returnPath: '/prayer-requests/thanks',
    siteKey: $siteKey,
);

if (
    $receipt->externalId !== 'request-001'
    || $provider->lastSubmission === null
    || $provider->lastSubmission->names !== ['Иоанн', 'Мария']
    || $provider->lastSubmission->comment !== 'О здравии'
    || $provider->lastSubmission->serviceCode !== 'moleben'
) {
    fwrite(STDERR, "Провайдер получил некорректно нормализованную заявку.\n");
    exit(1);
}

$foreign = $organizations->ensureSiteRoot('Чужой приход записок', 'parish', 'prayer-requests-foreign');
$invalidCases = [
    ['organizationPublicId' => $foreign->publicId],
    ['serviceCode' => 'unknown'],
    ['names' => []],
    ['names' => array_fill(0, 101, 'Имя')],
    ['names' => ['<script></script>']],
    ['returnPath' => 'https://evil.example/steal'],
    ['returnPath' => '//evil.example/steal'],
    ['providerId' => 'missing-provider'],
];

foreach ($invalidCases as $change) {
    $arguments = [
        'providerId' => 'smoke-notes',
        'organizationPublicId' => $root->publicId,
        'serviceCode' => 'liturgy',
        'names' => ['Иоанн'],
        'comment' => null,
        'returnPath' => '/prayer-requests/thanks',
        'siteKey' => $siteKey,
    ];
    foreach ($change as $key => $value) {
        $arguments[$key] = $value;
    }

    try {
        $capability->submit(...$arguments);
        fwrite(STDERR, "Граница записок приняла некорректный запрос: {$key}.\n");
        exit(1);
    } catch (InvalidArgumentException) {
    }
}

try {
    new PrayerRequestReceipt(
        providerId: 'smoke-notes',
        externalId: 'request-insecure',
        statusUrl: 'http://notes.example.test/status/request-insecure',
    );
    fwrite(STDERR, "Результат заявки разрешил небезопасный status URL.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

echo "Prayer request integration boundary smoke OK\n";
