<?php

declare(strict_types=1);

$health = is_array($health ?? null) ? $health : ['ready' => false, 'checks' => []];
$checks = is_array($health['checks'] ?? null) ? $health['checks'] : [];
$backups = is_array($backups ?? null) ? $backups : [];
$packages = is_array($packages ?? null) ? $packages : [];
$status = is_array($operationStatus ?? null) ? $operationStatus : null;
$warnings = is_array($operationWarnings ?? null) ? $operationWarnings : [];

$checkLabels = [
    'php' => 'Версия PHP',
    'installation' => 'Установка завершена',
    'secret_key' => 'Ключ безопасности',
    'storage' => 'Рабочие каталоги',
    'migrations' => 'Миграции',
    'database' => 'База данных',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Система</p>
            <h1>Резервные копии и обновления</h1>
            <p>Основные операции выполняются здесь без shell-команд и ручного ввода серверных путей.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <?php foreach ($warnings as $warning): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Служебный каталог недоступен',
            'message' => (string) $warning,
        ]) ?>
    <?php endforeach; ?>

    <section class="admin-operations-section" aria-labelledby="system-health-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Состояние</p>
                <h2 id="system-health-title">Готовность системы</h2>
            </div>
            <span class="admin-health-badge <?= !empty($health['ready']) ? 'admin-health-badge--ok' : 'admin-health-badge--error' ?>">
                <?= !empty($health['ready']) ? 'Готово' : 'Нужна проверка' ?>
            </span>
        </div>

        <div class="admin-health-grid">
            <?php foreach ($checkLabels as $key => $label): ?>
                <?php $passed = ($checks[$key] ?? false) === true; ?>
                <div class="admin-health-item">
                    <span aria-hidden="true"><?= $passed ? '✓' : '!' ?></span>
                    <strong><?= $theme->e($label) ?></strong>
                    <small><?= $passed ? 'Проверка пройдена' : 'Требует внимания' ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-operations-section" aria-labelledby="backups-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Безопасность</p>
                <h2 id="backups-title">Резервные копии</h2>
                <p>Копия включает локальную конфигурацию, uploads и данные БД.</p>
            </div>

            <form method="post" action="<?= $theme->e($theme->route('admin_operations_backup_create')) ?>">
                <?= $theme->csrfInput() ?>
                <button class="button button--primary" type="submit">Создать копию</button>
            </form>
        </div>

        <?php if ($backups === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Резервных копий пока нет',
                'message' => 'Создайте первую проверенную копию перед изменениями системы.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($backups as $backup): ?>
                    <?php $backupId = (string) ($backup['id'] ?? ''); ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($backupId) ?></strong>
                            <span>
                                Версия <?= $theme->e((string) ($backup['app_version'] ?? '')) ?>
                                · файлов <?= $theme->e((int) ($backup['files'] ?? 0)) ?>
                                · таблиц <?= $theme->e((int) ($backup['tables'] ?? 0)) ?>
                            </span>
                            <?php if (!empty($backup['created_at'])): ?>
                                <small><?= $theme->e((string) $backup['created_at']) ?></small>
                            <?php endif; ?>
                        </div>

                        <div>
                            <form
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_operations_backup_verify',
                                    ['backupId' => $backupId],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>
                                <button class="button button--quiet" type="submit">Проверить</button>
                            </form>

                            <form
                                class="admin-update-apply"
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_operations_backup_restore',
                                    ['backupId' => $backupId],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="confirm_restore"
                                        value="<?= $theme->e($backupId) ?>"
                                        required
                                    >
                                    <span>
                                        Заменить БД, config и uploads этой копией.
                                        Перед восстановлением будет создан аварийный снимок текущего состояния.
                                    </span>
                                </label>
                                <button class="button button--quiet" type="submit">Восстановить</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-operations-section" aria-labelledby="updates-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Обновления</p>
                <h2 id="updates-title">Подготовленные пакеты</h2>
                <p>Здесь отображаются только пакеты, уже помещённые сервером во внешний staging.</p>
            </div>
        </div>

        <?php if ($packages === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Готовых обновлений нет',
                'message' => 'Когда доверенный пакет попадёт в staging, он появится в этом списке.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($packages as $package): ?>
                    <?php
                    $stageId = (string) ($package['id'] ?? '');
                    $ready = !empty($package['ready']);
                    $codeOnly = !empty($package['code_only']);
                    $trusted = !empty($package['trusted']);
                    $trustedKeyId = (string) ($package['trusted_key_id'] ?? '');
                    ?>
                    <article class="admin-operations-row admin-operations-row--update" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong>
                                <?= $theme->e(
                                    $ready && !empty($package['version'])
                                        ? 'Версия ' . $package['version']
                                        : 'Пакет ' . $stageId
                                ) ?>
                            </strong>
                            <?php if ($ready): ?>
                                <span>
                                    файлов <?= $theme->e((int) ($package['files'] ?? 0)) ?>
                                    · удалений <?= $theme->e((int) ($package['deleted_files'] ?? 0)) ?>
                                </span>
                            <?php else: ?>
                                <span>Пакет повреждён, устарел или не предназначен для текущей версии.</span>
                            <?php endif; ?>

                            <?php if ($ready): ?>
                                <small>
                                    <?= $trusted
                                        ? 'Подпись проверена'
                                            . ($trustedKeyId !== ''
                                                ? ' · ключ ' . $theme->e($trustedKeyId)
                                                : '')
                                        : 'Подпись не проверена' ?>
                                </small>
                            <?php endif; ?>

                            <?php if ($ready && !$codeOnly): ?>
                                <small class="admin-operations-warning">
                                    Пакет содержит миграции. Автоматическое применение отключено до появления безопасного rollback схемы БД.
                                </small>
                            <?php endif; ?>
                        </div>

                        <?php if ($ready && $codeOnly): ?>
                            <form
                                class="admin-update-apply"
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_operations_update_apply',
                                    ['stageId' => $stageId],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="confirm_stage"
                                        value="<?= $theme->e($stageId) ?>"
                                        required
                                    >
                                    <span>Создать резервную копию и применить это обновление</span>
                                </label>
                                <button class="button button--primary" type="submit">Применить</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="admin-operations-note">
        <strong>Восстановление выполняется с аварийной копией.</strong>
        <p>
            Перед заменой БД, локальной конфигурации и uploads ChurchCMS создаёт проверенный снимок текущего состояния.
            При ошибке система пытается автоматически вернуть этот снимок. Из браузера разрешено восстанавливать только копию
            с тем же подключением к БД и тем же каталогом резервных копий; перенос между окружениями остаётся операторской процедурой.
        </p>
    </aside>
</section>
