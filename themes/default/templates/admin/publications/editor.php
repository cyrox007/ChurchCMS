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

    <?php if (!empty($error)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Не удалось сохранить',
            'message' => (string) $error,
        ]) ?>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'success',
            'title' => 'Сохранено',
            'message' => (string) $success,
        ]) ?>
    <?php endif; ?>

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

            <div class="editor-grid">
                <label class="field">
                    <span>Категории <small>через запятую</small></span>
                    <input
                        name="categories"
                        value="<?= $theme->e($form['categories'] ?? '') ?>"
                        maxlength="1000"
                        placeholder="Например: Новости прихода, Богослужение"
                    >
                    <small>До 8 категорий. Уже существующие категории будут переиспользованы.</small>
                </label>

                <label class="field">
                    <span>Теги <small>через запятую</small></span>
                    <input
                        name="tags"
                        value="<?= $theme->e($form['tags'] ?? '') ?>"
                        maxlength="2000"
                        placeholder="Например: Пасха, молодёжь, благотворительность"
                    >
                    <small>До 20 тегов. Дубликаты удаляются автоматически.</small>
                </label>
            </div>

            <label class="field">
                <span>Короткое описание <small>показывается в списках и используется SEO по умолчанию</small></span>
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

        <details class="editor-card seo-editor">
            <summary>
                <span>SEO и карточка в соцсетях</span>
                <small>Обычно заполнять не нужно — ChurchCMS возьмёт данные из публикации.</small>
            </summary>

            <div class="seo-editor__body">
                <div class="editor-grid">
                    <label class="field">
                        <span>Заголовок для поиска</span>
                        <input name="seo_title" value="<?= $theme->e($form['seo_title'] ?? '') ?>" maxlength="255" placeholder="по умолчанию — заголовок публикации">
                    </label>

                    <label class="field">
                        <span>Ключевые слова <small>необязательно</small></span>
                        <input name="seo_keywords" value="<?= $theme->e($form['seo_keywords'] ?? '') ?>" maxlength="1000" placeholder="через запятую">
                    </label>
                </div>

                <label class="field">
                    <span>Описание для поисковых систем</span>
                    <textarea name="seo_description" rows="3" maxlength="500" placeholder="по умолчанию — короткое описание"><?= $theme->e($form['seo_description'] ?? '') ?></textarea>
                </label>

                <div class="seo-editor__divider"></div>

                <h3>Карточка при отправке ссылки</h3>
                <p class="editor-card__hint">Open Graph и social-card теги формируются автоматически. Эти поля нужны только если карточка должна отличаться от публикации.</p>

                <div class="editor-grid">
                    <label class="field">
                        <span>Заголовок карточки</span>
                        <input name="social_title" value="<?= $theme->e($form['social_title'] ?? '') ?>" maxlength="255">
                    </label>

                    <label class="field">
                        <span>Изображение карточки</span>
                        <input name="social_image_url" value="<?= $theme->e($form['social_image_url'] ?? '') ?>" placeholder="https://...">
                    </label>
                </div>

                <label class="field">
                    <span>Описание карточки</span>
                    <textarea name="social_description" rows="3" maxlength="500"><?= $theme->e($form['social_description'] ?? '') ?></textarea>
                </label>

                <div class="seo-switches">
                    <label class="choice">
                        <input type="checkbox" name="robots_index" value="1" <?= !empty($form['robots_index']) ? 'checked' : '' ?>>
                        <span><strong>Показывать в поисковых системах</strong><small>Отключайте только для материалов, которые не должны индексироваться.</small></span>
                    </label>
                    <label class="choice">
                        <input type="checkbox" name="robots_follow" value="1" <?= !empty($form['robots_follow']) ? 'checked' : '' ?>>
                        <span><strong>Разрешить переход по ссылкам</strong><small>Обычно оставляется включённым.</small></span>
                    </label>
                </div>

                <label class="field">
                    <span>Канонический адрес <small>для специалистов</small></span>
                    <input name="canonical_url" value="<?= $theme->e($form['canonical_url'] ?? '') ?>" placeholder="обычно оставьте пустым">
                    <small>Нужен только если исходная версия материала находится по другому постоянному URL.</small>
                </label>
            </div>
        </details>

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
