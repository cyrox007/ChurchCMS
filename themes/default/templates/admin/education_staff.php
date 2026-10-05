<?php

declare(strict_types=1);

$chairs = is_array($chairs ?? null) ? $chairs : [];
$teachersByChair = is_array($teachersByChair ?? null) ? $teachersByChair : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$people = is_array($people ?? null) ? $people : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
$status = is_array($educationStaffStatus ?? null) ? $educationStaffStatus : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Образование</p>
            <h1>Кафедры и преподаватели</h1>
            <p>Учебные подразделения и образовательные назначения существующих карточек людей.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', $status) ?>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая кафедра</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_chair_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Название кафедры</span><input name="name" maxlength="500" required></label>
                <label><span>Краткое название</span><input name="short_name" maxlength="120"></label>
                <label><span>Описание</span><textarea name="description" rows="6"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Добавить кафедру</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Кафедры</h2>
        <?php if ($chairs === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Кафедр пока нет', 'message' => 'Добавьте первую кафедру через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($chairs as $chair): ?>
                    <?php $teachers = is_array($teachersByChair[$chair->publicId] ?? null) ? $teachersByChair[$chair->publicId] : []; ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($chair->name) ?></strong>
                            <span><?= $theme->e($chair->status) ?></span>
                            <?php if ($chair->shortName !== null): ?><small><?= $theme->e($chair->shortName) ?></small><?php endif; ?>
                        </div>

                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_chair_update', ['publicId' => $chair->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $chair->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="name" maxlength="500" required value="<?= $theme->e($chair->name) ?>"></label>
                                <label><span>Краткое название</span><input name="short_name" maxlength="120" value="<?= $theme->e($chair->shortName ?? '') ?>"></label>
                                <label><span>Описание</span><textarea name="description" rows="5"><?= $theme->e($chair->descriptionHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $chair->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить кафедру</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($chair->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_chair_publish', ['publicId' => $chair->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_chair_unpublish', ['publicId' => $chair->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>

                            <section class="admin-operations-section">
                                <h3>Добавить преподавателя</h3>
                                <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_teacher_assign', ['chairPublicId' => $chair->publicId])) ?>">
                                    <?= $theme->csrfInput() ?>
                                    <label><span>Человек</span><select name="person_public_id" required><option value="">Выберите карточку</option><?php foreach ($people as $person): ?><option value="<?= $theme->e($person['public_id']) ?>"><?= $theme->e($person['display_name']) ?></option><?php endforeach; ?></select></label>
                                    <label><span>Должность</span><input name="position_title" maxlength="255" required placeholder="Доцент, преподаватель, заведующий кафедрой"></label>
                                    <label><span>Учёная степень</span><input name="academic_degree" maxlength="255"></label>
                                    <label><span>Учёное звание</span><input name="academic_title" maxlength="255"></label>
                                    <label><span>Дисциплины</span><textarea name="disciplines" rows="3" maxlength="4000"></textarea></label>
                                    <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                                    <button class="button button--quiet" type="submit">Назначить</button>
                                </form>
                            </section>
                        <?php endif; ?>

                        <section class="admin-operations-section">
                            <h3>Состав кафедры</h3>
                            <?php if ($teachers === []): ?>
                                <p>Назначений пока нет.</p>
                            <?php else: ?>
                                <?php foreach ($teachers as $teacher): ?>
                                    <article class="admin-operations-row">
                                        <div class="admin-operations-row__body">
                                            <strong><?= $theme->e($teacher->personDisplayName) ?></strong>
                                            <span><?= $theme->e($teacher->positionTitle) ?> · <?= $theme->e($teacher->status) ?></span>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_teacher_update', ['assignmentPublicId' => $teacher->publicId])) ?>">
                                                <?= $theme->csrfInput() ?>
                                                <label><span>Человек</span><select name="person_public_id" required><?php foreach ($people as $person): ?><option value="<?= $theme->e($person['public_id']) ?>" <?= $person['public_id'] === $teacher->personPublicId ? 'selected' : '' ?>><?= $theme->e($person['display_name']) ?></option><?php endforeach; ?></select></label>
                                                <label><span>Должность</span><input name="position_title" maxlength="255" required value="<?= $theme->e($teacher->positionTitle) ?>"></label>
                                                <label><span>Учёная степень</span><input name="academic_degree" maxlength="255" value="<?= $theme->e($teacher->academicDegree ?? '') ?>"></label>
                                                <label><span>Учёное звание</span><input name="academic_title" maxlength="255" value="<?= $theme->e($teacher->academicTitle ?? '') ?>"></label>
                                                <label><span>Дисциплины</span><textarea name="disciplines" rows="3" maxlength="4000"><?= $theme->e($teacher->disciplines) ?></textarea></label>
                                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $teacher->sortOrder ?>"></label>
                                                <button class="button button--quiet" type="submit">Сохранить назначение</button>
                                            </form>
                                            <?php if ($teacher->status === 'active'): ?>
                                                <form method="post" action="<?= $theme->e($theme->route('admin_education_teacher_deactivate', ['assignmentPublicId' => $teacher->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Завершить назначение</button></form>
                                            <?php else: ?>
                                                <form method="post" action="<?= $theme->e($theme->route('admin_education_teacher_reactivate', ['assignmentPublicId' => $teacher->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Восстановить назначение</button></form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </section>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
