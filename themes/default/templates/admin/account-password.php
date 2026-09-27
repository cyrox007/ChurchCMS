<?php

declare(strict_types=1);

$status = is_array($passwordStatus ?? null)
    ? $passwordStatus
    : null;
$error = trim((string) ($passwordError ?? ''));
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Аккаунт</p>
            <h1>Смена пароля</h1>
            <p>После сохранения все другие административные сессии этого пользователя перестанут работать.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'success'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Пароль не изменён',
            'message' => $error,
        ]) ?>
    <?php endif; ?>

    <form
        class="admin-account-form"
        method="post"
        action="<?= $theme->e($theme->route('admin_account_password_update')) ?>"
    >
        <?= $theme->csrfInput() ?>

        <label class="field">
            <span>Текущий пароль</span>
            <input
                type="password"
                name="current_password"
                autocomplete="current-password"
                required
                maxlength="4096"
            >
        </label>

        <label class="field">
            <span>Новый пароль</span>
            <input
                type="password"
                name="new_password"
                autocomplete="new-password"
                required
                minlength="12"
                maxlength="4096"
            >
            <small>Не менее 12 символов. Новый пароль должен отличаться от текущего.</small>
        </label>

        <label class="field">
            <span>Повторите новый пароль</span>
            <input
                type="password"
                name="new_password_confirm"
                autocomplete="new-password"
                required
                minlength="12"
                maxlength="4096"
            >
        </label>

        <div class="admin-form-actions">
            <button class="button button--primary" type="submit">Изменить пароль</button>
        </div>
    </form>
</section>
