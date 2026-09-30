<?php declare(strict_types=1); ?>
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Администрирование</p>
        <h1>Новый пароль</h1>

        <?php if (!empty($changed)): ?>
            <div class="form-alert form-alert--success" role="status">
                Пароль изменён. Все ранее открытые административные сессии
                отозваны.
            </div>

            <p>
                <a class="button button--primary" href="<?= $theme->e($theme->route('admin_login')) ?>">
                    Войти
                </a>
            </p>
        <?php elseif (empty($validToken)): ?>
            <div class="form-alert" role="alert">
                Ссылка восстановления недействительна, уже использована или
                истекла.
            </div>

            <p>
                <a href="<?= $theme->e($theme->route('admin_password_forgot')) ?>">
                    Запросить новую ссылку
                </a>
            </p>
        <?php else: ?>
            <p class="auth-card__intro">
                Установите новый пароль длиной не менее 12 символов.
            </p>

            <?php if (!empty($error)): ?>
                <div class="form-alert" role="alert">
                    <?= $theme->e($error) ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= $theme->e($theme->route('admin_password_reset_submit')) ?>"
                class="form-stack"
            >
                <?= $theme->csrfInput() ?>
                <input
                    type="hidden"
                    name="token"
                    value="<?= $theme->e($token ?? '') ?>"
                >

                <label class="field">
                    <span>Новый пароль</span>
                    <input
                        type="password"
                        name="new_password"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="4096"
                        required
                        autofocus
                    >
                </label>

                <label class="field">
                    <span>Повторите новый пароль</span>
                    <input
                        type="password"
                        name="new_password_confirm"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="4096"
                        required
                    >
                </label>

                <button class="button button--primary" type="submit">
                    Изменить пароль
                </button>
            </form>
        <?php endif; ?>
    </div>
</section>
