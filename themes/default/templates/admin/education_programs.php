<?php

declare(strict_types=1);

$programs = is_array($programs ?? null) ? $programs : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Образование</p>
            <h1>Образовательные программы</h1>
            <p>Программы обучения с уровнем, формой, длительностью, квалификацией и условиями поступления.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новая программа</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_programs_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Название</span><input name="title" maxlength="500" required></label>
                <label><span>Уровень образования</span><input name="education_level" maxlength="100" placeholder="Например: дополнительное образование" required></label>
                <label><span>Форма обучения</span><input name="study_form" maxlength="100" placeholder="Очная, заочная, очно-заочная" required></label>
                <label><span>Длительность, месяцев</span><input type="number" name="duration_months" min="1" max="240"></label>
                <label><span>Квалификация</span><input name="qualification" maxlength="255"></label>
                <label><span>Условия поступления</span><textarea name="admission_note" rows="4" maxlength="4000"></textarea></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное описание</span><textarea name="description" rows="7"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Добавить программу</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Программы</h2>
        <?php if ($programs === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Программ пока нет', 'message' => 'Добавьте первую образовательную программу через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($programs as $program): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($program->title) ?></strong>
                            <span><?= $theme->e($program->status) ?></span>
                            <small><?= $theme->e($program->educationLevel) ?> · <?= $theme->e($program->studyForm) ?></small>
                            <small>владелец: <?= $theme->e($program->ownerOrganizationPublicId) ?></small>
                        </div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_programs_update', ['publicId' => $program->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $program->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="title" maxlength="500" required value="<?= $theme->e($program->title) ?>"></label>
                                <label><span>Уровень образования</span><input name="education_level" maxlength="100" required value="<?= $theme->e($program->educationLevel) ?>"></label>
                                <label><span>Форма обучения</span><input name="study_form" maxlength="100" required value="<?= $theme->e($program->studyForm) ?>"></label>
                                <label><span>Длительность, месяцев</span><input type="number" name="duration_months" min="1" max="240" value="<?= $program->durationMonths === null ? '' : (int) $program->durationMonths ?>"></label>
                                <label><span>Квалификация</span><input name="qualification" maxlength="255" value="<?= $theme->e($program->qualification ?? '') ?>"></label>
                                <label><span>Условия поступления</span><textarea name="admission_note" rows="4" maxlength="4000"><?= $theme->e($program->admissionNote) ?></textarea></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($program->summary) ?></textarea></label>
                                <label><span>Полное описание</span><textarea name="description" rows="7"><?= $theme->e($program->descriptionHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $program->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($program->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_programs_publish', ['publicId' => $program->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_programs_unpublish', ['publicId' => $program->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
