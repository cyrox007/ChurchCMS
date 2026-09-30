<?php

declare(strict_types=1);

$assets = is_array($assets ?? null) ? $assets : [];
$organizationUnits = is_array($organizationUnits ?? null)
    ? $organizationUnits
    : [];
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
$status = is_array($mediaStatus ?? null) ? $mediaStatus : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Медиатека</h1>
            <p>Файлы проверяются по содержимому и сохраняются вне корня ChurchCMS.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="media-upload-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новый файл</p>
                <h2 id="media-upload-title">Загрузить в медиатеку</h2>
                <p>Разрешены изображения, базовые аудио/видео форматы и PDF. SVG, HTML и архивы пока не принимаются.</p>
            </div>
        </div>

        <form
            class="admin-update-apply"
            method="post"
            enctype="multipart/form-data"
            action="<?= $theme->e($theme->route('admin_media_upload')) ?>"
        >
            <?= $theme->csrfInput() ?>

            <label>
                <span>Организация-владелец</span>
                <select name="owner_organization_public_id" required>
                    <option value="">Выберите организацию</option>
                    <?php foreach ($organizationUnits as $unit): ?>
                        <option
                            value="<?= $theme->e($unit->publicId) ?>"
                            <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>
                        >
                            <?= $theme->e($unit->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Файл</span>
                <input
                    type="file"
                    name="media_file"
                    required
                >
            </label>

            <label>
                <span>Название <small>необязательно</small></span>
                <input
                    type="text"
                    name="title"
                    maxlength="255"
                    autocomplete="off"
                >
            </label>

            <label>
                <span>Альтернативное описание <small>необязательно</small></span>
                <textarea
                    name="alt_text"
                    maxlength="500"
                    rows="3"
                ></textarea>
            </label>

            <button class="button button--primary" type="submit">
                Проверить и загрузить
            </button>
        </form>
    </section>

    <section class="admin-operations-section" aria-labelledby="media-list-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Файлы</p>
                <h2 id="media-list-title">Доступные медиаматериалы</h2>
            </div>
        </div>

        <?php if ($assets === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Медиатека пуста',
                'message' => 'Загрузите первый файл через форму выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($assets as $asset): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong>
                                <?= $theme->e($asset->title ?? $asset->originalName) ?>
                            </strong>
                            <span>
                                <?= $theme->e($asset->mediaType) ?>
                                · <?= $theme->e($asset->mimeType) ?>
                                · <?= $theme->e(number_format($asset->bytes, 0, '.', ' ')) ?> байт
                                <?php if ($asset->pixelWidth !== null && $asset->pixelHeight !== null): ?>
                                    · <?= $theme->e($asset->pixelWidth) ?>×<?= $theme->e($asset->pixelHeight) ?> px
                                <?php endif; ?>
                            </span>
                            <small>
                                владелец: <?= $theme->e($asset->ownerOrganizationPublicId) ?>
                                · видимость: <?= $theme->e($asset->visibility) ?>
                            </small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
