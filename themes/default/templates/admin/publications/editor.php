<?php declare(strict_types=1);
$isEdit = isset($publication) && $publication !== null;
$typeLabels = [
    'news' => 'Новость',
    'article' => 'Статья',
    'announcement' => 'Объявление',
    'sermon' => 'Проповедь',
    'interview' => 'Интервью',
    'document' => 'Документ',
];
$currentTargets = $isEdit ? $publication->syndicationTargets : [];
$formAction = $isEdit
    ? $theme->route('admin_publication_update', ['publicId' => $publication->publicId])
    : $theme->route('admin_publication_create');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow"><?= $isEdit ? 'Редактирование' : 'Новый материал' ?></p>
            <h1><?= $isEdit ? $theme->e($publication->title) : 'Новая публикация' ?></h1>
            <p><?= $isEdit ? 'Изменения можно сохранить без публикации.' : 'Сначала сохраните черновик — на сайт он автоматически не попадёт.' ?></p>
        </div>
        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publications')) ?>">← К публикациям</a>
    </header>

    <?php if (!empty($error)): ?><div class="form-alert" role="alert"><?= $theme->e($error) ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="comment-notice comment-notice--success" role="status"><?= $theme->e($success) ?></div><?php endif; ?>

    <form class="publication-editor" method="post" action="<?= $theme->e($formAction) ?>">
        <?= $theme->csrfInput() ?>

        <section class="editor-card editor-card--primary">
            <label class="field field--title">
                <span>Заголовок</span>
                <input name="title" value="<?= $theme->e($form['title'] ?? '') ?>" required maxlength="255" autofocus>
            </label>

            <div class="editor-grid">
                <label class="field">
                    <span>Тип материала</span>
                    <select name="type">
                        <?php foreach ($typeLabels as $value => $label): ?>
                            <option value="<?= $theme->e($value) ?>" <?= ($form['type'] ?? 'news') === $value ? 'selected' : '' ?>>
                                <?= $theme->e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span>Автор <small>необязательно</small></span>
                    <input name="author_name" value="<?= $theme->e($form['author_name'] ?? '') ?>" maxlength="255">
                </label>
            </div>

            <label class="field">
                <span>Короткое описание <small>показывается в списках</small></span>
                <textarea name="excerpt" rows="3"><?= $theme->e($form['excerpt'] ?? '') ?></textarea>
            </label>

            <label class="field">
                <span>Текст публикации</span>
                <textarea class="editor-body" name="body" rows="18" required><?= $theme->e($form['body'] ?? '') ?></textarea>
                <small>Можно писать обычным текстом. Абзацы формируются автоматически.</small>
            </label>
        </section>

        <section class="editor-card">
            <div class="setting-row">
                <div>
                    <strong>Комментарии</strong>
                    <p>Разрешить посетителям оставлять комментарии к этой публикации. Новые комментарии сначала проходят модерацию.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="comments_enabled" value="1" <?= !empty($form['comments_enabled']) ? 'checked' : '' ?>>
                    <span>Разрешить комментарии</span>
                </label>
            </div>
        </section>

        <?php if (!empty($canSyndicate)): ?>
            <section class="editor-card">
                <h2>Распространение</h2>
                <p class="editor-card__hint">Отметьте только те каналы, куда этот материал разрешено передавать.</p>

                <div class="choice-list">
                    <?php foreach ([
                        'rss' => ['RSS', 'Обычная новостная лента сайта'],
                        'diocese' => ['Сайт епархии', 'Материал доступен партнёрской синхронизации'],
                        'rambler' => ['Рамблер/Новости', 'Включать только после подключения сайта к агрегатору'],
                    ] as $target => [$label, $hint]): ?>
                        <label class="choice">
                            <input type="checkbox" name="syndication_targets[]" value="<?= $theme->e($target) ?>" <?= in_array($target, $currentTargets, true) ? 'checked' : '' ?>>
                            <span><strong><?= $theme->e($label) ?></strong><small><?= $theme->e($hint) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <details class="editor-card editor-advanced">
            <summary>Дополнительно</summary>
            <label class="field">
                <span>Адрес материала</span>
                <input name="slug" value="<?= $theme->e($form['slug'] ?? '') ?>" maxlength="180" placeholder="создастся автоматически">
                <small>Обычно это поле трогать не нужно.</small>
            </label>
        </details>

        <div class="editor-actions">
            <button class="button button--primary" type="submit"><?= $isEdit ? 'Сохранить изменения' : 'Сохранить черновик' ?></button>
        </div>
    </form>

    <?php if ($isEdit && !empty($canPublish)): ?>
        <section class="publication-state-actions">
            <?php if ($publication->status->value !== 'published'): ?>
                <form method="post" action="<?= $theme->e($theme->route('admin_publication_publish', ['publicId' => $publication->publicId])) ?>">
                    <?= $theme->csrfInput() ?>
                    <button class="button button--primary" type="submit">Опубликовать на сайте</button>
                </form>
            <?php else: ?>
                <a class="button button--quiet" href="<?= $theme->e($theme->route('publication_show', ['slug' => $publication->slug])) ?>" target="_blank" rel="noopener">
                    Посмотреть на сайте
                </a>
                <form method="post" action="<?= $theme->e($theme->route('admin_publication_withdraw', ['publicId' => $publication->publicId])) ?>">
                    <?= $theme->csrfInput() ?>
                    <button class="button button--quiet" type="submit">Снять с публикации</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
