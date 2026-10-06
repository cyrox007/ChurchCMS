<?php declare(strict_types=1);
$statusLabels = [
    'draft' => 'Черновик',
    'review' => 'На проверке',
    'scheduled' => 'Запланировано',
    'published' => 'Опубликовано',
    'withdrawn' => 'Снято с публикации',
];
$typeLabels = [
    'news' => 'Новость',
    'article' => 'Статья',
    'announcement' => 'Объявление',
    'sermon' => 'Проповедь',
    'interview' => 'Интервью',
    'document' => 'Документ',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Материалы</p>
            <h1>Публикации</h1>
            <p>Новости, статьи, объявления и другие материалы сайта.</p>
        </div>

        <div class="admin-heading__actions">
            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_syndication_exports')) ?>">Журнал экспортов</a>
            <?php if (!empty($canCreate)): ?>
                <a class="button button--primary" href="<?= $theme->e($theme->route('admin_publication_new')) ?>">+ Новая публикация</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (empty($publications)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Публикаций пока нет',
            'message' => 'Создайте первый материал — он сохранится как черновик и не появится на сайте, пока вы сами его не опубликуете.',
            'action_href' => !empty($canCreate) ? $theme->route('admin_publication_new') : '',
            'action_label' => !empty($canCreate) ? 'Создать публикацию' : '',
        ]) ?>
    <?php else: ?>
        <div class="editorial-list">
            <?php foreach ($publications as $publication): ?>
                <article class="editorial-item">
                    <div class="editorial-item__main">
                        <div class="editorial-item__meta">
                            <span class="status-pill status-pill--<?= $theme->e($publication->status->value) ?>">
                                <?= $theme->e($statusLabels[$publication->status->value] ?? $publication->status->value) ?>
                            </span>
                            <span><?= $theme->e($typeLabels[$publication->type->value] ?? $publication->type->value) ?></span>
                            <?php if ($publication->commentsEnabled): ?><span>Комментарии включены</span><?php endif; ?>
                        </div>

                        <h2><?= $theme->e($publication->title) ?></h2>
                        <?php if ($publication->excerpt !== ''): ?><p><?= $theme->e($publication->excerpt) ?></p><?php endif; ?>
                    </div>

                    <?php if (!empty($canEdit)): ?>
                        <div class="admin-heading__actions">
                            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publication_syndication_overrides', ['publicId' => $publication->publicId])) ?>">
                                Каналы
                            </a>
                            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publication_edit', ['publicId' => $publication->publicId])) ?>">
                                Редактировать
                            </a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>