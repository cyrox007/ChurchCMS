<?php declare(strict_types=1); ?>
<section class="auth-shell">
    <div class="auth-card">
        <p class="eyebrow">Администрирование</p>
        <h1>Восстановление доступа</h1>

        <?php if (!empty($submitted)): ?>
            <div class="form-alert form-alert--success" role="status">
                Если активная учётная запись с таким email существует и канал
                доставки настроен, ссылка для восстановления отправлена.
            </div>
        <?php else: ?>
            <p class="auth-card__intro">
                Укажите email администратора. Ответ не сообщает, существует ли
                такая учётная запись.
            </p>

            <?php if (!empty($error)): ?>
                <div class="form-alert" role="alert">
                    <?= $theme->e($error) ?>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= $theme->e($theme->route('admin_password_forgot_submit')) ?>"
                class="form-stack"
            >
                <?= $theme->csrfInput() ?>

                <label class="field">
                    <span>Email</span>
                    <input
                        type="email"
                        name="email"
                        autocomplete="email"
                        maxlength="255"
                        required
                        autofocus
                    >
                </label>

                <button class="button button--primary" type="submit">
                    Отправить ссылку
                </button>
            </form>
        <?php endif; ?>

        <p>
            <a href="<?= $theme->e($theme->route('admin_login')) ?>">
                Вернуться ко входу
            </a>
        </p>
    </div>
</section>
