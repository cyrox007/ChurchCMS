<?php

declare(strict_types=1);

$ministries = is_array($ministries ?? null) ? $ministries : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div><p class="eyebrow">Структура</p><h1>Служения и отделы</h1><p>Публичные карточки служений с привязкой к церковной организации.</p></div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новое служение</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_ministries_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Название</span><input name="title" maxlength="255" required></label>
                <label><span>Краткое название</span><input name="short_title" maxlength="160"></label>
                <label><span>Руководитель</span><input name="leader_name" maxlength="255"></label>
                <label><span>Email</span><input type="email" name="contact_email" maxlength="255"></label>
                <label><span>Телефон</span><input name="contact_phone" maxlength="64"></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное описание</span><textarea name="description" rows="6"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Создать служение</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Служения</h2>
        <?php if ($ministries === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Служений пока нет', 'message' => 'Создайте первое служение через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($ministries as $ministry): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body"><strong><?= $theme->e($ministry->title) ?></strong><span><?= $theme->e($ministry->status) ?></span><small>владелец: <?= $theme->e($ministry->ownerOrganizationPublicId) ?></small></div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_ministries_update', ['publicId' => $ministry->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $ministry->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="title" maxlength="255" required value="<?= $theme->e($ministry->title) ?>"></label>
                                <label><span>Краткое название</span><input name="short_title" maxlength="160" value="<?= $theme->e($ministry->shortTitle ?? '') ?>"></label>
                                <label><span>Руководитель</span><input name="leader_name" maxlength="255" value="<?= $theme->e($ministry->leaderName ?? '') ?>"></label>
                                <label><span>Email</span><input type="email" name="contact_email" value="<?= $theme->e($ministry->contactEmail ?? '') ?>"></label>
                                <label><span>Телефон</span><input name="contact_phone" value="<?= $theme->e($ministry->contactPhone ?? '') ?>"></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($ministry->summary) ?></textarea></label>
                                <label><span>Полное описание</span><textarea name="description" rows="6"><?= $theme->e($ministry->descriptionHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $ministry->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($ministry->status === 'draft'): ?><form method="post" action="<?= $theme->e($theme->route('admin_ministries_publish', ['publicId' => $ministry->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form><?php else: ?><form method="post" action="<?= $theme->e($theme->route('admin_ministries_unpublish', ['publicId' => $ministry->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
