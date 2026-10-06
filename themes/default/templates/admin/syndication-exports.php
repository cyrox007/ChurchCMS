<?php declare(strict_types=1);
$statusLabels = [
    'success' => 'Успешно',
    'failed' => 'Ошибка',
];
$targetLabels = [
    'rss' => 'RSS 2.0',
    'rambler' => 'Rambler',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешнее распространение</p>
            <h1>Журнал экспортов</h1>
            <p>История фактического формирования фидов. Повторная выдача из кэша не создаёт новую запись.</p>
        </div>
        <div class="admin-heading__actions">
            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publications')) ?>">К публикациям</a>
        </div>
    </header>

    <form method="get" class="admin-filter" action="<?= $theme->e($theme->route('admin_syndication_exports')) ?>">
        <label>
            Канал
            <select name="target">
                <option value=""<?= $target === '' ? ' selected' : '' ?>>Все</option>
                <option value="rss"<?= $target === 'rss' ? ' selected' : '' ?>>RSS 2.0</option>
                <option value="rambler"<?= $target === 'rambler' ? ' selected' : '' ?>>Rambler</option>
            </select>
        </label>
        <button class="button button--quiet" type="submit">Применить</button>
    </form>

    <?php if (empty($entries)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Экспортов пока нет',
            'message' => 'Запись появится после первого формирования включённого RSS или Rambler-фида.',
        ]) ?>
    <?php else: ?>
        <div class="editorial-list">
            <?php foreach ($entries as $entry): ?>
                <article class="editorial-item">
                    <div class="editorial-item__main">
                        <div class="editorial-item__meta">
                            <span class="status-pill"><?= $theme->e($statusLabels[$entry['status']] ?? (string) $entry['status']) ?></span>
                            <span><?= $theme->e($targetLabels[$entry['target']] ?? (string) $entry['target']) ?></span>
                            <span><?= $theme->e((string) $entry['created_at']) ?> UTC</span>
                        </div>
                        <h2><?= (int) $entry['entry_count'] ?> материалов</h2>
                        <p>
                            Размер: <?= (int) $entry['body_bytes'] ?> байт ·
                            Формирование: <?= (int) $entry['duration_ms'] ?> мс
                            <?php if (!empty($entry['error_code'])): ?>· Код: <?= $theme->e((string) $entry['error_code']) ?><?php endif; ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
