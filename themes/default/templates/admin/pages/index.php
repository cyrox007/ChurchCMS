<?php declare(strict_types=1);
$statusLabels = [
    'draft' => 'Черновик',
    'published' => 'Опубликовано',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Структура сайта</p>
            <h1>Страницы</h1>
            <p>Постоянные разделы сайта и их положение в дереве.</p>
        </div>

        <div class="admin-heading__actions">
            <?php if (!empty($canCreate)): ?>
                <a class="button button--primary" href="<?= $theme->e($theme->route('admin_page_new')) ?>">+ Новая страница</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (empty($pages)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Страниц пока нет',
            'message' => 'Создайте первый раздел. Он сохранится как черновик и не появится на сайте до публикации.',
            'action_href' => !empty($canCreate) ? $theme->route('admin_page_new') : '',
            'action_label' => !empty($canCreate) ? 'Создать страницу' : '',
        ]) ?>
    <?php else: ?>
        <div class="editorial-list">
            <?php foreach ($pages as $item): ?>
                <article class="editorial-item">
                    <div class="editorial-item__main">
                        <div class="editorial-item__meta">
                            <span class="status-pill status-pill--<?= $theme->e($item->status->value) ?>">
                                <?= $theme->e($statusLabels[$item->status->value] ?? $item->status->value) ?>
                            </span>
                            <span><?= $theme->e($item->path) ?></span>
                        </div>

                        <h2><?= $theme->e($item->title) ?></h2>
                        <?php if ($item->navigationTitle !== null): ?>
                            <p>В меню: <?= $theme->e($item->navigationTitle) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($editablePageIds[$item->publicId])): ?>
                        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_page_edit', ['publicId' => $item->publicId])) ?>">
                            Редактировать
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
