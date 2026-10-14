<?php

declare(strict_types=1);

$search = is_array($search ?? null)
    ? $search
    : ['query' => '', 'items' => []];
$error = is_string($searchError ?? null)
    ? $searchError
    : null;
$query = (string) ($search['query'] ?? '');
$items = is_array($search['items'] ?? null)
    ? $search['items']
    : [];
$typeLabels = [
    'publication' => 'Публикация',
    'page' => 'Страница',
    'document' => 'Документ',
    'person' => 'Люди',
    'event' => 'Событие',
    'worship' => 'Богослужение',
    'ministry' => 'Служение',
    'shrine' => 'Святыня',
    'saint' => 'Святые',
    'library' => 'Библиотека',
    'sunday_school' => 'Воскресная школа',
    'education_program' => 'Образование',
];
?>
<section class="content-section">
    <header class="section-heading">
        <p class="eyebrow">Поиск</p>
        <h1>Поиск по сайту</h1>
        <p>Публикации, страницы, документы, люди и события.</p>
    </header>

    <form method="get" action="<?= $theme->e($theme->route('search_index')) ?>">
        <label>
            <span class="sr-only">Поисковый запрос</span>
            <input
                type="search"
                name="q"
                value="<?= $theme->e($query) ?>"
                minlength="2"
                maxlength="120"
                placeholder="Что найти?"
            >
        </label>
        <button class="button button--primary" type="submit">
            Найти
        </button>
    </form>

    <?php if ($error !== null): ?>
        <div class="empty-state" role="alert">
            <h2>Поиск не выполнен</h2>
            <p><?= $theme->e($error) ?></p>
        </div>
    <?php elseif ($query !== '' && $items === []): ?>
        <div class="empty-state">
            <h2>Ничего не найдено</h2>
            <p>Попробуйте изменить запрос или использовать более короткую формулировку.</p>
        </div>
    <?php elseif ($items !== []): ?>
        <div class="card-grid">
            <?php foreach ($items as $item): ?>
                <?php
                $type = trim((string) ($item['type'] ?? ''));
                $typeLabel = $typeLabels[$type] ?? 'Материал';
                ?>
                <article class="card">
                    <p class="eyebrow"><?= $theme->e($typeLabel) ?></p>
                    <h2>
                        <a href="<?= $theme->e((string) ($item['url'] ?? '#')) ?>">
                            <?= $theme->e((string) ($item['title'] ?? '')) ?>
                        </a>
                    </h2>
                    <?php if (!empty($item['excerpt'])): ?>
                        <p><?= $theme->e((string) $item['excerpt']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
