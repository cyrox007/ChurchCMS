<?php

declare(strict_types=1);

$units = is_array($units ?? null) ? $units : [];
$typeLabels = is_array($typeLabels ?? null) ? $typeLabels : [];
$status = is_array($organizationStatus ?? null)
    ? $organizationStatus
    : null;
$rootId = isset($root) && $root instanceof \ChurchCMS\Modules\Organizations\OrganizationUnit
    ? $root->id
    : 0;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Церковная структура</p>
            <h1>Организации и подразделения</h1>
            <p>Благочиния, приходы, монастыри, отделы и комиссии в структуре этого сайта.</p>
        </div>

        <div class="admin-heading__actions">
            <a class="button button--primary" href="<?= $theme->e($theme->route('admin_organization_new')) ?>">
                + Добавить
            </a>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <?php if ($units === []): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'empty',
            'title' => 'Структура пока не создана',
            'message' => 'Корневая организация обычно создаётся установщиком. Добавьте её после проверки конфигурации.',
        ]) ?>
    <?php else: ?>
        <div class="organization-tree" role="list">
            <?php foreach ($units as $unit): ?>
                <?php
                $depth = max(
                    0,
                    substr_count(trim($unit->path, '/'), '/'),
                );
                $isRoot = $unit->id === $rootId;
                $isArchived = $unit->status === 'archived';
                ?>
                <article
                    class="organization-tree__item<?= $isArchived ? ' organization-tree__item--archived' : '' ?>"
                    role="listitem"
                    style="--organization-depth: <?= $theme->e($depth) ?>"
                >
                    <div class="organization-tree__body">
                        <div class="organization-tree__meta">
                            <span><?= $theme->e($typeLabels[$unit->type] ?? $unit->type) ?></span>
                            <?php if ($isRoot): ?>
                                <span class="status-pill">Корень сайта</span>
                            <?php endif; ?>
                            <?php if ($isArchived): ?>
                                <span class="status-pill status-pill--withdrawn">Архив</span>
                            <?php endif; ?>
                        </div>

                        <h2><?= $theme->e($unit->name) ?></h2>

                        <?php if ($unit->shortName !== null): ?>
                            <p><?= $theme->e($unit->shortName) ?></p>
                        <?php endif; ?>

                        <small><?= $theme->e($unit->path) ?></small>
                    </div>

                    <div class="organization-tree__actions">
                        <?php if (!$isArchived): ?>
                            <a
                                class="button button--quiet"
                                href="<?= $theme->e(
                                    $theme->route('admin_organization_new')
                                    . '?parent='
                                    . rawurlencode($unit->publicId)
                                ) ?>"
                            >
                                + Дочерний
                            </a>
                        <?php endif; ?>

                        <a
                            class="button button--quiet"
                            href="<?= $theme->e($theme->route(
                                'admin_organization_edit',
                                ['publicId' => $unit->publicId],
                            )) ?>"
                        >
                            Редактировать
                        </a>

                        <?php if (!$isRoot): ?>
                            <?php if ($isArchived): ?>
                                <form
                                    method="post"
                                    action="<?= $theme->e($theme->route(
                                        'admin_organization_restore',
                                        ['publicId' => $unit->publicId],
                                    )) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <button class="button button--quiet" type="submit">Восстановить</button>
                                </form>
                            <?php else: ?>
                                <form
                                    method="post"
                                    action="<?= $theme->e($theme->route(
                                        'admin_organization_archive',
                                        ['publicId' => $unit->publicId],
                                    )) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <button class="button button--quiet" type="submit">В архив</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <aside class="admin-operations-note">
        <strong>Удаление заменено архивированием.</strong>
        <p>
            Это сохраняет стабильные идентификаторы и не ломает будущие связи с духовенством, документами, публикациями и подключёнными ChurchCMS.
            Архивирование родителя архивирует всё его поддерево.
        </p>
    </aside>
</section>
