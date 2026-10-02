<?php

declare(strict_types=1);

$events = is_array($events ?? null) ? $events : [];
$createOrganizationUnits = is_array($createOrganizationUnits ?? null)
    ? $createOrganizationUnits
    : [];
$editOrganizationUnits = is_array($editOrganizationUnits ?? null)
    ? $editOrganizationUnits
    : [];
$canCreate = ($canCreate ?? false) === true;
$canEdit = ($canEdit ?? false) === true;
$canPublish = ($canPublish ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>События</h1>
            <p>Календарные события с привязкой к организационной структуре.</p>
        </div>
    </header>

    <?php if ($canCreate): ?>
        <section class="admin-operations-section">
            <h2>Новое событие</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_events_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required>
                    <option value="">Выберите организацию</option>
                    <?php foreach ($createOrganizationUnits as $unit): ?>
                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Название</span><input name="title" maxlength="255" required></label>
                <label><span>Начало</span><input type="datetime-local" name="starts_at" required></label>
                <label><span>Окончание</span><input type="datetime-local" name="ends_at"></label>
                <label><input type="checkbox" name="all_day" value="1"> <span>Весь день</span></label>
                <label><span>Место</span><input name="location_name" maxlength="255"></label>
                <label><span>Краткое описание</span><textarea name="excerpt" rows="3" maxlength="2000"></textarea></label>
                <label><span>Описание</span><textarea name="description" rows="5"></textarea></label>
                <button class="button button--primary" type="submit">Создать событие</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>События</h2>
        <?php if ($events === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Событий пока нет',
                'message' => 'Создайте первое событие через форму выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($events as $event): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($event->title) ?></strong>
                            <span><?= $theme->e($event->startsAt) ?> UTC · <?= $theme->e($event->status) ?></span>
                            <small>владелец: <?= $theme->e($event->ownerOrganizationPublicId) ?></small>
                        </div>

                        <?php if ($canEdit && !in_array($event->status, ['withdrawn', 'cancelled'], true)): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_events_update', ['publicId' => $event->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required>
                                    <?php foreach ($editOrganizationUnits as $unit): ?>
                                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $event->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Название</span><input name="title" maxlength="255" required value="<?= $theme->e($event->title) ?>"></label>
                                <label><span>Начало UTC</span><input name="starts_at" required value="<?= $theme->e($event->startsAt) ?>"></label>
                                <label><span>Окончание UTC</span><input name="ends_at" value="<?= $theme->e($event->endsAt ?? '') ?>"></label>
                                <label><input type="checkbox" name="all_day" value="1" <?= $event->allDay ? 'checked' : '' ?>> <span>Весь день</span></label>
                                <label><span>Место</span><input name="location_name" maxlength="255" value="<?= $theme->e($event->locationName ?? '') ?>"></label>
                                <label><span>Краткое описание</span><textarea name="excerpt" rows="3" maxlength="2000"><?= $theme->e($event->excerpt) ?></textarea></label>
                                <label><span>Описание</span><textarea name="description" rows="5"><?= $theme->e($event->descriptionHtml) ?></textarea></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                        <?php endif; ?>

                        <div class="admin-actions">
                            <?php if ($canPublish && $event->status === 'draft'): ?>
                                <form method="post" action="<?= $theme->e($theme->route('admin_events_publish', ['publicId' => $event->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                            <?php elseif ($canPublish && $event->status === 'published'): ?>
                                <form method="post" action="<?= $theme->e($theme->route('admin_events_withdraw', ['publicId' => $event->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять</button></form>
                            <?php endif; ?>
                            <?php if ($canEdit && $event->status === 'published'): ?>
                                <form method="post" action="<?= $theme->e($theme->route('admin_events_cancel', ['publicId' => $event->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Отменить</button></form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
