<?php

declare(strict_types=1);

$saints = is_array($saints ?? null) ? $saints : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div><p class="eyebrow">Церковная жизнь</p><h1>Святые</h1><p>Карточки святых, связанных с приходом, монастырём или епархией.</p></div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая карточка</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_saints_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Имя для публикации</span><input name="display_name" maxlength="255" required></label>
                <label><span>Чин / тип почитания</span><input name="saint_rank" maxlength="64" placeholder="Например: святитель, преподобный"></label>
                <label><span>Мирское имя</span><input name="secular_name" maxlength="255"></label>
                <label><span>Дни памяти</span><input name="commemoration_text" maxlength="500" placeholder="Текстом, с учётом принятого календаря"></label>
                <label><span>Краткое житие</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное житие</span><textarea name="biography" rows="7"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Создать карточку</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Карточки святых</h2>
        <?php if ($saints === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Карточек пока нет', 'message' => 'Создайте первую карточку через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($saints as $saint): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body"><strong><?= $theme->e($saint->displayName) ?></strong><span><?= $theme->e($saint->saintRank ?? 'без указания чина') ?> · <?= $theme->e($saint->status) ?></span><small>владелец: <?= $theme->e($saint->ownerOrganizationPublicId) ?></small></div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_saints_update', ['publicId' => $saint->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $saint->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Имя для публикации</span><input name="display_name" maxlength="255" required value="<?= $theme->e($saint->displayName) ?>"></label>
                                <label><span>Чин / тип почитания</span><input name="saint_rank" maxlength="64" value="<?= $theme->e($saint->saintRank ?? '') ?>"></label>
                                <label><span>Мирское имя</span><input name="secular_name" maxlength="255" value="<?= $theme->e($saint->secularName ?? '') ?>"></label>
                                <label><span>Дни памяти</span><input name="commemoration_text" maxlength="500" value="<?= $theme->e($saint->commemorationText ?? '') ?>"></label>
                                <label><span>Краткое житие</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($saint->summary) ?></textarea></label>
                                <label><span>Полное житие</span><textarea name="biography" rows="7"><?= $theme->e($saint->biographyHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $saint->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($saint->status === 'draft'): ?><form method="post" action="<?= $theme->e($theme->route('admin_saints_publish', ['publicId' => $saint->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form><?php else: ?><form method="post" action="<?= $theme->e($theme->route('admin_saints_unpublish', ['publicId' => $saint->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
