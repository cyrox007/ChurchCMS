<?php

declare(strict_types=1);

$connections = is_array($connections ?? null) ? $connections : [];
$adapters = is_array($adapters ?? null) ? $adapters : [];
$status = is_array($channelStatus ?? null) ? $channelStatus : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Интеграции</p>
            <h1>Внешние каналы</h1>
            <p>Подключайте соцсети, мессенджеры и видеоплатформы через зарегистрированные адаптеры.</p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="external-connections-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Подключения</p>
                <h2 id="external-connections-title">Настроенные каналы</h2>
                <p>Секреты никогда не выводятся обратно в интерфейс.</p>
            </div>
        </div>

        <?php if ($connections === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Подключений пока нет',
                'message' => 'Добавьте первый канал через форму ниже.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($connections as $connection): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($connection->name) ?></strong>
                            <span>
                                <?= $theme->e($connection->provider) ?>
                                · <?= $theme->e($connection->targetRef) ?>
                            </span>
                            <small>
                                <?= $connection->enabled ? 'включено' : 'выключено' ?>
                                · исходящие: <?= $connection->outboundEnabled ? 'да' : 'нет' ?>
                                · входящие: <?= $connection->inboundEnabled ? 'да' : 'нет' ?>
                            </small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-operations-section" aria-labelledby="external-connect-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новое подключение</p>
                <h2 id="external-connect-title">Подключить канал</h2>
                <p>Перед сохранением ChurchCMS проверит канал через адаптер. При неудачной проверке секрет в БД не попадёт.</p>
            </div>
        </div>

        <?php if ($adapters === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Нет доступных адаптеров',
                'message' => 'Установите или включите модуль провайдера. Ядро ChurchCMS не содержит жёсткого списка платформ.',
            ]) ?>
        <?php else: ?>
            <form
                class="admin-update-apply"
                method="post"
                action="<?= $theme->e($theme->route('admin_external_channels_create')) ?>"
            >
                <?= $theme->csrfInput() ?>

                <label>
                    <span>Платформа</span>
                    <select name="provider" required>
                        <option value="">Выберите адаптер</option>
                        <?php foreach ($adapters as $adapter): ?>
                            <?php $canTest = ($adapter['can_test'] ?? false) === true; ?>
                            <option
                                value="<?= $theme->e((string) ($adapter['id'] ?? '')) ?>"
                                <?= $canTest ? '' : 'disabled' ?>
                            >
                                <?= $theme->e((string) ($adapter['label'] ?? $adapter['id'] ?? '')) ?>
                                <?= $canTest ? '' : ' — проверка подключения не поддерживается' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Название подключения</span>
                    <input
                        type="text"
                        name="name"
                        maxlength="120"
                        required
                        autocomplete="off"
                        placeholder="Например, Новости прихода"
                    >
                </label>

                <label>
                    <span>Идентификатор канала</span>
                    <input
                        type="text"
                        name="target_ref"
                        maxlength="255"
                        required
                        autocomplete="off"
                        placeholder="ID, username или другой идентификатор провайдера"
                    >
                </label>

                <label>
                    <span>Секрет / токен</span>
                    <input
                        type="password"
                        name="credentials"
                        required
                        autocomplete="new-password"
                    >
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="outbound_enabled"
                        value="1"
                        checked
                    >
                    <span>Разрешить исходящую публикацию</span>
                </label>

                <label>
                    <input
                        type="checkbox"
                        name="inbound_enabled"
                        value="1"
                    >
                    <span>Разрешить получение материалов</span>
                </label>

                <button class="button button--primary" type="submit">
                    Проверить и подключить
                </button>
            </form>
        <?php endif; ?>
    </section>
</section>
