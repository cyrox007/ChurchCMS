<?php declare(strict_types=1); ?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">ChurchCMS</p>
            <h1>Панель управления</h1>
            <p>
                <?= $theme->e(is_array($adminUser) ? ($adminUser['display_name'] ?? $adminUser['username'] ?? '') : '') ?>
            </p>
        </div>

        <form method="post" action="<?= $theme->e($theme->route('admin_logout')) ?>">
            <?= $theme->csrfInput() ?>
            <button class="button button--quiet" type="submit">Выйти</button>
        </form>
    </header>

    <div class="admin-grid">
        <article class="admin-tile">
            <p class="card__eyebrow">Публикации</p>
            <h2>Материалы</h2>
            <p>Редактор публикаций будет подключен следующим инкрементом поверх уже работающей Auth/RBAC-защиты.</p>
        </article>

        <article class="admin-tile">
            <p class="card__eyebrow">Роли</p>
            <h2><?= $theme->e(implode(', ', is_array($roles) ? $roles : [])) ?></h2>
            <p>Права будут проверяться сервисом RBAC перед каждым административным действием.</p>
        </article>

        <article class="admin-tile">
            <p class="card__eyebrow">Интеграции</p>
            <h2>API и фиды</h2>
            <p>Партнерский API и синдикация уже изолированы от административной сессии.</p>
        </article>
    </div>
</section>
