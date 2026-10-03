<?php

declare(strict_types=1);

use ChurchCMS\Modules\Organizations\OrganizationService;
use ChurchCMS\Modules\Worship\WorshipHolidayTemplateService;
use ChurchCMS\Modules\Worship\WorshipRepository;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$root = $organizations->ensureSiteRoot(
    'Тестовый приход праздничных шаблонов',
    'parish',
    'worship-holiday-smoke',
);

$templates = WorshipHolidayTemplateService::fromDatabase();
$templateId = $templates->createTemplate(
    'Рождество Христово',
    'Europe/Moscow',
    'worship-holiday-smoke',
);

$templates->addItem(
    $templateId,
    'Всенощное бдение',
    -1,
    '17:00',
    'worship-holiday-smoke',
    'vigil',
    180,
    'Главный храм',
    sortOrder: 10,
);

$templates->addItem(
    $templateId,
    'Божественная литургия',
    0,
    '09:00',
    'worship-holiday-smoke',
    'liturgy',
    120,
    'Главный храм',
    sortOrder: 20,
);

$created = $templates->apply(
    $templateId,
    $root->publicId,
    '2027-01-07',
    'worship-holiday-smoke',
);

if (count($created) !== 2) {
    fwrite(
        STDERR,
        'Праздничный шаблон должен создать две службы, создано: '
        . count($created)
        . "\n",
    );
    exit(1);
}

$repeat = $templates->apply(
    $templateId,
    $root->publicId,
    '2027-01-07',
    'worship-holiday-smoke',
);

if ($repeat !== []) {
    fwrite(
        STDERR,
        "Повторное применение праздничного шаблона создало дубли.\n",
    );
    exit(1);
}

$services = WorshipRepository::fromDatabase()->forOrganization(
    $root->publicId,
    'worship-holiday-smoke',
);

if (count($services) !== 2) {
    fwrite(
        STDERR,
        "Созданные праздничные службы не найдены в Worship.\n",
    );
    exit(1);
}

$starts = array_map(
    static fn($service): string => $service->startsAt,
    $services,
);
sort($starts);

$expected = [
    '2027-01-06 14:00:00',
    '2027-01-07 06:00:00',
];

if ($starts !== $expected) {
    fwrite(
        STDERR,
        "Дата, смещение или часовой пояс праздничного шаблона обработаны неверно.\n",
    );
    exit(1);
}

echo "Праздничные шаблоны Worship: OK\n";
