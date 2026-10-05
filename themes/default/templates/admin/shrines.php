<?php

declare(strict_types=1);

$shrines = is_array($shrines ?? null) ? $shrines : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
$types = [
    'relics' => 'Мощи',
    'icon' => 'Икона',
    'place' => 'Святое место',
    'object' => 'Священный предмет',
    'spring' => 'Источник',
    'other' => 'Другое',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div><p class="eyebrow">Церковная жизнь</p><h1>Святыни</h1><p>Карточки святынь с привязкой к церковной организации.</p></div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая святыня</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_shrines_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Тип</span><select name="shrine_type" required><?php foreach ($types as $value => $label): ?><option value="<?= $theme->e($value) ?>"><?= $theme->e($label) ?></option><?php endforeach; ?></select></label>
                <label><span>Название</span><input name="title" maxlength="255" required></label>
                <label><span>Подзаголовок</span><input name="subtitle" maxlength="255"></label>
                <label><span>Местонахождение</span><input name="location_name" maxlength="255"></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное описание</span><textarea name="description" rows="6"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Создать святыню</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Святыни</h2>
        <?php if ($shrines === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Святынь пока нет', 'message' => 'Создайте первую карточку через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($shrines as $shrine): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body"><strong><?= $theme->e($shrine->title) ?></strong><span><?= $theme->e($types[$shrine->shrineType] ?? $shrine->shrineType) ?> · <?= $theme->e($shrine->status) ?></span><small>владелец: <?= $theme->e($shrine->ownerOrganizationPublicId) ?></small></div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_shrines_update', ['publicId' => $shrine->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $shrine->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Тип</span><select name="shrine_type" required><?php foreach ($types as $value => $label): ?><option value="<?= $theme->e($value) ?>" <?= $value === $shrine->shrineType ? 'selected' : '' ?>><?= $theme->e($label) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="title" maxlength="255" required value="<?= $theme->e($shrine->title) ?>"></label>
                                <label><span>Подзаголовок</span><input name="subtitle" maxlength="255" value="<?= $theme->e($shrine->subtitle ?? '') ?>"></label>
                                <label><span>Местонахождение</span><input name="location_name" maxlength="255" value="<?= $theme->e($shrine->locationName ?? '') ?>"></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($shrine->summary) ?></textarea></label>
                                <label><span>Полное описание</span><textarea name="description" rows="6"><?= $theme->e($shrine->descriptionHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $shrine->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($shrine->status === 'draft'): ?><form method="post" action="<?= $theme->e($theme->route('admin_shrines_publish', ['publicId' => $shrine->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form><?php else: ?><form method="post" action="<?= $theme->e($theme->route('admin_shrines_unpublish', ['publicId' => $shrine->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
