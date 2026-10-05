<?php

declare(strict_types=1);

$admissions = is_array($admissions ?? null) ? $admissions : [];
$programs = is_array($programs ?? null) ? $programs : [];
$canManage = ($canManage ?? false) === true;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Образование</p>
            <h1>Приёмная кампания</h1>
            <p>Сроки приёма, места, требования, вступительные испытания и контактная информация.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая кампания</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_admissions_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Образовательная программа</span><select name="program_public_id" required><option value="">Выберите программу</option><?php foreach ($programs as $program): ?><option value="<?= $theme->e($program->publicId) ?>"><?= $theme->e($program->title) ?></option><?php endforeach; ?></select></label>
                <label><span>Название кампании</span><input name="title" maxlength="500" required></label>
                <label><span>Учебный год</span><input name="academic_year" maxlength="32" placeholder="2026/2027" required></label>
                <label><span>Начало приёма</span><input type="date" name="starts_on"></label>
                <label><span>Окончание приёма</span><input type="date" name="ends_on"></label>
                <label><span>Бюджетных мест</span><input type="number" name="budget_seats" min="0" max="100000" value="0"></label>
                <label><span>Платных мест</span><input type="number" name="paid_seats" min="0" max="100000" value="0"></label>
                <label><span>Стоимость обучения</span><textarea name="tuition_note" rows="3" maxlength="4000"></textarea></label>
                <label><span>Требования</span><textarea name="requirements" rows="5" maxlength="8000"></textarea></label>
                <label><span>Вступительные испытания</span><textarea name="entrance_tests" rows="5" maxlength="8000"></textarea></label>
                <label><span>Контакты</span><textarea name="contact_note" rows="3" maxlength="4000"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Добавить кампанию</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Кампании</h2>
        <?php if ($admissions === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Кампаний пока нет', 'message' => 'Добавьте первую запись приёмной кампании.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($admissions as $admission): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($admission->title) ?></strong>
                            <span><?= $theme->e($admission->status) ?> · <?= $theme->e($admission->academicYear) ?></span>
                            <small><?= $theme->e($admission->programTitle) ?></small>
                        </div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_admissions_update', ['publicId' => $admission->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Образовательная программа</span><select name="program_public_id" required><?php foreach ($programs as $program): ?><option value="<?= $theme->e($program->publicId) ?>" <?= $program->publicId === $admission->programPublicId ? 'selected' : '' ?>><?= $theme->e($program->title) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название кампании</span><input name="title" maxlength="500" required value="<?= $theme->e($admission->title) ?>"></label>
                                <label><span>Учебный год</span><input name="academic_year" maxlength="32" required value="<?= $theme->e($admission->academicYear) ?>"></label>
                                <label><span>Начало приёма</span><input type="date" name="starts_on" value="<?= $theme->e($admission->startsOn ?? '') ?>"></label>
                                <label><span>Окончание приёма</span><input type="date" name="ends_on" value="<?= $theme->e($admission->endsOn ?? '') ?>"></label>
                                <label><span>Бюджетных мест</span><input type="number" name="budget_seats" min="0" max="100000" value="<?= (int) $admission->budgetSeats ?>"></label>
                                <label><span>Платных мест</span><input type="number" name="paid_seats" min="0" max="100000" value="<?= (int) $admission->paidSeats ?>"></label>
                                <label><span>Стоимость обучения</span><textarea name="tuition_note" rows="3" maxlength="4000"><?= $theme->e($admission->tuitionNote) ?></textarea></label>
                                <label><span>Требования</span><textarea name="requirements" rows="5" maxlength="8000"><?= $theme->e($admission->requirements) ?></textarea></label>
                                <label><span>Вступительные испытания</span><textarea name="entrance_tests" rows="5" maxlength="8000"><?= $theme->e($admission->entranceTests) ?></textarea></label>
                                <label><span>Контакты</span><textarea name="contact_note" rows="3" maxlength="4000"><?= $theme->e($admission->contactNote) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $admission->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($admission->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_admissions_publish', ['publicId' => $admission->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_admissions_unpublish', ['publicId' => $admission->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
