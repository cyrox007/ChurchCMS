<?php

declare(strict_types=1);

$links = is_array($links ?? null) ? $links : [];
$organizations = is_array($organizations ?? null)
    ? $organizations
    : [];
$preview = is_array($preview ?? null) ? $preview : null;
$error = is_string($federationError ?? null)
    ? $federationError
    : null;
$form = is_array($federationForm ?? null)
    ? $federationForm
    : [];
$status = is_array($federationStatus ?? null)
    ? $federationStatus
    : null;

$organizationNames = [];
foreach ($organizations as $organization) {
    if (
        $organization instanceof
        \ChurchCMS\Modules\Organizations\OrganizationUnit
    ) {
        $organizationNames[$organization->id] =
            $organization->name;
    }
}

$relationLabels = [
    'parent' => 'Вышестоящий узел',
    'child' => 'Нижестоящий узел',
    'peer' => 'Равноправный узел',
];

$statusLabels = [
    'pending' => 'Ожидает проверки',
    'active' => 'Доступен',
    'error' => 'Ошибка связи',
    'conflict' => 'Конфликт identity',
    'revoked' => 'Доверие отозвано',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Федерация ChurchCMS</p>
            <h1>Связи между самостоятельными сайтами</h1>
            <p>
                Подключайте приход, благочиние, епархию, митрополию
                или другой ChurchCMS без общей базы данных и без
                передачи административного доступа.
            </p>
        </div>
    </header>

    <?php if ($status !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => (string) ($status['kind'] ?? 'empty'),
            'title' => (string) ($status['title'] ?? ''),
            'message' => (string) ($status['message'] ?? ''),
        ]) ?>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Discovery не выполнен',
            'message' => $error,
        ]) ?>
    <?php endif; ?>

    <section class="editor-card editor-card--primary">
        <div class="admin-heading">
            <div>
                <p class="eyebrow">Шаг 1</p>
                <h2>Проверить удалённый ChurchCMS</h2>
                <p>
                    На этом шаге credential не передаётся и связь
                    ещё не создаётся.
                </p>
            </div>
        </div>

        <form
            method="post"
            action="<?= $theme->e(
                $theme->route('admin_federation_discover')
            ) ?>"
        >
            <?= $theme->csrfInput() ?>

            <label class="field">
                <span>Адрес удалённого сайта</span>
                <input
                    type="url"
                    name="remote_base_url"
                    value="<?= $theme->e(
                        (string) ($form['remote_base_url'] ?? '')
                    ) ?>"
                    placeholder="https://eparhia.example"
                    required
                    maxlength="500"
                >
                <small>
                    Публичные узлы проверяются только по HTTPS.
                    Частные адреса по умолчанию отключены.
                </small>
            </label>

            <div class="editor-actions">
                <button
                    class="button button--primary"
                    type="submit"
                >
                    Проверить узел
                </button>
            </div>
        </form>
    </section>

    <?php if ($preview !== null): ?>
        <?php
        $remoteOrganization = is_array(
            $preview['organization'] ?? null
        )
            ? $preview['organization']
            : [];
        ?>
        <section class="editor-card">
            <div class="admin-heading">
                <div>
                    <p class="eyebrow">Шаг 2</p>
                    <h2>Подтвердить связь</h2>
                    <p>
                        Перед сохранением ChurchCMS повторно выполнит
                        discovery и не будет доверять значениям из формы.
                    </p>
                </div>
            </div>

            <dl class="admin-summary">
                <div>
                    <dt>Организация</dt>
                    <dd><?= $theme->e(
                        (string) ($remoteOrganization['name'] ?? '')
                    ) ?></dd>
                </div>
                <div>
                    <dt>Профиль</dt>
                    <dd><?= $theme->e(
                        (string) ($preview['profile'] ?? '')
                    ) ?></dd>
                </div>
                <div>
                    <dt>Instance ID</dt>
                    <dd><code><?= $theme->e(
                        (string) ($preview['instance_id'] ?? '')
                    ) ?></code></dd>
                </div>
                <div>
                    <dt>Адрес</dt>
                    <dd><?= $theme->e(
                        (string) ($preview['base_url'] ?? '')
                    ) ?></dd>
                </div>
            </dl>

            <form
                method="post"
                action="<?= $theme->e(
                    $theme->route('admin_federation_connect')
                ) ?>"
            >
                <?= $theme->csrfInput() ?>
                <input
                    type="hidden"
                    name="remote_base_url"
                    value="<?= $theme->e(
                        (string) ($preview['base_url'] ?? '')
                    ) ?>"
                >

                <div class="editor-grid">
                    <label class="field">
                        <span>Локальная организация</span>
                        <select
                            name="local_organization_public_id"
                            required
                        >
                            <option value="" disabled selected>
                                Выберите организацию
                            </option>
                            <?php foreach ($organizations as $organization): ?>
                                <?php if (
                                    $organization instanceof
                                    \ChurchCMS\Modules\Organizations\OrganizationUnit
                                ): ?>
                                    <option
                                        value="<?= $theme->e(
                                            $organization->publicId
                                        ) ?>"
                                    >
                                        <?= $theme->e(
                                            $organization->name
                                        ) ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="field">
                        <span>Отношение</span>
                        <select name="relation" required>
                            <?php foreach (
                                $relationLabels
                                as $relation => $label
                            ): ?>
                                <option
                                    value="<?= $theme->e($relation) ?>"
                                >
                                    <?= $theme->e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="editor-grid">
                    <label class="field">
                        <span>Входящие scopes</span>
                        <input
                            name="inbound_scopes"
                            placeholder="publications.read events.read"
                        >
                        <small>
                            Что этот сайт разрешает принимать от
                            удалённого узла.
                        </small>
                    </label>

                    <label class="field">
                        <span>Исходящие scopes</span>
                        <input
                            name="outbound_scopes"
                            placeholder="publications.read documents.read"
                        >
                        <small>
                            Что удалённый узел сможет запрашивать у
                            этого сайта после реализации соответствующих
                            контрактов синхронизации.
                        </small>
                    </label>
                </div>

                <label class="field">
                    <span>Credential удалённого узла <small>необязательно</small></span>
                    <input
                        type="password"
                        name="outbound_token"
                        autocomplete="new-password"
                    >
                    <small>
                        Значение шифруется перед сохранением и не
                        отображается повторно в Admin Shell.
                    </small>
                </label>

                <div class="editor-actions">
                    <button
                        class="button button--primary"
                        type="submit"
                    >
                        Создать связь
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="editor-card">
        <div class="admin-heading">
            <div>
                <p class="eyebrow">Доверенные связи</p>
                <h2>Подключённые ChurchCMS</h2>
            </div>
        </div>

        <?php if ($links === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Связей пока нет',
                'message' => 'Проверьте удалённый ChurchCMS и подтвердите связь выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-list">
                <?php foreach ($links as $link): ?>
                    <article class="admin-list__item">
                        <div>
                            <div class="organization-tree__meta">
                                <span><?= $theme->e(
                                    $relationLabels[$link->relation]
                                    ?? $link->relation
                                ) ?></span>
                                <span class="status-pill<?= in_array(
                                    $link->status,
                                    ['revoked', 'error', 'conflict'],
                                    true,
                                ) ? ' status-pill--withdrawn' : '' ?>">
                                    <?= $theme->e(
                                        $statusLabels[$link->status]
                                        ?? $link->status
                                    ) ?>
                                </span>
                            </div>

                            <h3><?= $theme->e(
                                $link->remoteName
                                ?? $link->remoteBaseUrl
                            ) ?></h3>

                            <p><?= $theme->e(
                                $link->remoteBaseUrl
                            ) ?></p>

                            <small>
                                Локальная организация:
                                <?= $theme->e(
                                    $organizationNames[
                                        $link->localOrganizationId
                                    ] ?? ('#' . $link->localOrganizationId)
                                ) ?>
                            </small>

                            <?php if (
                                $link->inboundScopes !== []
                                || $link->outboundScopes !== []
                            ): ?>
                                <p>
                                    Входящие:
                                    <?= $theme->e(
                                        implode(
                                            ', ',
                                            $link->inboundScopes
                                        )
                                    ) ?>
                                    · Исходящие:
                                    <?= $theme->e(
                                        implode(
                                            ', ',
                                            $link->outboundScopes
                                        )
                                    ) ?>
                                </p>
                            <?php endif; ?>

                            <p>
                                Последний ответ discovery:
                                <?= $theme->e(
                                    $link->lastSeenAt
                                    ?? 'ещё не получен'
                                ) ?>
                            </p>

                            <?php if ($link->lastError !== null): ?>
                                <p>
                                    <strong>Состояние:</strong>
                                    <?= $theme->e($link->lastError) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($link->syncCursor !== null): ?>
                                <small>
                                    Курсор синхронизации сохранён.
                                </small>
                            <?php endif; ?>
                        </div>

                        <?php if ($link->status !== 'revoked'): ?>
                            <div>
                                <form
                                    method="post"
                                    action="<?= $theme->e(
                                        $theme->route(
                                            'admin_federation_check',
                                            ['publicId' => $link->publicId],
                                        )
                                    ) ?>"
                                >
                                    <?= $theme->csrfInput() ?>
                                    <button
                                        class="button button--primary"
                                        type="submit"
                                    >
                                        Проверить состояние
                                    </button>
                                </form>

                                <form
                                    method="post"
                                    action="<?= $theme->e(
                                        $theme->route(
                                            'admin_federation_revoke',
                                            ['publicId' => $link->publicId],
                                        )
                                    ) ?>"
                                >
                                    <?= $theme->csrfInput() ?>

                                    <label class="field">
                                        <span>
                                            <input
                                                type="checkbox"
                                                name="confirm_link"
                                                value="<?= $theme->e(
                                                    $link->publicId
                                                ) ?>"
                                                required
                                            >
                                            Подтверждаю отзыв доверия
                                        </span>
                                    </label>

                                    <button
                                        class="button button--quiet"
                                        type="submit"
                                    >
                                        Отозвать доверие
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="admin-operations-note">
        <strong>Связь не выдаёт удалённому сайту права администратора.</strong>
        <p>
            Federation link хранит только техническое доверие и scopes.
            Отзыв доверия очищает сохранённый credential, но оставляет
            запись для аудита и последующего безопасного переподключения.
        </p>
    </aside>
</section>
