<?php

declare(strict_types=1);

$isEdit = isset($unit)
    && $unit instanceof \ChurchCMS\Modules\Organizations\OrganizationUnit;
$isRoot = $isEdit
    && isset($root)
    && $root instanceof \ChurchCMS\Modules\Organizations\OrganizationUnit
    && $root->id === $unit->id;
$typeLabels = is_array($typeLabels ?? null) ? $typeLabels : [];
$parents = is_array($parents ?? null) ? $parents : [];
$parentLocked = ($parentLocked ?? false) === true;
$lockedParent = isset($lockedParent)
    && $lockedParent instanceof \ChurchCMS\Modules\Organizations\OrganizationUnit
    ? $lockedParent
    : null;
$currentType = (string) ($form['type'] ?? 'parish');

if (
    $currentType !== ''
    && !isset($typeLabels[$currentType])
) {
    $typeLabels[$currentType] =
        $currentType . ' (пользовательский тип)';
}
$formAction = $isEdit
    ? $theme->route(
        'admin_organization_update',
        ['publicId' => $unit->publicId],
    )
    : $theme->route('admin_organization_create');
$status = is_array($organizationStatus ?? null)
    ? $organizationStatus
    : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow"><?= $isEdit ? 'Церковная структура' : 'Новый элемент' ?></p>
            <h1><?= $isEdit ? $theme->e($unit->name) : 'Добавить организацию или подразделение' ?></h1>
            <p>Структура используется как общий контекст для будущих людей, документов, событий, богослужений и материалов.</p>
        </div>

        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_organizations')) ?>">
            ← К структуре
        </a>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'success'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Не удалось сохранить',
            'message' => (string) $error,
        ]) ?>
    <?php endif; ?>

    <form class="organization-editor" method="post" action="<?= $theme->e($formAction) ?>">
        <?= $theme->csrfInput() ?>

        <section class="editor-card editor-card--primary">
            <label class="field field--title">
                <span>Название</span>
                <input
                    name="name"
                    value="<?= $theme->e((string) ($form['name'] ?? '')) ?>"
                    required
                    maxlength="255"
                    autofocus
                >
            </label>

            <div class="editor-grid">
                <label class="field">
                    <span>Тип</span>
                    <select name="type">
                        <?php foreach ($typeLabels as $type => $label): ?>
                            <option
                                value="<?= $theme->e($type) ?>"
                                <?= ($form['type'] ?? 'parish') === $type ? 'selected' : '' ?>
                            >
                                <?= $theme->e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($isRoot): ?>
                    <div class="field">
                        <span>Родитель</span>
                        <strong>Это корневая организация сайта</strong>
                        <small>Корень нельзя переместить под другое подразделение.</small>
                    </div>
                    <input type="hidden" name="parent_public_id" value="">
                <?php elseif ($parentLocked && $lockedParent !== null): ?>
                    <div class="field">
                        <span>Родительская организация</span>
                        <strong><?= $theme->e($lockedParent->name) ?></strong>
                        <small>Родитель находится выше вашей области доступа, поэтому положение этого узла изменить нельзя.</small>
                    </div>
                    <input
                        type="hidden"
                        name="parent_public_id"
                        value="<?= $theme->e($lockedParent->publicId) ?>"
                    >
                <?php else: ?>
                    <label class="field">
                        <span>Родительская организация</span>
                        <select name="parent_public_id" required>
                            <option value="" <?= empty($form['parent_public_id']) ? 'selected' : '' ?> disabled>
                                Выберите родительскую организацию
                            </option>
                            <?php foreach ($parents as $parent): ?>
                                <?php
                                $depth = max(
                                    0,
                                    substr_count(trim($parent->path, '/'), '/'),
                                );
                                $prefix = str_repeat('— ', $depth);
                                ?>
                                <option
                                    value="<?= $theme->e($parent->publicId) ?>"
                                    <?= ($form['parent_public_id'] ?? null) === $parent->publicId ? 'selected' : '' ?>
                                >
                                    <?= $theme->e($prefix . $parent->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Перемещение изменит адрес этого элемента и всего его поддерева.</small>
                    </label>
                <?php endif; ?>
            </div>

            <div class="editor-grid">
                <label class="field">
                    <span>Краткое название <small>необязательно</small></span>
                    <input
                        name="short_name"
                        value="<?= $theme->e((string) ($form['short_name'] ?? '')) ?>"
                        maxlength="255"
                    >
                </label>

                <label class="field">
                    <span>Порядок</span>
                    <input
                        type="number"
                        name="sort_order"
                        value="<?= $theme->e((int) ($form['sort_order'] ?? 0)) ?>"
                        min="0"
                        max="1000000"
                    >
                </label>
            </div>

            <label class="field">
                <span>Описание <small>необязательно</small></span>
                <textarea name="description" rows="8"><?= $theme->e((string) ($form['description'] ?? '')) ?></textarea>
            </label>
        </section>

        <details class="editor-card editor-advanced">
            <summary>Дополнительно</summary>

            <label class="field">
                <span>Юридическое / официальное название</span>
                <input
                    name="legal_name"
                    value="<?= $theme->e((string) ($form['legal_name'] ?? '')) ?>"
                    maxlength="500"
                >
            </label>

            <label class="field">
                <span>Адрес в структуре</span>
                <input
                    name="slug"
                    value="<?= $theme->e((string) ($form['slug'] ?? '')) ?>"
                    maxlength="180"
                    placeholder="создастся автоматически"
                >
                <small>Обычно менять не нужно. Изменение пересчитает пути дочерних элементов.</small>
            </label>
        </details>

        <div class="editor-actions">
            <button class="button button--primary" type="submit">
                <?= $isEdit ? 'Сохранить изменения' : 'Добавить в структуру' ?>
            </button>
        </div>
    </form>
</section>
