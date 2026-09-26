<?php declare(strict_types=1); ?>
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Администрирование</p>
        <h1>Вход в ChurchCMS</h1>
        <p class="auth-card__intro">Используйте учетную запись администратора сайта.</p>

        <?php if (!empty($error)): ?>
            <div class="form-alert" role="alert">
                <?= $theme->e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= $theme->e($theme->route('admin_login_submit')) ?>" class="form-stack">
            <?= $theme->csrfInput() ?>

            <label class="field">
                <span>Имя пользователя</span>
                <input
                    type="text"
                    name="username"
                    value="<?= $theme->e($username ?? '') ?>"
                    autocomplete="username"
                    required
                    maxlength="100"
                    autofocus
                >
            </label>

            <label class="field">
                <span>Пароль</span>
                <input
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </label>

            <button class="button button--primary" type="submit">Войти</button>
        </form>
    </div>
</section>
