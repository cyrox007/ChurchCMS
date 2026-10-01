<?php

declare(strict_types=1);

$services = is_array($services ?? null) ? $services : [];
$createOrganizationUnits = is_array($createOrganizationUnits ?? null)
    ? $createOrganizationUnits
    : [];
$editOrganizationUnits = is_array($editOrganizationUnits ?? null)
    ? $editOrganizationUnits
    : [];
$canCreate = ($canCreate ?? false) === true;
$canEdit = ($canEdit ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Расписание богослужений</h1>
            <p>Управление богослужениями с привязкой к организационной структуре.</p>
        </div>
    </header>

    <?php if ($canCreate): ?>
        <section class="admin-operations-section">
            <h2>Добавить богослужение</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_worship_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required>
                    <option value="">Выберите организацию</option>
                    <?php foreach ($createOrganizationUnits as $unit): ?>
                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Название</span><input name="title" maxlength="255" required></label>
                <label><span>Тип</span><input name="service_type" value="service" maxlength="64" required></label>
                <label><span>Начало</span><input type="datetime-local" name="starts_at" required></label>
                <label><span>Окончание</span><input type="datetime-local" name="ends_at"></label>
                <label><span>Место</span><input name="location_name" maxlength="255"></label>
                <label><span>Описание</span><textarea name="description" rows="4"></textarea></label>
                <button class="button button--primary" type="submit">Добавить</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Записи расписания</h2>
        <?php if ($services === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Расписание пусто',
                'message' => 'Добавьте первую запись богослужения.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($services as $service): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($service->title) ?></strong>
                            <span>
                                <?= $theme->e($service->startsAt) ?> UTC
                                · <?= $theme->e($service->status) ?>
                                <?php if ($service->locationName !== null): ?>
                                    · <?= $theme->e($service->locationName) ?>
                                <?php endif; ?>
                            </span>
                            <small>владелец: <?= $theme->e($service->ownerOrganizationPublicId) ?></small>
                        </div>

                        <?php if ($canEdit && $service->status !== 'withdrawn'): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_worship_update', ['publicId' => $service->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required>
                                    <?php foreach ($editOrganizationUnits as $unit): ?>
                                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $service->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Название</span><input name="title" maxlength="255" required value="<?= $theme->e($service->title) ?>"></label>
                                <label><span>Тип</span><input name="service_type" maxlength="64" required value="<?= $theme->e($service->serviceType) ?>"></label>
                                <label><span>Начало UTC</span><input name="starts_at" required value="<?= $theme->e($service->startsAt) ?>"></label>
                                <label><span>Окончание UTC</span><input name="ends_at" value="<?= $theme->e($service->endsAt ?? '') ?>"></label>
                                <label><span>Место</span><input name="location_name" maxlength="255" value="<?= $theme->e($service->locationName ?? '') ?>"></label>
                                <label><span>Описание</span><textarea name="description" rows="4"><?= $theme->e($service->descriptionHtml) ?></textarea></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>

                            <div class="admin-actions">
                                <?php if ($service->status === 'cancelled'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_worship_schedule', ['publicId' => $service->publicId])) ?>">
                                        <?= $theme->csrfInput() ?>
                                        <button class="button button--quiet" type="submit">Вернуть в расписание</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_worship_cancel', ['publicId' => $service->publicId])) ?>">
                                        <?= $theme->csrfInput() ?>
                                        <button class="button button--quiet" type="submit">Отменить</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
