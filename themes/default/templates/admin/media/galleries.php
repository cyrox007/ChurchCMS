<?php

declare(strict_types=1);

$galleries = is_array($galleries ?? null) ? $galleries : [];
$images = is_array($images ?? null) ? $images : [];
$organizationUnits = is_array($organizationUnits ?? null)
    ? $organizationUnits
    : [];
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
$status = is_array($galleryStatus ?? null) ? $galleryStatus : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Галереи</h1>
            <p>Галерея использует изображения из медиатеки и не хранит отдельные файловые пути.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="gallery-create-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новая галерея</p>
                <h2 id="gallery-create-title">Создать черновик</h2>
                <p>После создания добавьте изображения и только затем публикуйте галерею.</p>
            </div>
        </div>

        <form
            class="admin-update-apply"
            method="post"
            action="<?= $theme->e($theme->route('admin_media_galleries_create')) ?>"
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
                <span>Название</span>
                <input
                    type="text"
                    name="title"
                    maxlength="255"
                    required
                    autocomplete="off"
                >
            </label>

            <label>
                <span>Описание <small>необязательно</small></span>
                <textarea
                    name="description"
                    rows="3"
                    maxlength="4000"
                ></textarea>
            </label>

            <button class="button button--primary" type="submit">
                Создать галерею
            </button>
        </form>
    </section>

    <section class="admin-operations-section" aria-labelledby="gallery-list-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Редактор</p>
                <h2 id="gallery-list-title">Галереи</h2>
                <p>Порядок задаётся числом: меньшее значение выводится раньше.</p>
            </div>
        </div>

        <?php if ($galleries === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Галерей пока нет',
                'message' => 'Создайте первую галерею через форму выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($galleries as $row): ?>
                    <?php
                    $gallery = $row['gallery'];
                    $items = is_array($row['items'] ?? null)
                        ? $row['items']
                        : [];
                    $selected = [];
                    foreach ($items as $index => $item) {
                        $selected[$item->publicId] = $index + 1;
                    }
                    ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($gallery->title) ?></strong>
                            <span>
                                <?= $theme->e($gallery->status) ?>
                                · <?= $theme->e($gallery->visibility) ?>
                                · изображений: <?= $theme->e(count($items)) ?>
                            </span>
                            <small>
                                владелец: <?= $theme->e($gallery->ownerOrganizationPublicId) ?>
                                · ID: <?= $theme->e($gallery->publicId) ?>
                            </small>
                        </div>

                        <?php if ($gallery->status !== 'archived'): ?>
                            <form
                                class="admin-update-apply"
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_media_galleries_update',
                                    ['publicId' => $gallery->publicId],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>

                                <label>
                                    <span>Название</span>
                                    <input
                                        type="text"
                                        name="title"
                                        maxlength="255"
                                        required
                                        value="<?= $theme->e($gallery->title) ?>"
                                    >
                                </label>

                                <label>
                                    <span>Описание</span>
                                    <textarea
                                        name="description"
                                        rows="3"
                                        maxlength="4000"
                                    ><?= $theme->e($gallery->description) ?></textarea>
                                </label>

                                <?php if ($images === []): ?>
                                    <?= $theme->component('admin.state', [
                                        'kind' => 'empty',
                                        'title' => 'Нет изображений',
                                        'message' => 'Сначала загрузите изображения в медиатеку.',
                                    ]) ?>
                                <?php else: ?>
                                    <div class="choice-list">
                                        <?php foreach ($images as $image): ?>
                                            <?php
                                            $order = $selected[$image->publicId] ?? null;
                                            ?>
                                            <div class="external-channel-editor">
                                                <label class="choice">
                                                    <input
                                                        type="checkbox"
                                                        name="gallery_items[]"
                                                        value="<?= $theme->e($image->publicId) ?>"
                                                        <?= $order !== null ? 'checked' : '' ?>
                                                    >
                                                    <span>
                                                        <strong>
                                                            <?= $theme->e($image->title ?? $image->originalName) ?>
                                                        </strong>
                                                        <small>
                                                            <?= $theme->e($image->mimeType) ?>
                                                            · видимость: <?= $theme->e($image->visibility) ?>
                                                            <?php if ($image->pixelWidth !== null && $image->pixelHeight !== null): ?>
                                                                · <?= $theme->e($image->pixelWidth) ?>×<?= $theme->e($image->pixelHeight) ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </span>
                                                </label>

                                                <label class="field">
                                                    <span>Порядок</span>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max="999999"
                                                        name="gallery_order[<?= $theme->e($image->publicId) ?>]"
                                                        value="<?= $theme->e($order ?? '') ?>"
                                                        inputmode="numeric"
                                                    >
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <button class="button button--primary" type="submit">
                                    Сохранить карточку и состав
                                </button>
                            </form>

                            <div class="admin-actions">
                                <?php if ($gallery->status === 'published'): ?>
                                    <form
                                        method="post"
                                        action="<?= $theme->e($theme->route(
                                            'admin_media_galleries_withdraw',
                                            ['publicId' => $gallery->publicId],
                                        )) ?>"
                                    >
                                        <?= $theme->csrfInput() ?>
                                        <button class="button button--quiet" type="submit">
                                            Снять с публикации
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form
                                        method="post"
                                        action="<?= $theme->e($theme->route(
                                            'admin_media_galleries_publish',
                                            ['publicId' => $gallery->publicId],
                                        )) ?>"
                                    >
                                        <?= $theme->csrfInput() ?>
                                        <button class="button button--quiet" type="submit">
                                            Опубликовать
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form
                                    method="post"
                                    action="<?= $theme->e($theme->route(
                                        'admin_media_galleries_archive',
                                        ['publicId' => $gallery->publicId],
                                    )) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <button class="button button--quiet" type="submit">
                                        Архивировать
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
