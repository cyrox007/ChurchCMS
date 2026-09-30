<?php

declare(strict_types=1);

$connections = is_array($connections ?? null) ? $connections : [];
$adapters = is_array($adapters ?? null) ? $adapters : [];
$inboxItems = is_array($inboxItems ?? null) ? $inboxItems : [];
$importOwners = is_array($importOwners ?? null) ? $importOwners : [];
$outboundFailures = is_array($outboundFailures ?? null) ? $outboundFailures : [];
$canRetryOutbound = ($canRetryOutbound ?? false) === true;
$canLinkExternal = ($canLinkExternal ?? false) === true;
$canImportExternal = ($canImportExternal ?? false) === true;
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

    <section class="admin-operations-section" aria-labelledby="external-errors-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Исходящая очередь</p>
                <h2 id="external-errors-title">Ошибки отправки</h2>
                <p>Здесь остаются записи, исчерпавшие автоматические попытки. Повторная отправка снова выполняется только фоновым обработчиком.</p>
            </div>
        </div>

        <?php if ($outboundFailures === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Ошибок отправки нет',
                'message' => 'Dead-letter очередь внешних публикаций пуста.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($outboundFailures as $failure): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e((string) ($failure['publication_title'] ?? 'Публикация')) ?></strong>
                            <span>
                                <?= $theme->e((string) ($failure['connection_name'] ?? 'Внешний канал')) ?>
                                <?php if (!empty($failure['provider'])): ?>
                                    · <?= $theme->e((string) $failure['provider']) ?>
                                <?php endif; ?>
                                · попыток: <?= $theme->e((int) ($failure['attempts'] ?? 0)) ?>
                            </span>
                            <?php if (!empty($failure['last_error'])): ?>
                                <small><?= $theme->e((string) $failure['last_error']) ?></small>
                            <?php endif; ?>
                        </div>

                        <?php if ($canRetryOutbound): ?>
                            <form
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_external_channels_outbox_retry',
                                    ['publicId' => (string) ($failure['post_public_id'] ?? '')],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>
                                <button class="button button--quiet" type="submit">
                                    Повторить отправку
                                </button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-operations-section" aria-labelledby="external-inbox-title">
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Входящие материалы</p>
                <h2 id="external-inbox-title">Очередь проверки</h2>
                <p>Внешний текст показывается только как обычный текст. Он не считается доверенным HTML и не публикуется автоматически.</p>
            </div>
        </div>

        <?php if ($inboxItems === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Очередь пуста',
                'message' => 'Новых входящих материалов сейчас нет.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($inboxItems as $item): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($item->title ?? 'Материал без заголовка') ?></strong>
                            <span>
                                Тип: <?= $theme->e($item->kind) ?>
                                · внешний ID: <?= $theme->e($item->remoteId) ?>
                            </span>
                            <?php if ($item->canonicalUrl !== null): ?>
                                <small><?= $theme->e($item->canonicalUrl) ?></small>
                            <?php endif; ?>
                            <?php if (trim($item->bodyText) !== ''): ?>
                                <p><?= nl2br($theme->e($item->bodyText), false) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <form
                                method="post"
                                action="<?= $theme->e($theme->route(
                                    'admin_external_channels_inbox_ignore',
                                    ['publicId' => $item->publicId],
                                )) ?>"
                            >
                                <?= $theme->csrfInput() ?>
                                <button class="button button--quiet" type="submit">
                                    Игнорировать
                                </button>
                            </form>

                            <?php if ($canImportExternal && $importOwners !== []): ?>
                                <form
                                    class="admin-update-apply"
                                    method="post"
                                    action="<?= $theme->e($theme->route(
                                        'admin_external_channels_inbox_import',
                                        ['publicId' => $item->publicId],
                                    )) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <label>
                                        <span>Организация-владелец нового черновика</span>
                                        <select
                                            name="owner_organization_public_id"
                                            required
                                        >
                                            <option value="">Выберите организацию</option>
                                            <?php foreach ($importOwners as $owner): ?>
                                                <option
                                                    value="<?= $theme->e((string) ($owner['public_id'] ?? '')) ?>"
                                                >
                                                    <?= $theme->e((string) ($owner['name'] ?? '')) ?>
                                                    <?php if (!empty($owner['path'])): ?>
                                                        · <?= $theme->e((string) $owner['path']) ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <button
                                        class="button button--primary"
                                        type="submit"
                                    >
                                        Импортировать как черновик
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($canLinkExternal): ?>
                                <form
                                    class="admin-update-apply"
                                    method="post"
                                    action="<?= $theme->e($theme->route(
                                        'admin_external_channels_inbox_link',
                                        ['publicId' => $item->publicId],
                                    )) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <label>
                                        <span>Публичный ID публикации</span>
                                        <input
                                            type="text"
                                            name="publication_public_id"
                                            required
                                            autocomplete="off"
                                        >
                                    </label>
                                    <button class="button button--quiet" type="submit">
                                        Связать с публикацией
                                    </button>
                                </form>
                            <?php endif; ?>
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
                            <?php
                            $canTest = ($adapter['can_test'] ?? false) === true;
                            $canPublish = ($adapter['can_publish'] ?? false) === true;
                            $canImport = ($adapter['can_import'] ?? false) === true;
                            $usable = $canTest && ($canPublish || $canImport);
                            $directionLabel = match (true) {
                                $canPublish && $canImport => 'исходящие + входящие',
                                $canPublish => 'только исходящие',
                                $canImport => 'только входящие',
                                default => 'нет доступных направлений',
                            };
                            ?>
                            <option
                                value="<?= $theme->e((string) ($adapter['id'] ?? '')) ?>"
                                <?= $usable ? '' : 'disabled' ?>
                            >
                                <?= $theme->e((string) ($adapter['label'] ?? $adapter['id'] ?? '')) ?>
                                — <?= $theme->e($directionLabel) ?>
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

                <p>
                    Выберите только направления, которые указаны рядом с выбранной платформой.
                    ChurchCMS повторно проверит это на сервере.
                </p>

                <label>
                    <input
                        type="checkbox"
                        name="outbound_enabled"
                        value="1"
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
