<?php

declare(strict_types=1);

$peopleRows = is_array($peopleRows ?? null) ? $peopleRows : [];
$createOrganizationUnits = is_array($createOrganizationUnits ?? null)
    ? $createOrganizationUnits
    : [];
$editOrganizationUnits = is_array($editOrganizationUnits ?? null)
    ? $editOrganizationUnits
    : [];
$createPortraits = is_array($createPortraits ?? null)
    ? $createPortraits
    : [];
$editPortraits = is_array($editPortraits ?? null)
    ? $editPortraits
    : [];
$canCreate = ($canCreate ?? false) === true;
$canEdit = ($canEdit ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Контент</p>
            <h1>Люди и духовенство</h1>
            <p>Карточки людей и их назначения в организационной структуре.</p>
        </div>
    </header>

    <?php if ($canCreate): ?>
        <section class="admin-operations-section">
            <h2>Новая карточка</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_people_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required>
                    <option value="">Выберите организацию</option>
                    <?php foreach ($createOrganizationUnits as $unit): ?>
                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option>
                    <?php endforeach; ?>
                </select></label>
                <label><span>Отображаемое имя</span><input name="display_name" maxlength="255" required></label>
                <label><span>Имя</span><input name="first_name" maxlength="120"></label>
                <label><span>Отчество</span><input name="middle_name" maxlength="120"></label>
                <label><span>Фамилия</span><input name="last_name" maxlength="120"></label>
                <label><span>Краткая биография</span><textarea name="biography" rows="4"></textarea></label>
                <label>
                    <span>Фотография</span>
                    <select name="portrait_media_public_id">
                        <option value="">Без фотографии</option>
                        <?php foreach ($createPortraits as $portrait): ?>
                            <option value="<?= $theme->e((string) ($portrait['public_id'] ?? '')) ?>">
                                <?= $theme->e((string) ($portrait['title'] ?? 'Изображение')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button--primary" type="submit">Создать карточку</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Карточки</h2>
        <?php if ($peopleRows === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Карточек пока нет',
                'message' => 'Создайте первую карточку человека.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($peopleRows as $row): ?>
                    <?php
                    $person = $row['person'];
                    $appointments = $row['appointments'] ?? [];
                    $portraitMediaPublicId = (string) (
                        $row['portrait_media_public_id'] ?? ''
                    );
                    ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($person->displayName) ?></strong>
                            <small>владелец: <?= $theme->e($person->ownerOrganizationPublicId) ?> · назначений: <?= $theme->e(count($appointments)) ?></small>
                        </div>
                        <?php if ($canEdit): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_people_update', ['publicId' => $person->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required>
                                    <?php foreach ($editOrganizationUnits as $unit): ?>
                                        <option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $person->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Отображаемое имя</span><input name="display_name" maxlength="255" required value="<?= $theme->e($person->displayName) ?>"></label>
                                <label><span>Имя</span><input name="first_name" maxlength="120" value="<?= $theme->e($person->firstName ?? '') ?>"></label>
                                <label><span>Отчество</span><input name="middle_name" maxlength="120" value="<?= $theme->e($person->middleName ?? '') ?>"></label>
                                <label><span>Фамилия</span><input name="last_name" maxlength="120" value="<?= $theme->e($person->lastName ?? '') ?>"></label>
                                <label><span>Краткая биография</span><textarea name="biography" rows="4"><?= $theme->e($person->biographyHtml) ?></textarea></label>
                                <label>
                                    <span>Фотография</span>
                                    <select name="portrait_media_public_id">
                                        <option value="">Без фотографии</option>
                                        <?php foreach ($editPortraits as $portrait): ?>
                                            <?php $portraitId = (string) ($portrait['public_id'] ?? ''); ?>
                                            <option
                                                value="<?= $theme->e($portraitId) ?>"
                                                <?= $portraitId === $portraitMediaPublicId ? 'selected' : '' ?>
                                            >
                                                <?= $theme->e((string) ($portrait['title'] ?? 'Изображение')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>

                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_people_appointment_create', ['publicId' => $person->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <h3>Добавить назначение</h3>
                                <label><span>Организация</span><select name="organization_public_id" required>
                                    <?php foreach ($editOrganizationUnits as $unit): ?>
                                        <option value="<?= $theme->e($unit->publicId) ?>"><?= $theme->e($unit->name) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label><span>Должность или служение</span><input name="title" maxlength="255" required></label>
                                <label><span>Тип</span><input name="appointment_type" value="position" maxlength="64" required></label>
                                <label><span>Начало</span><input type="date" name="started_on"></label>
                                <label><span>Окончание</span><input type="date" name="ended_on"></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                                <button class="button button--quiet" type="submit">Добавить назначение</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($appointments !== []): ?>
                            <div>
                                <?php foreach ($appointments as $appointment): ?>
                                    <p><strong><?= $theme->e($appointment->title) ?></strong> · <?= $theme->e($appointment->organizationPublicId) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
