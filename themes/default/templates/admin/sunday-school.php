<?php

declare(strict_types=1);

$schools = is_array($schools ?? null) ? $schools : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div><p class="eyebrow">Приходская жизнь</p><h1>Воскресная школа</h1><p>Публичные карточки школ с руководителем, контактами и возрастной информацией.</p></div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая школа</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_sunday_school_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Название</span><input name="title" maxlength="255" required></label>
                <label><span>Руководитель</span><input name="leader_name" maxlength="255"></label>
                <label><span>Место занятий</span><input name="location_name" maxlength="255"></label>
                <label><span>Возрастные группы</span><input name="age_info" maxlength="255" placeholder="Например: 7–12 лет"></label>
                <label><span>Email</span><input type="email" name="contact_email" maxlength="255"></label>
                <label><span>Телефон</span><input name="contact_phone" maxlength="64"></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное описание</span><textarea name="description" rows="6"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Создать школу</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Школы</h2>
        <?php if ($schools === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Воскресных школ пока нет', 'message' => 'Создайте первую карточку через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($schools as $school): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body"><strong><?= $theme->e($school->title) ?></strong><span><?= $theme->e($school->status) ?></span><small>владелец: <?= $theme->e($school->ownerOrganizationPublicId) ?></small></div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_sunday_school_update', ['publicId' => $school->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $school->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="title" maxlength="255" required value="<?= $theme->e($school->title) ?>"></label>
                                <label><span>Руководитель</span><input name="leader_name" maxlength="255" value="<?= $theme->e($school->leaderName ?? '') ?>"></label>
                                <label><span>Место занятий</span><input name="location_name" maxlength="255" value="<?= $theme->e($school->locationName ?? '') ?>"></label>
                                <label><span>Возрастные группы</span><input name="age_info" maxlength="255" value="<?= $theme->e($school->ageInfo ?? '') ?>"></label>
                                <label><span>Email</span><input type="email" name="contact_email" maxlength="255" value="<?= $theme->e($school->contactEmail ?? '') ?>"></label>
                                <label><span>Телефон</span><input name="contact_phone" maxlength="64" value="<?= $theme->e($school->contactPhone ?? '') ?>"></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($school->summary) ?></textarea></label>
                                <label><span>Полное описание</span><textarea name="description" rows="6"><?= $theme->e($school->descriptionHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $school->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($school->status === 'draft'): ?><form method="post" action="<?= $theme->e($theme->route('admin_sunday_school_publish', ['publicId' => $school->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form><?php else: ?><form method="post" action="<?= $theme->e($theme->route('admin_sunday_school_unpublish', ['publicId' => $school->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
