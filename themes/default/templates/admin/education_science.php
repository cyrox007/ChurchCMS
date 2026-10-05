<?php

declare(strict_types=1);

$activities = is_array($activities ?? null) ? $activities : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
$types = [
    'research' => 'Исследование',
    'conference' => 'Конференция',
    'publication' => 'Публикация',
    'grant' => 'Грант',
    'laboratory' => 'Лаборатория',
    'other' => 'Другое',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Образование</p>
            <h1>Научная деятельность</h1>
            <p>Исследования, конференции, публикации, гранты и другие научные активности.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая активность</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_science_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Тип</span><select name="activity_type" required><?php foreach ($types as $value => $label): ?><option value="<?= $theme->e($value) ?>"><?= $theme->e($label) ?></option><?php endforeach; ?></select></label>
                <label><span>Название</span><input name="title" maxlength="500" required></label>
                <label><span>Дата начала</span><input type="date" name="starts_on"></label>
                <label><span>Дата окончания</span><input type="date" name="ends_on"></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное описание</span><textarea name="description" rows="7"></textarea></label>
                <label><span>Внешняя HTTPS-ссылка</span><input type="url" name="external_url" maxlength="2000"></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Добавить</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Научные активности</h2>
        <?php if ($activities === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Материалов пока нет', 'message' => 'Добавьте первую научную активность.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($activities as $activity): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($activity->title) ?></strong>
                            <span><?= $theme->e($activity->status) ?></span>
                            <small><?= $theme->e($types[$activity->activityType] ?? $activity->activityType) ?> · владелец: <?= $theme->e($activity->ownerOrganizationPublicId) ?></small>
                        </div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_science_update', ['publicId' => $activity->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $activity->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Тип</span><select name="activity_type" required><?php foreach ($types as $value => $label): ?><option value="<?= $theme->e($value) ?>" <?= $value === $activity->activityType ? 'selected' : '' ?>><?= $theme->e($label) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="title" maxlength="500" required value="<?= $theme->e($activity->title) ?>"></label>
                                <label><span>Дата начала</span><input type="date" name="starts_on" value="<?= $theme->e($activity->startsOn ?? '') ?>"></label>
                                <label><span>Дата окончания</span><input type="date" name="ends_on" value="<?= $theme->e($activity->endsOn ?? '') ?>"></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($activity->summary) ?></textarea></label>
                                <label><span>Полное описание</span><textarea name="description" rows="7"><?= $theme->e($activity->descriptionHtml) ?></textarea></label>
                                <label><span>Внешняя HTTPS-ссылка</span><input type="url" name="external_url" maxlength="2000" value="<?= $theme->e($activity->externalUrl ?? '') ?>"></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $activity->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($activity->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_science_publish', ['publicId' => $activity->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_science_unpublish', ['publicId' => $activity->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
