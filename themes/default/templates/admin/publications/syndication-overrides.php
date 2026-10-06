<?php declare(strict_types=1);
$targetLabels = [
    'rss' => 'RSS 2.0',
    'rambler' => 'Rambler',
];
$targets = is_array($targets ?? null) ? $targets : [];
$overrides = is_array($overrides ?? null) ? $overrides : [];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешнее распространение</p>
            <h1>Переопределения каналов</h1>
            <p><?= $theme->e($publication->title) ?></p>
        </div>
        <div class="admin-heading__actions">
            <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publication_edit', ['publicId' => $publication->publicId])) ?>">← К публикации</a>
        </div>
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

    <?php if ($targets === []): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Каналы не выбраны',
            'message' => 'Сначала выберите каналы внешнего распространения в редакторе публикации.',
            'action_href' => $theme->route('admin_publication_edit', ['publicId' => $publication->publicId]),
            'action_label' => 'Открыть публикацию',
        ]) ?>
    <?php else: ?>
        <form class="publication-editor" method="post" action="<?= $theme->e($theme->route('admin_publication_syndication_overrides_update', ['publicId' => $publication->publicId])) ?>">
            <?= $theme->csrfInput() ?>

            <?php foreach ($targets as $target): ?>
                <?php $values = is_array($overrides[$target] ?? null) ? $overrides[$target] : []; ?>
                <section class="editor-card">
                    <div class="editor-card__heading">
                        <div>
                            <p class="eyebrow">Канал</p>
                            <h2><?= $theme->e($targetLabels[$target] ?? $target) ?></h2>
                        </div>
                    </div>

                    <label class="field">
                        <span>Заголовок <small>необязательно</small></span>
                        <input
                            name="title[<?= $theme->e($target) ?>]"
                            value="<?= $theme->e($values['title'] ?? '') ?>"
                            maxlength="255"
                            placeholder="Если пусто — используется общий заголовок синдикации или публикации"
                        >
                    </label>

                    <label class="field">
                        <span>Краткое описание <small>необязательно</small></span>
                        <textarea
                            name="excerpt[<?= $theme->e($target) ?>]"
                            rows="4"
                            maxlength="4000"
                            placeholder="Если пусто — используется общее описание синдикации или публикации"
                        ><?= $theme->e($values['excerpt'] ?? '') ?></textarea>
                    </label>

                    <div class="editor-grid">
                        <label class="field">
                            <span>URL изображения <small>HTTPS, необязательно</small></span>
                            <input
                                name="image_url[<?= $theme->e($target) ?>]"
                                value="<?= $theme->e($values['image_url'] ?? '') ?>"
                                maxlength="2048"
                                inputmode="url"
                                placeholder="https://example.org/image.jpg"
                            >
                        </label>

                        <label class="field">
                            <span>MIME-тип изображения <small>необязательно</small></span>
                            <input
                                name="image_mime[<?= $theme->e($target) ?>]"
                                value="<?= $theme->e($values['image_mime'] ?? '') ?>"
                                maxlength="128"
                                placeholder="image/jpeg"
                            >
                        </label>
                    </div>
                </section>
            <?php endforeach; ?>

            <div class="admin-actions">
                <button class="button button--primary" type="submit">Сохранить переопределения</button>
                <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_publication_edit', ['publicId' => $publication->publicId])) ?>">Отмена</a>
            </div>
        </form>
    <?php endif; ?>
</section>
