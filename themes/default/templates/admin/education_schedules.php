<?php

declare(strict_types=1);

$schedules = is_array($schedules ?? null) ? $schedules : [];
$programs = is_array($programs ?? null) ? $programs : [];
$canManage = ($canManage ?? false) === true;
$localInput = static function (string $utcValue, string $timezone): string {
    $date = new DateTimeImmutable($utcValue, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone($timezone))->format('Y-m-d\TH:i');
};
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Образование</p>
            <h1>Расписание обучения</h1>
            <p>Конкретные занятия образовательных программ с корректным часовым поясом.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новое занятие</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_schedules_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Образовательная программа</span><select name="program_public_id" required><option value="">Выберите программу</option><?php foreach ($programs as $program): ?><option value="<?= $theme->e($program->publicId) ?>"><?= $theme->e($program->title) ?></option><?php endforeach; ?></select></label>
                <label><span>Название занятия</span><input name="title" maxlength="500" required></label>
                <label><span>Начало</span><input type="datetime-local" name="starts_at_local" required></label>
                <label><span>Окончание</span><input type="datetime-local" name="ends_at_local" required></label>
                <label><span>Часовой пояс</span><input name="timezone" maxlength="100" value="Europe/Moscow" required></label>
                <label><span>Место</span><input name="location" maxlength="500"></label>
                <label><span>Примечание</span><textarea name="note" rows="4" maxlength="4000"></textarea></label>
                <button class="button button--primary" type="submit">Добавить занятие</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Занятия</h2>
        <?php if ($schedules === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Расписание пока пусто', 'message' => 'Добавьте первое занятие через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($schedules as $schedule): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($schedule->title) ?></strong>
                            <span><?= $theme->e($schedule->status) ?></span>
                            <small><?= $theme->e($schedule->programTitle) ?> · <?= $theme->e($schedule->timezone) ?></small>
                        </div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_education_schedules_update', ['publicId' => $schedule->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Образовательная программа</span><select name="program_public_id" required><?php foreach ($programs as $program): ?><option value="<?= $theme->e($program->publicId) ?>" <?= $program->publicId === $schedule->programPublicId ? 'selected' : '' ?>><?= $theme->e($program->title) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название занятия</span><input name="title" maxlength="500" required value="<?= $theme->e($schedule->title) ?>"></label>
                                <label><span>Начало</span><input type="datetime-local" name="starts_at_local" required value="<?= $theme->e($localInput($schedule->startsAtUtc, $schedule->timezone)) ?>"></label>
                                <label><span>Окончание</span><input type="datetime-local" name="ends_at_local" required value="<?= $theme->e($localInput($schedule->endsAtUtc, $schedule->timezone)) ?>"></label>
                                <label><span>Часовой пояс</span><input name="timezone" maxlength="100" required value="<?= $theme->e($schedule->timezone) ?>"></label>
                                <label><span>Место</span><input name="location" maxlength="500" value="<?= $theme->e($schedule->location) ?>"></label>
                                <label><span>Примечание</span><textarea name="note" rows="4" maxlength="4000"><?= $theme->e($schedule->note) ?></textarea></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($schedule->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_schedules_publish', ['publicId' => $schedule->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_education_schedules_unpublish', ['publicId' => $schedule->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
