<?php

declare(strict_types=1);

use ChurchCMS\App\Services\AdminNavigationRegistry;
use ChurchCMS\Modules\Publications\PublicationSyndicationProvider;

require dirname(__DIR__) . '/core.php';

/** @param list<string> $ids */
function requireIds(array $ids, array $entries, string $profile): void
{
    $actual = array_column($entries, 'id');

    foreach ($ids as $id) {
        if (in_array($id, $actual, true)) {
            continue;
        }

        fwrite(
            STDERR,
            "Профиль {$profile}: отсутствует обязательный раздел {$id}.\n",
        );
        exit(1);
    }
}

/** @param list<string> $ids */
function forbidIds(array $ids, array $entries, string $profile): void
{
    $actual = array_column($entries, 'id');

    foreach ($ids as $id) {
        if (!in_array($id, $actual, true)) {
            continue;
        }

        fwrite(
            STDERR,
            "Профиль {$profile}: лишний раздел {$id}.\n",
        );
        exit(1);
    }
}

function assertGroupOrder(array $entries, string $profile): void
{
    $closed = [];
    $current = null;

    foreach ($entries as $entry) {
        $group = (string) ($entry['group'] ?? '');
        if ($group === '') {
            fwrite(STDERR, "Профиль {$profile}: раздел без группы.\n");
            exit(1);
        }

        if ($current === $group) {
            continue;
        }

        if (isset($closed[$group])) {
            fwrite(
                STDERR,
                "Профиль {$profile}: группа {$group} разорвана другими разделами.\n",
            );
            exit(1);
        }

        if ($current !== null) {
            $closed[$current] = true;
        }
        $current = $group;
    }
}

$parish = AdminNavigationRegistry::entriesForProfile('parish');
requireIds(['publications', 'pages', 'navigation'], $parish, 'приход');
forbidIds(
    [
        'organizations',
        'federation',
        'education_programs',
        'distribution_webhooks',
        'syndication_exports',
    ],
    $parish,
    'приход',
);
assertGroupOrder($parish, 'приход');

$education = AdminNavigationRegistry::entriesForProfile('education');
requireIds(['publications', 'pages', 'education_programs'], $education, 'учебное заведение');
forbidIds(
    ['organizations', 'federation', 'ministries', 'shrines', 'saints'],
    $education,
    'учебное заведение',
);
assertGroupOrder($education, 'учебное заведение');

$diocese = AdminNavigationRegistry::entriesForProfile('diocese');
requireIds(['organizations', 'federation', 'publications'], $diocese, 'епархия');
forbidIds(['education_programs'], $diocese, 'епархия');
assertGroupOrder($diocese, 'епархия');

$targets = PublicationSyndicationProvider::editorTargets();
if (!isset($targets['rss'])) {
    fwrite(STDERR, "Редактор публикации потерял локальный RSS-канал.\n");
    exit(1);
}

foreach (['diocese', 'rambler'] as $target) {
    if (!isset($targets[$target])) {
        continue;
    }

    fwrite(
        STDERR,
        "Редактор показывает неподключённый канал {$target}.\n",
    );
    exit(1);
}

if (AdminNavigationRegistry::profileLabel('parish') !== 'Приход') {
    fwrite(STDERR, "Не определено русское название приходского профиля.\n");
    exit(1);
}

echo "Профильная административная панель: проверка пройдена.\n";
