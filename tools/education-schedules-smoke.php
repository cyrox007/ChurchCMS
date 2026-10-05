<?php

declare(strict_types=1);

use ChurchCMS\Core\DatabaseManager;
use ChurchCMS\Core\ThemeRenderer;
use ChurchCMS\Modules\EducationPrograms\EducationProgramService;
use ChurchCMS\Modules\EducationSchedules\EducationScheduleCatalogService;
use ChurchCMS\Modules\EducationSchedules\EducationScheduleRepository;
use ChurchCMS\Modules\EducationSchedules\EducationScheduleService;
use ChurchCMS\Modules\Organizations\OrganizationService;
use InvalidArgumentException;
use PDOException;

require dirname(__DIR__) . '/core.php';

$organizations = OrganizationService::fromDatabase();
$programs = EducationProgramService::fromDatabase();
$schedules = EducationScheduleService::fromDatabase();
$repository = EducationScheduleRepository::fromDatabase();
$catalog = EducationScheduleCatalogService::fromDatabase();
$siteKey = 'education-schedules-smoke';

$root = $organizations->ensureSiteRoot('Тестовая образовательная организация', 'education', $siteKey);
$programId = $programs->createDraft(
    ownerOrganizationPublicId: $root->publicId,
    title: 'Церковная история',
    educationLevel: 'дополнительное образование',
    studyForm: 'очная',
    durationMonths: 9,
    qualification: null,
    admissionNote: '',
    summary: '',
    descriptionInput: '',
    siteKey: $siteKey,
);

$scheduleId = $schedules->createDraft(
    programPublicId: $programId,
    title: 'История Русской Церкви',
    startsAtLocal: '2026-10-12T10:00',
    endsAtLocal: '2026-10-12T11:30',
    timezone: 'Europe/Moscow',
    location: 'Аудитория 3',
    note: 'Вводное занятие.',
    siteKey: $siteKey,
);

$record = $repository->find($scheduleId, $siteKey);
if (
    $record === null
    || $record->status !== 'draft'
    || $record->startsAtUtc !== '2026-10-12 07:00:00'
    || $record->endsAtUtc !== '2026-10-12 08:30:00'
    || $record->timezone !== 'Europe/Moscow'
) {
    fwrite(STDERR, "Черновик расписания или преобразование времени сохранены некорректно.\n");
    exit(1);
}

$schedules->publish($scheduleId, $siteKey);
if ($catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Расписание черновой образовательной программы стало публичным.\n");
    exit(1);
}

$programs->publish($programId, $siteKey);
$items = $catalog->index($siteKey);
$detail = $catalog->detail($scheduleId, $siteKey);
if (
    count($items) !== 1
    || ($items[0]['public_id'] ?? '') !== $scheduleId
    || !str_contains((string) ($detail['starts_at'] ?? ''), '10:00:00+03:00')
    || ($detail['location'] ?? '') !== 'Аудитория 3'
    || ($detail['note'] ?? '') !== 'Вводное занятие.'
) {
    fwrite(STDERR, "Опубликованное расписание не попало в публичную проекцию.\n");
    exit(1);
}

$schedules->update(
    publicId: $scheduleId,
    programPublicId: $programId,
    title: 'История Поместных Церквей',
    startsAtLocal: '2026-10-12T12:00',
    endsAtLocal: '2026-10-12T13:00',
    timezone: 'Europe/Moscow',
    location: 'Аудитория 5',
    note: 'Обновлено.',
    siteKey: $siteKey,
);
$updated = $repository->find($scheduleId, $siteKey);
if ($updated === null || $updated->title !== 'История Поместных Церквей' || $updated->startsAtUtc !== '2026-10-12 09:00:00') {
    fwrite(STDERR, "Обновление расписания не сохранилось.\n");
    exit(1);
}

try {
    $schedules->createDraft(
        programPublicId: $programId,
        title: 'Некорректное время',
        startsAtLocal: '2026-10-12T15:00',
        endsAtLocal: '2026-10-12T14:00',
        timezone: 'Europe/Moscow',
        location: '',
        note: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил окончание раньше начала.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

try {
    $schedules->createDraft(
        programPublicId: $programId,
        title: 'Некорректный пояс',
        startsAtLocal: '2026-10-12T15:00',
        endsAtLocal: '2026-10-12T16:00',
        timezone: 'Mars/Phobos',
        location: '',
        note: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил неизвестный часовой пояс.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$foreignSite = 'education-schedules-foreign';
$foreignRoot = $organizations->ensureSiteRoot('Чужая образовательная организация', 'education', $foreignSite);
$foreignProgramId = $programs->createDraft(
    ownerOrganizationPublicId: $foreignRoot->publicId,
    title: 'Чужая программа',
    educationLevel: 'дополнительное образование',
    studyForm: 'очная',
    durationMonths: 1,
    qualification: null,
    admissionNote: '',
    summary: '',
    descriptionInput: '',
    siteKey: $foreignSite,
);

try {
    $schedules->createDraft(
        programPublicId: $foreignProgramId,
        title: 'Недопустимое занятие',
        startsAtLocal: '2026-10-12T15:00',
        endsAtLocal: '2026-10-12T16:00',
        timezone: 'Europe/Moscow',
        location: '',
        note: '',
        siteKey: $siteKey,
    );
    fwrite(STDERR, "Сервис разрешил программу из другого site_key.\n");
    exit(1);
} catch (InvalidArgumentException) {
}

$pdo = DatabaseManager::getInstance()->connection();
$statement = $pdo->prepare(
    'UPDATE education_schedules SET program_public_id = :program WHERE site_key = :site_key AND public_id = :public_id'
);
try {
    $statement->execute(['program' => $foreignProgramId, 'site_key' => $siteKey, 'public_id' => $scheduleId]);
    fwrite(STDERR, "База разрешила программу из другого site_key.\n");
    exit(1);
} catch (PDOException) {
}

$programs->unpublish($programId, $siteKey);
if ($catalog->detail($scheduleId, $siteKey) !== null || $catalog->index($siteKey) !== []) {
    fwrite(STDERR, "Расписание снятой с публикации программы осталось публичным.\n");
    exit(1);
}

$theme = ThemeRenderer::fromConfig()->activeTheme();
foreach (['admin.education_schedules', 'education_schedules.index', 'education_schedules.show'] as $logicalName) {
    if ($theme->template($logicalName) === null) {
        fwrite(STDERR, "В теме не зарегистрирован шаблон {$logicalName}.\n");
        exit(1);
    }
}

echo "Проверка образовательного расписания пройдена.\n";
