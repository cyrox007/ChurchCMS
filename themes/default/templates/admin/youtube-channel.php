<?php

declare(strict_types=1);

$connections = is_array($connections ?? null) ? $connections : [];
$status = is_array($channelStatus ?? null) ? $channelStatus : null;
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Внешние каналы</p>
            <h1>YouTube</h1>
            <p>Настройте получение видео и исходящую загрузку через YouTube Data API.</p>
        </div>
        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_external_channels')) ?>">
            Все внешние каналы
        </a>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <section class="admin-operations-section" aria-labelledby="youtube-connections-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Подключения</p>
                <h2 id="youtube-connections-title">Настроенные каналы</h2>
                <p>Секреты и OAuth-токены после сохранения обратно не отображаются.</p>
            </div>
        </div>

        <?php if ($connections === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'YouTube пока не подключён',
                'message' => 'Добавьте канал через форму ниже.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($connections as $connection): ?>
                    <?php
                    $privacy = (string) (
                        $connection->settings['youtube_privacy']
                        ?? 'unlisted'
                    );
                    $categoryId = (string) (
                        $connection->settings['youtube_category_id']
                        ?? '22'
                    );
                    ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($connection->name) ?></strong>
                            <span><?= $theme->e($connection->targetRef) ?></span>
                            <small>
                                исходящие: <?= $connection->outboundEnabled ? 'да' : 'нет' ?>
                                · входящие: <?= $connection->inboundEnabled ? 'да' : 'нет' ?>
                                · приватность новых видео: <?= $theme->e($privacy) ?>
                                · категория: <?= $theme->e($categoryId) ?>
                            </small>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-operations-section" aria-labelledby="youtube-create-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новое подключение</p>
                <h2 id="youtube-create-title">Подключить YouTube</h2>
                <p>Для входящей синхронизации нужен API key. Для загрузки видео дополнительно нужен OAuth-доступ.</p>
            </div>
        </div>

        <form
            class="admin-update-apply"
            method="post"
            action="<?= $theme->e($theme->route('admin_external_channels_youtube_create')) ?>"
        >
            <?= $theme->csrfInput() ?>

            <label>
                <span>Название подключения</span>
                <input
                    type="text"
                    name="name"
                    maxlength="120"
                    required
                    autocomplete="off"
                    placeholder="Например, YouTube епархии"
                >
            </label>

            <label>
                <span>ID канала YouTube</span>
                <input
                    type="text"
                    name="target_ref"
                    maxlength="255"
                    required
                    autocomplete="off"
                    placeholder="UC..."
                >
            </label>

            <fieldset>
                <legend>Доступ к YouTube Data API</legend>

                <label>
                    <span>API key</span>
                    <input
                        type="password"
                        name="api_key"
                        required
                        autocomplete="new-password"
                    >
                </label>

                <p>API key используется для проверки канала и входящей синхронизации. Для исходящей загрузки одного API key недостаточно.</p>
            </fieldset>

            <fieldset>
                <legend>OAuth для исходящей загрузки</legend>

                <label>
                    <span>Access token <small>короткоживущий вариант</small></span>
                    <input
                        type="password"
                        name="access_token"
                        autocomplete="new-password"
                    >
                </label>

                <p>Для постоянной работы лучше использовать refresh token и пару client ID / client secret. Тогда ChurchCMS обновляет access token перед отправкой автоматически.</p>

                <label>
                    <span>Refresh token</span>
                    <input
                        type="password"
                        name="refresh_token"
                        autocomplete="new-password"
                    >
                </label>

                <label>
                    <span>Client ID</span>
                    <input
                        type="text"
                        name="client_id"
                        autocomplete="off"
                    >
                </label>

                <label>
                    <span>Client secret</span>
                    <input
                        type="password"
                        name="client_secret"
                        autocomplete="new-password"
                    >
                </label>
            </fieldset>

            <fieldset>
                <legend>Параметры новых видео</legend>

                <label>
                    <span>Приватность после загрузки</span>
                    <select name="privacy">
                        <option value="unlisted" selected>По ссылке</option>
                        <option value="private">Приватное</option>
                        <option value="public">Публичное</option>
                    </select>
                </label>

                <label>
                    <span>ID категории YouTube</span>
                    <input
                        type="text"
                        name="category_id"
                        inputmode="numeric"
                        pattern="[0-9]{1,4}"
                        maxlength="4"
                        value="22"
                        required
                    >
                </label>

                <p>Категория 22 используется как безопасное значение по умолчанию. При необходимости укажите другой числовой ID категории YouTube.</p>
            </fieldset>

            <label>
                <input
                    type="checkbox"
                    name="outbound_enabled"
                    value="1"
                >
                <span>Разрешить загрузку видео в YouTube</span>
            </label>

            <label>
                <input
                    type="checkbox"
                    name="inbound_enabled"
                    value="1"
                >
                <span>Разрешить получение видео из YouTube</span>
            </label>

            <button class="button button--primary" type="submit">
                Проверить и подключить YouTube
            </button>
        </form>
    </section>
</section>
