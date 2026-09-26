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
        <?php if (!empty($canManagePublications)): ?>
            <a class="admin-tile admin-tile--link" href="<?= $theme->e($theme->route('admin_publications')) ?>">
                <p class="card__eyebrow">Публикации</p>
                <h2>Материалы</h2>
                <p>Создать новость, сохранить черновик, разрешить комментарии и опубликовать.</p>
            </a>
        <?php endif; ?>

        <?php if (!empty($canModerateComments)): ?>
            <a class="admin-tile admin-tile--link" href="<?= $theme->e($theme->route('admin_comments')) ?>">
                <p class="card__eyebrow">Комментарии</p>
                <h2><?= $theme->e((int) ($pendingComments ?? 0)) ?> ожидают проверки</h2>
                <p>Открыть очередь и быстро одобрить, отклонить или пометить как спам.</p>
            </a>
        <?php endif; ?>

        <article class="admin-tile">
            <p class="card__eyebrow">Роли</p>
            <h2><?= $theme->e(implode(', ', is_array($roles) ? $roles : [])) ?></h2>
            <p>Доступ к разделам определяется правами роли, без необходимости разбираться во внутреннем устройстве CMS.</p>
        </article>

        <article class="admin-tile">
            <p class="card__eyebrow">Интеграции</p>
            <h2>API и фиды</h2>
            <p>Партнерский API и синдикация работают отдельно от административной сессии.</p>
        </article>
    </div>
</section>
