<?php

declare(strict_types=1);

$redirectRules = is_array($redirectRules ?? null)
    ? $redirectRules
    : [];
$status = is_array($redirectStatus ?? null)
    ? $redirectStatus
    : null;

$statusLabels = [
    301 => '301 · постоянный',
    302 => '302 · временный',
    307 => '307 · временный, метод сохраняется',
    308 => '308 · постоянный, метод сохраняется',
];
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">SEO и маршрутизация</p>
            <h1>Редиректы</h1>
            <p>
                Правила применяются к публичным GET/HEAD до обычной
                маршрутизации. Разрешены только локальные цели.
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

    <section
        class="admin-operations-section"
        aria-labelledby="redirect-create-title"
    >
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Новое правило</p>
                <h2 id="redirect-create-title">Добавить редирект</h2>
                <p>
                    Исходный путь задаётся без домена и query.
                    Цель может содержать локальный query string.
                </p>
            </div>
        </div>

        <form
            class="admin-update-apply"
            method="post"
            action="<?= $theme->e($theme->route('admin_redirect_create')) ?>"
        >
            <?= $theme->csrfInput() ?>

            <label>
                <span>Старый путь</span>
                <input
                    type="text"
                    name="source_path"
                    maxlength="700"
                    placeholder="/old-section/page"
                    required
                    autocomplete="off"
                >
            </label>

            <label>
                <span>Новый локальный путь</span>
                <input
                    type="text"
                    name="target_path"
                    maxlength="2000"
                    placeholder="/pages/new-section/page"
                    required
                    autocomplete="off"
                >
            </label>

            <label>
                <span>HTTP-код</span>
                <select name="status_code" required>
                    <?php foreach ($statusLabels as $code => $label): ?>
                        <option
                            value="<?= $theme->e($code) ?>"
                            <?= $code === 301 ? 'selected' : '' ?>
                        >
                            <?= $theme->e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="choice">
                <input
                    type="checkbox"
                    name="enabled"
                    value="1"
                    checked
                >
                <span>Включить правило сразу</span>
            </label>

            <button
                class="button button--primary"
                type="submit"
            >
                Добавить
            </button>
        </form>
    </section>

    <section
        class="admin-operations-section"
        aria-labelledby="redirect-list-title"
    >
        <div class="admin-operations-heading">
            <div>
                <p class="card__eyebrow">Текущие правила</p>
                <h2 id="redirect-list-title">Редиректы</h2>
                <p>
                    Активные правила не могут образовывать цепочки или циклы.
                </p>
            </div>
        </div>

        <?php if ($redirectRules === []): ?>
            <?= $theme->component('admin.state', [
                'kind' => 'empty',
                'title' => 'Редиректов пока нет',
                'message' => 'Добавьте первое правило через форму выше.',
            ]) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($redirectRules as $rule): ?>
                    <article
                        class="admin-operations-row"
                        role="listitem"
                    >
                        <div class="admin-operations-row__body">
                            <strong>
                                <?= $theme->e($rule->sourcePath) ?>
                                →
                                <?= $theme->e($rule->targetPath) ?>
                            </strong>
                            <span>
                                <?= $theme->e($statusLabels[$rule->statusCode] ?? $rule->statusCode) ?>
                                · <?= $rule->enabled ? 'включён' : 'выключен' ?>
                                · срабатываний: <?= $theme->e($rule->hitCount) ?>
                            </span>
                            <small>
                                Последнее срабатывание:
                                <?= $theme->e($rule->lastHitAt ?? 'ещё не было') ?>
                            </small>
                        </div>

                        <form
                            class="admin-update-apply"
                            method="post"
                            action="<?= $theme->e($theme->route(
                                'admin_redirect_update',
                                ['publicId' => $rule->publicId],
                            )) ?>"
                        >
                            <?= $theme->csrfInput() ?>

                            <label>
                                <span>Старый путь</span>
                                <input
                                    type="text"
                                    name="source_path"
                                    maxlength="700"
                                    required
                                    value="<?= $theme->e($rule->sourcePath) ?>"
                                >
                            </label>

                            <label>
                                <span>Новый локальный путь</span>
                                <input
                                    type="text"
                                    name="target_path"
                                    maxlength="2000"
                                    required
                                    value="<?= $theme->e($rule->targetPath) ?>"
                                >
                            </label>

                            <label>
                                <span>HTTP-код</span>
                                <select name="status_code" required>
                                    <?php foreach ($statusLabels as $code => $label): ?>
                                        <option
                                            value="<?= $theme->e($code) ?>"
                                            <?= $rule->statusCode === $code ? 'selected' : '' ?>
                                        >
                                            <?= $theme->e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="choice">
                                <input
                                    type="checkbox"
                                    name="enabled"
                                    value="1"
                                    <?= $rule->enabled ? 'checked' : '' ?>
                                >
                                <span>Правило включено</span>
                            </label>

                            <button
                                class="button button--primary"
                                type="submit"
                            >
                                Сохранить
                            </button>
                        </form>

                        <form
                            method="post"
                            action="<?= $theme->e($theme->route(
                                'admin_redirect_delete',
                                ['publicId' => $rule->publicId],
                            )) ?>"
                        >
                            <?= $theme->csrfInput() ?>
                            <button
                                class="button button--quiet"
                                type="submit"
                            >
                                Удалить
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
