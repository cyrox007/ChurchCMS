<?php

declare(strict_types=1);

$assets = is_array($assets ?? null) ? $assets : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
$status = is_array($mediaStatus ?? null) ? $mediaStatus : null;
$storageReport = is_array($storageReport ?? null) ? $storageReport : null;
$archiveStatus = is_array($archiveStatus ?? null) ? $archiveStatus : null;
$formatBytes = static function (int $bytes): string {
    $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    $value = max(0, $bytes);
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return number_format($value, $unit === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unit];
};
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Медиатека</h1>
            <p>Файлы проверяются по содержимому, дедуплицируются по SHA-256 и могут безопасно переноситься во внешний архив.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="media-storage-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Хранилище</p>
                <h2 id="media-storage-title">Использование диска и архив</h2>
                <p>Старые Media остаются доступны: архивная копия автоматически восстанавливается в локальный cache при запросе.</p>
            </div>
        </div>

        <?php if ($storageReport === null): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'error',
                'title' => 'Статистика хранилища недоступна',
                'message' => 'Проверьте миграции Media и настройки storage_path.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <article class="admin-operations-row" role="listitem">
                    <div class="admin-operations-row__body">
                        <strong>Локально: <?= $theme->e($formatBytes((int) ($storageReport['local_bytes'] ?? 0))) ?></strong>
                        <span>
                            логический объём: <?= $theme->e($formatBytes((int) ($storageReport['logical_bytes'] ?? 0))) ?>
                            · уникальные blob: <?= $theme->e($formatBytes((int) ($storageReport['unique_blob_bytes'] ?? 0))) ?>
                            · производные: <?= $theme->e($formatBytes((int) ($storageReport['derivative_bytes'] ?? 0))) ?>
                        </span>
                        <small>
                            экономия дедупликацией: <?= $theme->e($formatBytes((int) ($storageReport['dedup_saved_bytes'] ?? 0))) ?>
                            · осиротевшие файлы: <?= $theme->e((int) ($storageReport['orphan_count'] ?? 0)) ?>
                            · потерянные blob: <?= $theme->e((int) ($storageReport['missing_count'] ?? 0)) ?>
                            · только в архиве: <?= $theme->e((int) ($storageReport['archived_only_count'] ?? 0)) ?>
                        </small>
                    </div>
                </article>
                <article class="admin-operations-row" role="listitem">
                    <div class="admin-operations-row__body">
                        <strong>
                            Внешний архив: <?= ($archiveStatus['enabled'] ?? false) ? 'включён' : 'не настроен' ?>
                        </strong>
                        <span>
                            архивировано: <?= $theme->e($formatBytes((int) ($storageReport['archive_bytes'] ?? 0))) ?>
                            <?php if (($archiveStatus['backend'] ?? null) !== null): ?>
                                · backend: <?= $theme->e((string) $archiveStatus['backend']) ?>
                            <?php endif; ?>
                        </span>
                        <small>
                            <?php if (($storageReport['growth_bytes_per_day'] ?? null) !== null): ?>
                                рост локального Media: <?= $theme->e($formatBytes((int) $storageReport['growth_bytes_per_day'])) ?>/сутки
                            <?php else: ?>
                                Для прогноза заполнения нужны минимум два снимка использования за разные дни.
                            <?php endif; ?>
                            <?php if (($storageReport['estimated_days_to_full'] ?? null) !== null): ?>
                                · оценка до заполнения: <?= $theme->e((int) $storageReport['estimated_days_to_full']) ?> дн.
                            <?php endif; ?>
                        </small>
                    </div>
                </article>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-operations-section" aria-labelledby="media-upload-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новый файл</p>
                <h2 id="media-upload-title">Загрузить в медиатеку</h2>
                <p>Разрешены изображения, базовые аудио/видео форматы и PDF. SVG, HTML и архивы пока не принимаются.</p>
            </div>
        </div>

        <form class="admin-update-apply" method="post" enctype="multipart/form-data" action="<?= $theme->e($theme->route('admin_media_upload')) ?>">
            <?= $theme->csrfInput() ?>
            <label>
                <span>Организация-владелец</span>
                <select name="owner_organization_public_id" required>
                    <option value="">Выберите организацию</option>
                    <?php foreach ($organizationUnits as $unit): ?>
                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>>
                            <?= $theme->e($unit->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Файл</span>
                <input type="file" name="media_file" required>
            </label>
            <label>
                <span>Название <small>необязательно</small></span>
                <input type="text" name="title" maxlength="255" autocomplete="off">
            </label>
            <label>
                <span>Альтернативное описание <small>необязательно</small></span>
                <textarea name="alt_text" maxlength="500" rows="3"></textarea>
            </label>
            <button class="button button--primary" type="submit">Проверить и загрузить</button>
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
                            <strong><?= $theme->e($asset->title ?? $asset->originalName) ?></strong>
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

                        <?php if ($asset->status !== 'archived'): ?>
                            <form method="post" action="<?= $theme->e($theme->route('admin_media_visibility', ['publicId' => $asset->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label>
                                    <span>Видимость</span>
                                    <select name="visibility" required>
                                        <?php foreach ([
                                            'private' => 'Приватно',
                                            'public' => 'Публично на сайте',
                                            'federated' => 'Для федерации',
                                        ] as $value => $label): ?>
                                            <option value="<?= $theme->e($value) ?>" <?= $asset->visibility === $value ? 'selected' : '' ?>>
                                                <?= $theme->e($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="button button--quiet" type="submit">Сохранить видимость</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
