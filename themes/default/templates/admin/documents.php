<?php

declare(strict_types=1);

$documentRows = is_array($documentRows ?? null)
    ? $documentRows
    : [];
$createOrganizationUnits = is_array(
    $createOrganizationUnits ?? null
) ? $createOrganizationUnits : [];
$editOrganizationUnits = is_array(
    $editOrganizationUnits ?? null
) ? $editOrganizationUnits : [];
$availableFiles = is_array($availableFiles ?? null)
    ? $availableFiles
    : [];
$canCreate = ($canCreate ?? false) === true;
$canEdit = ($canEdit ?? false) === true;
$canPublish = ($canPublish ?? false) === true;
$defaultOwnerPublicId = (string) (
    $defaultOwnerPublicId ?? ''
);
$status = is_array($documentStatus ?? null)
    ? $documentStatus
    : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Документы</h1>
            <p>Карточки документов используют проверенные файлы из медиатеки и organization-scoped доступ.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <?php if ($canCreate): ?>
        <section class="admin-operations-section" aria-labelledby="document-create-title">
            <div class="admin-operations-heading">
                <div>
                    <p class="card__eyebrow">Новый документ</p>
                    <h2 id="document-create-title">Создать черновик</h2>
                </div>
            </div>

            <form
                class="admin-update-apply"
                method="post"
                action="<?= $theme->e($theme->route('admin_documents_create')) ?>"
            >
                <?= $theme->csrfInput() ?>

                <label>
                    <span>Организация-владелец</span>
                    <select name="owner_organization_public_id" required>
                        <option value="">Выберите организацию</option>
                        <?php foreach ($createOrganizationUnits as $unit): ?>
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
                    >
                </label>

                <label>
                    <span>Тип</span>
                    <input
                        type="text"
                        name="document_type"
                        value="document"
                        maxlength="64"
                        required
                    >
                </label>

                <label>
                    <span>Номер <small>необязательно</small></span>
                    <input
                        type="text"
                        name="document_number"
                        maxlength="120"
                    >
                </label>

                <label>
                    <span>Дата документа <small>необязательно</small></span>
                    <input
                        type="date"
                        name="issued_on"
                    >
                </label>

                <label>
                    <span>Краткое описание</span>
                    <textarea
                        name="summary"
                        rows="4"
                        maxlength="4000"
                    ></textarea>
                </label>

                <label>
                    <span>Рубрики <small>необязательно</small></span>
                    <input
                        type="text"
                        name="categories"
                        maxlength="1000"
                        placeholder="Например: Распоряжения, Приказы"
                    >
                    <small>До восьми рубрик, разделяйте запятыми.</small>
                </label>

                <label>
                    <span>Файл из медиатеки <small>необязательно</small></span>
                    <select name="media_public_id">
                        <option value="">Без файла</option>
                        <?php foreach ($availableFiles as $file): ?>
                            <option value="<?= $theme->e((string) ($file['public_id'] ?? '')) ?>">
                                <?= $theme->e((string) ($file['title'] ?? 'Файл')) ?>
                                · <?= $theme->e((string) ($file['mime_type'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button class="button button--primary" type="submit">
                    Создать документ
                </button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="document-list-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Каталог</p>
                <h2 id="document-list-title">Карточки документов</h2>
            </div>
        </div>

        <?php if ($documentRows === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Документов пока нет',
                'message' => 'Создайте первый документ через форму выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($documentRows as $row): ?>
                    <?php
                    $document = $row['document'];
                    $fileMediaPublicId = (string) (
                        $row['media_public_id'] ?? ''
                    );
                    $categoryNames = (string) (
                        $row['category_names'] ?? ''
                    );
                    ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($document->title) ?></strong>
                            <span>
                                <?= $theme->e($document->status) ?>
                                · <?= $theme->e($document->visibility) ?>
                                · <?= $theme->e($document->documentType) ?>
                                <?php if ($document->documentNumber !== null): ?>
                                    · № <?= $theme->e($document->documentNumber) ?>
                                <?php endif; ?>
                                <?php if ($document->issuedOn !== null): ?>
                                    · <?= $theme->e($document->issuedOn) ?>
                                <?php endif; ?>
                            </span>
                            <small>
                                владелец: <?= $theme->e($document->ownerOrganizationPublicId) ?>
                                <?php if ($categoryNames !== ''): ?>
                                    · рубрики: <?= $theme->e($categoryNames) ?>
                                <?php endif; ?>
                                · ID: <?= $theme->e($document->publicId) ?>
                            </small>
                        </div>

                        <?php if ($canEdit && $document->status !== 'archived'): ?>
                            <form
                                class="admin-update-apply"
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_documents_update',
                                    ['publicId' => $document->publicId],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>

                                <label>
                                    <span>Организация-владелец</span>
                                    <select name="owner_organization_public_id" required>
                                        <?php foreach ($editOrganizationUnits as $unit): ?>
                                            <option
                                                value="<?= $theme->e($unit->publicId) ?>"
                                                <?= $unit->publicId === $document->ownerOrganizationPublicId ? 'selected' : '' ?>
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
                                        value="<?= $theme->e($document->title) ?>"
                                    >
                                </label>

                                <label>
                                    <span>Тип</span>
                                    <input
                                        type="text"
                                        name="document_type"
                                        maxlength="64"
                                        required
                                        value="<?= $theme->e($document->documentType) ?>"
                                    >
                                </label>

                                <label>
                                    <span>Номер</span>
                                    <input
                                        type="text"
                                        name="document_number"
                                        maxlength="120"
                                        value="<?= $theme->e($document->documentNumber ?? '') ?>"
                                    >
                                </label>

                                <label>
                                    <span>Дата документа</span>
                                    <input
                                        type="date"
                                        name="issued_on"
                                        value="<?= $theme->e($document->issuedOn ?? '') ?>"
                                    >
                                </label>

                                <label>
                                    <span>Краткое описание</span>
                                    <textarea
                                        name="summary"
                                        rows="4"
                                        maxlength="4000"
                                    ><?= $theme->e($document->summary) ?></textarea>
                                </label>

                                <label>
                                    <span>Рубрики <small>необязательно</small></span>
                                    <input
                                        type="text"
                                        name="categories"
                                        maxlength="1000"
                                        value="<?= $theme->e($categoryNames) ?>"
                                        placeholder="Например: Распоряжения, Приказы"
                                    >
                                    <small>До восьми рубрик, разделяйте запятыми.</small>
                                </label>

                                <label>
                                    <span>Файл из медиатеки</span>
                                    <select name="media_public_id">
                                        <option value="">Без файла</option>
                                        <?php foreach ($availableFiles as $file): ?>
                                            <?php $fileId = (string) ($file['public_id'] ?? ''); ?>
                                            <option
                                                value="<?= $theme->e($fileId) ?>"
                                                <?= $fileId === $fileMediaPublicId ? 'selected' : '' ?>
                                            >
                                                <?= $theme->e((string) ($file['title'] ?? 'Файл')) ?>
                                                · <?= $theme->e((string) ($file['mime_type'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

                                <button class="button button--primary" type="submit">
                                    Сохранить
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="admin-actions">
                            <?php if ($canPublish && $document->status !== 'archived'): ?>
                                <?php if ($document->status === 'published'): ?>
                                    <form
                                        method="post"
                                        action="<?= $theme->e($theme->route(
                                            'admin_documents_withdraw',
                                            ['publicId' => $document->publicId],
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
                                            'admin_documents_publish',
                                            ['publicId' => $document->publicId],
                                        )) ?>"
                                    >
                                        <?= $theme->csrfInput() ?>
                                        <button class="button button--quiet" type="submit">
                                            Опубликовать
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($canEdit && $document->status !== 'archived'): ?>
                                <form
                                    method="post"
                                    action="<?= $theme->e($theme->route(
                                        'admin_documents_archive',
                                        ['publicId' => $document->publicId],
                                    )) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <button class="button button--quiet" type="submit">
                                        Архивировать
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
