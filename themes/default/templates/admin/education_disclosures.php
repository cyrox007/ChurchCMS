<?php

declare(strict_types=1);

$disclosures = is_array($disclosures ?? null) ? $disclosures : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Образование</p>
            <h1>Обязательные сведения</h1>
            <p>Публичные разделы сведений образовательной организации.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новый раздел</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_disclosures_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Ключ раздела</span><input name="section_key" maxlength="100" placeholder="basic_information" required></label>
                <label><span>Название</span><input name="title" maxlength="500" required></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Содержимое</span><textarea name="body" rows="8"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Добавить раздел</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Разделы</h2>
        <?php if ($disclosures === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Разделов пока нет', 'message' => 'Добавьте первый раздел обязательных сведений.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($disclosures as $disclosure): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($disclosure->title) ?></strong>
                            <span><?= $theme->e($disclosure->status) ?></span>
                            <small><?= $theme->e($disclosure->sectionKey) ?> · владелец: <?= $theme->e($disclosure->ownerOrganizationPublicId) ?></small>
                        </div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_disclosures_update', ['publicId' => $disclosure->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $disclosure->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Ключ раздела</span><input name="section_key" maxlength="100" required value="<?= $theme->e($disclosure->sectionKey) ?>"></label>
                                <label><span>Название</span><input name="title" maxlength="500" required value="<?= $theme->e($disclosure->title) ?>"></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($disclosure->summary) ?></textarea></label>
                                <label><span>Содержимое</span><textarea name="body" rows="8"><?= $theme->e($disclosure->bodyHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $disclosure->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($disclosure->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_disclosures_publish', ['publicId' => $disclosure->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_disclosures_unpublish', ['publicId' => $disclosure->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
