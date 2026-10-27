<?php

declare(strict_types=1);

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
$availableTargets = !empty($canSyndicate)
    ? \ChurchCMS\Modules\Publications\PublicationSyndicationProvider::editorTargets(
        $publication?->siteKey ?? 'default',
    )
    : [];
$externalChannels = is_array($externalChannels ?? null)
    ? $externalChannels
    : [];
$canExternalPublish = ($canExternalPublish ?? false) === true;
$externalStatusLabels = [
    'idle' => 'ожидает публикации',
    'pending' => 'в очереди',
    'processing' => 'отправляется',
    'sent' => 'отправлено',
    'failed' => 'ошибка отправки',
];
$formAction = $isEdit
    ? $theme->route(
        'admin_publication_update',
        ['publicId' => $publication->publicId],
    )
    : $theme->route('admin_publication_create');
$revisions = is_array($revisions ?? null) ? $revisions : [];
$seoMediaOptions = is_array($seoMediaOptions ?? null)
    ? $seoMediaOptions
    : [];
$seoImages = is_array($seoMediaOptions['images'] ?? null)
    ? $seoMediaOptions['images']
    : [];
$seoVideos = is_array($seoMediaOptions['videos'] ?? null)
    ? $seoMediaOptions['videos']
    : [];
$bodyIsStoredHtml = $isEdit && empty($error);
$editorBody = $bodyIsStoredHtml
    ? $publication->bodyHtml
    : (string) ($form['body'] ?? '');
?>
<section class="admin-shell publication-editor-page">
    <header class="admin-heading">
        <div>
            <p class="eyebrow"><?= $isEdit ? 'Редактирование' : 'Новый материал' ?></p>
            <h1><?= $isEdit ? $theme->e($publication->title) : 'Новая публикация' ?></h1>
            <p>
                <?= $isEdit
                    ? 'Сохранение не меняет статус публикации.'
                    : 'Сначала сохраните черновик. На сайт он автоматически не попадёт.' ?>
            </p>
        </div>
        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publications')) ?>">
            ← К публикациям
        </a>
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
                <input
                    name="title"
                    value="<?= $theme->e($form['title'] ?? '') ?>"
                    required
                    maxlength="255"
                    autofocus
                >
            </label>

            <div class="editor-grid">
                <label class="field">
                    <span>Тип материала</span>
                    <select name="type">
                        <?php foreach ($typeLabels as $value => $label): ?>
                            <option
                                value="<?= $theme->e($value) ?>"
                                <?= ($form['type'] ?? 'news') === $value ? 'selected' : '' ?>
                            ><?= $theme->e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span>Автор <small>необязательно</small></span>
                    <input
                        name="author_name"
                        value="<?= $theme->e($form['author_name'] ?? '') ?>"
                        maxlength="255"
                    >
                </label>
            </div>

            <label class="field">
                <span>Организация-владелец</span>
                <select name="owner_organization_public_id" required>
                    <option value="">Выберите организацию</option>
                    <?php foreach (($organizationUnits ?? []) as $organization): ?>
                        <option
                            value="<?= $theme->e($organization->publicId) ?>"
                            <?= ($form['owner_organization_public_id'] ?? '') === $organization->publicId ? 'selected' : '' ?>
                        >
                            <?= $theme->e($organization->name . ' · ' . $organization->path) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="field__hint">Доступны только активные организации в пределах ваших прав.</small>
            </label>

            <div class="editor-grid">
                <label class="field">
                    <span>Категории <small>через запятую</small></span>
                    <input
                        name="categories"
                        value="<?= $theme->e($form['categories'] ?? '') ?>"
                        maxlength="1000"
                        placeholder="Новости прихода, Богослужение"
                    >
                </label>

                <label class="field">
                    <span>Теги <small>через запятую</small></span>
                    <input
                        name="tags"
                        value="<?= $theme->e($form['tags'] ?? '') ?>"
                        maxlength="2000"
                        placeholder="Пасха, молодёжь, благотворительность"
                    >
                </label>
            </div>

            <label class="field">
                <span>Короткое описание</span>
                <textarea
                    name="excerpt"
                    rows="3"
                    placeholder="Короткий анонс для списков и поисковых систем"
                ><?= $theme->e($form['excerpt'] ?? '') ?></textarea>
            </label>

            <label class="field">
                <span>Текст публикации</span>
                <textarea
                    class="editor-body"
                    name="body"
                    rows="12"
                    required
                    data-rich-editor-source
                    data-rich-editor-format="<?= $bodyIsStoredHtml ? 'html' : 'text' ?>"
                ><?= $theme->e($editorBody) ?></textarea>
                <small>
                    Форматирование очищается на сервере: опасная разметка и скрипты не сохраняются.
                </small>
            </label>
        </section>

        <section class="editor-card">
            <div class="setting-row">
                <div>
                    <strong>Комментарии</strong>
                    <p>Разрешить комментарии к этой публикации. Новые сообщения проходят установленную модерацию.</p>
                </div>
                <label class="switch">
                    <input
                        type="checkbox"
                        name="comments_enabled"
                        value="1"
                        <?= !empty($form['comments_enabled']) ? 'checked' : '' ?>
                    >
                    <span>Разрешить</span>
                </label>
            </div>
        </section>

        <?php if ($availableTargets !== []): ?>
            <section class="editor-card">
                <h2>Распространение</h2>
                <p class="editor-card__hint">
                    Здесь показываются только включённые или реально подключённые каналы.
                </p>
                <div class="choice-list">
                    <?php foreach ($availableTargets as $target => $targetData): ?>
                        <label class="choice">
                            <input
                                type="checkbox"
                                name="syndication_targets[]"
                                value="<?= $theme->e($target) ?>"
                                <?= in_array($target, $currentTargets, true) ? 'checked' : '' ?>
                            >
                            <span>
                                <strong><?= $theme->e($targetData['label']) ?></strong>
                                <small><?= $theme->e($targetData['hint']) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif (!empty($canSyndicate)): ?>
            <section class="editor-card editor-card--distribution-empty">
                <h2>Распространение</h2>
                <p>
                    Дополнительные каналы не подключены. Они появятся здесь автоматически после настройки соответствующей связи или партнёрского канала.
                </p>
            </section>
        <?php endif; ?>

        <?php if ($isEdit && $canExternalPublish && $externalChannels !== []): ?>
            <section class="editor-card">
                <h2>Внешние каналы</h2>
                <p class="editor-card__hint">
                    Площадки показаны только после их подключения в настройках сайта.
                </p>

                <div class="choice-list">
                    <?php foreach ($externalChannels as $channel): ?>
                        <?php
                        $status = (string) ($channel['status'] ?? '');
                        $statusLabel = $externalStatusLabels[$status] ?? '';
                        $channelPublicId = (string) ($channel['public_id'] ?? '');
                        ?>
                        <div class="external-channel-editor">
                            <label class="choice">
                                <input
                                    type="checkbox"
                                    name="external_channel_ids[]"
                                    value="<?= $theme->e($channelPublicId) ?>"
                                    <?= !empty($channel['selected']) ? 'checked' : '' ?>
                                >
                                <span>
                                    <strong><?= $theme->e((string) ($channel['name'] ?? 'Внешний канал')) ?></strong>
                                    <small>
                                        <?= $theme->e((string) ($channel['provider'] ?? '')) ?>
                                        <?php if (!empty($channel['target_ref'])): ?>
                                            · <?= $theme->e((string) $channel['target_ref']) ?>
                                        <?php endif; ?>
                                        <?php if ($statusLabel !== ''): ?>
                                            · <?= $theme->e($statusLabel) ?>
                                        <?php endif; ?>
                                    </small>
                                </span>
                            </label>

                            <label class="field">
                                <span>Текст для этого канала <small>необязательно</small></span>
                                <textarea
                                    name="external_channel_text[<?= $theme->e($channelPublicId) ?>]"
                                    rows="2"
                                    maxlength="5000"
                                ><?= $theme->e((string) ($channel['custom_text'] ?? '')) ?></textarea>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <details class="editor-card seo-editor">
            <summary>
                <span>SEO и карточка в соцсетях</span>
                <small>Обычно эти поля заполнять не требуется.</small>
            </summary>

            <div class="seo-editor__body">
                <div class="editor-grid">
                    <label class="field">
                        <span>Заголовок для поиска</span>
                        <input
                            name="seo_title"
                            value="<?= $theme->e($form['seo_title'] ?? '') ?>"
                            maxlength="255"
                            placeholder="По умолчанию — заголовок публикации"
                        >
                    </label>
                    <label class="field">
                        <span>Ключевые слова</span>
                        <input
                            name="seo_keywords"
                            value="<?= $theme->e($form['seo_keywords'] ?? '') ?>"
                            maxlength="1000"
                            placeholder="через запятую"
                        >
                    </label>
                </div>

                <label class="field">
                    <span>Описание для поисковых систем</span>
                    <textarea name="seo_description" rows="3" maxlength="500"><?= $theme->e($form['seo_description'] ?? '') ?></textarea>
                </label>

                <div class="seo-editor__divider"></div>
                <h3>Карточка ссылки</h3>

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

                <?php if ($seoImages !== [] || $seoVideos !== []): ?>
                    <div class="seo-editor__divider"></div>
                    <h3>Медиа для поисковых систем</h3>

                    <?php if ($seoImages !== []): ?>
                        <label class="field">
                            <span>Основное изображение</span>
                            <select name="seo_image_media_id">
                                <option value="">Автоматически</option>
                                <?php foreach ($seoImages as $asset): ?>
                                    <?php $assetId = (string) ($asset['public_id'] ?? ''); ?>
                                    <option
                                        value="<?= $theme->e($assetId) ?>"
                                        <?= ($form['seo_image_media_id'] ?? '') === $assetId ? 'selected' : '' ?>
                                    ><?= $theme->e((string) ($asset['title'] ?? $assetId)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>

                    <?php if ($seoVideos !== []): ?>
                        <div class="editor-grid">
                            <label class="field">
                                <span>SEO-видео</span>
                                <select name="seo_video_media_id">
                                    <option value="">Без отдельного видео</option>
                                    <?php foreach ($seoVideos as $asset): ?>
                                        <?php $assetId = (string) ($asset['public_id'] ?? ''); ?>
                                        <option
                                            value="<?= $theme->e($assetId) ?>"
                                            <?= ($form['seo_video_media_id'] ?? '') === $assetId ? 'selected' : '' ?>
                                        ><?= $theme->e((string) ($asset['title'] ?? $assetId)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Превью видео</span>
                                <select name="seo_video_thumbnail_media_id">
                                    <option value="">Выберите изображение</option>
                                    <?php foreach ($seoImages as $asset): ?>
                                        <?php $assetId = (string) ($asset['public_id'] ?? ''); ?>
                                        <option
                                            value="<?= $theme->e($assetId) ?>"
                                            <?= ($form['seo_video_thumbnail_media_id'] ?? '') === $assetId ? 'selected' : '' ?>
                                        ><?= $theme->e((string) ($asset['title'] ?? $assetId)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="seo-switches">
                    <label class="choice">
                        <input type="checkbox" name="robots_index" value="1" <?= !empty($form['robots_index']) ? 'checked' : '' ?>>
                        <span><strong>Показывать в поисковых системах</strong></span>
                    </label>
                    <label class="choice">
                        <input type="checkbox" name="robots_follow" value="1" <?= !empty($form['robots_follow']) ? 'checked' : '' ?>>
                        <span><strong>Разрешить переход по ссылкам</strong></span>
                    </label>
                </div>

                <label class="field">
                    <span>Канонический адрес <small>для специалистов</small></span>
                    <input name="canonical_url" value="<?= $theme->e($form['canonical_url'] ?? '') ?>" placeholder="Обычно оставьте пустым">
                </label>
            </div>
        </details>

        <details class="editor-card editor-advanced">
            <summary>Дополнительно</summary>
            <div class="seo-editor__body">
                <label class="field">
                    <span>Адрес материала</span>
                    <input
                        name="slug"
                        value="<?= $theme->e($form['slug'] ?? '') ?>"
                        maxlength="180"
                        placeholder="Создастся автоматически"
                    >
                    <small>Обычно менять это поле не требуется.</small>
                </label>
            </div>
        </details>

        <div class="editor-actions">
            <button class="button button--primary" type="submit">
                <?= $isEdit ? 'Сохранить изменения' : 'Сохранить черновик' ?>
            </button>
        </div>
    </form>

    <?php if ($isEdit && !empty($canPublish)): ?>
        <section class="publication-state-actions">
            <?php if ($publication->status->value === 'published'): ?>
                <a
                    class="button button--quiet"
                    href="<?= $theme->e($theme->route('publication_show', ['slug' => $publication->slug])) ?>"
                    target="_blank"
                    rel="noopener"
                >Посмотреть на сайте</a>
                <form method="post" action="<?= $theme->e($theme->route('admin_publication_withdraw', ['publicId' => $publication->publicId])) ?>">
                    <?= $theme->csrfInput() ?>
                    <button class="button button--quiet" type="submit">Снять с публикации</button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= $theme->e($theme->route('admin_publication_publish', ['publicId' => $publication->publicId])) ?>">
                    <?= $theme->csrfInput() ?>
                    <button class="button button--primary" type="submit">Опубликовать сейчас</button>
                </form>

                <?php if ($publication->status->value === 'scheduled'): ?>
                    <div class="publication-schedule-summary">
                        <strong>Публикация запланирована</strong>
                        <span>
                            <?= $theme->e(str_replace('T', ' ', (string) ($scheduledAtLocal ?? ''))) ?>
                            (<?= $theme->e((string) ($scheduleTimezone ?? 'UTC')) ?>)
                        </span>
                    </div>
                    <form method="post" action="<?= $theme->e($theme->route('admin_publication_unschedule', ['publicId' => $publication->publicId])) ?>">
                        <?= $theme->csrfInput() ?>
                        <button class="button button--quiet" type="submit">Отменить планирование</button>
                    </form>
                <?php else: ?>
                    <form class="publication-schedule-form" method="post" action="<?= $theme->e($theme->route('admin_publication_schedule', ['publicId' => $publication->publicId])) ?>">
                        <?= $theme->csrfInput() ?>
                        <label class="field">
                            <span>Опубликовать позже <small><?= $theme->e((string) ($scheduleTimezone ?? 'UTC')) ?></small></span>
                            <input
                                type="datetime-local"
                                name="scheduled_at"
                                value="<?= $theme->e((string) ($scheduledAtLocal ?? '')) ?>"
                                required
                            >
                        </label>
                        <button class="button button--quiet" type="submit">Запланировать</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($isEdit && $revisions !== []): ?>
        <details class="editor-card editor-advanced revision-history">
            <summary>
                История изменений
                <small>версий: <?= $theme->e(count($revisions)) ?></small>
            </summary>
            <div class="seo-editor__body">
                <p class="editor-card__hint">
                    Восстановление создаёт новую текущую версию и не удаляет историю.
                </p>
                <div class="admin-operations-list" role="list">
                    <?php foreach ($revisions as $revision): ?>
                        <?php
                        $snapshotTitle = is_string($revision->snapshot['title'] ?? null)
                            ? (string) $revision->snapshot['title']
                            : 'Без заголовка';
                        ?>
                        <article class="admin-operations-row" role="listitem">
                            <div class="admin-operations-row__body">
                                <strong>
                                    Версия №<?= $theme->e($revision->revisionNumber) ?>
                                    · <?= $theme->e($snapshotTitle) ?>
                                </strong>
                                <span><?= $theme->e($revision->createdAt) ?> UTC</span>
                            </div>
                            <form
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_publication_revision_restore',
                                    [
                                        'publicId' => $publication->publicId,
                                        'revisionPublicId' => $revision->publicId,
                                    ],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>
                                <button class="button button--quiet" type="submit">
                                    Восстановить
                                </button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </details>
    <?php endif; ?>
</section>
