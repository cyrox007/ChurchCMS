<?php declare(strict_types=1);
$isEdit = isset($page) && $page !== null;
$formAction = $isEdit
    ? $theme->route('admin_page_update', ['publicId' => $page->publicId])
    : $theme->route('admin_page_create');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow"><?= $isEdit ? 'Редактирование' : 'Новый раздел' ?></p>
            <h1><?= $isEdit ? $theme->e($page->title) : 'Новая страница' ?></h1>
            <p><?= $isEdit ? 'Изменения дерева и владельца сохраняются одной операцией.' : 'Страница сначала сохранится как черновик.' ?></p>
        </div>
        <a class="button button--quiet" href="<?= $theme->e($theme->route('admin_pages')) ?>">← К страницам</a>
    </header>

    <?php if (!empty($error)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'error',
            'title' => 'Не удалось сохранить',
            'message' => (string) $error,
        ]) ?>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <?= $theme->component('admin.state', [
            'kind' => 'success',
            'title' => 'Сохранено',
            'message' => (string) $success,
        ]) ?>
    <?php endif; ?>

    <form class="publication-editor" method="post" action="<?= $theme->e($formAction) ?>">
        <?= $theme->csrfInput() ?>

        <section class="editor-card editor-card--primary">
            <label class="field field--title">
                <span>Название страницы</span>
                <input name="title" value="<?= $theme->e($form['title'] ?? '') ?>" required maxlength="255" autofocus>
            </label>

            <div class="editor-grid">
                <label class="field">
                    <span>Название в меню <small>необязательно</small></span>
                    <input name="navigation_title" value="<?= $theme->e($form['navigation_title'] ?? '') ?>" maxlength="255">
                </label>

                <label class="field">
                    <span>Организация-владелец</span>
                    <select name="owner_organization_public_id" required>
                        <option value="">Выберите организацию</option>
                        <?php foreach (($organizationUnits ?? []) as $organization): ?>
                            <option
                                value="<?= $theme->e($organization->publicId) ?>"
                                <?= ($form['owner_organization_public_id'] ?? '') === $organization->publicId ? 'selected' : '' ?>
                            >
                                <?= $theme->e($organization->name . ' · ' . $organization->path) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Показаны только активные организации в пределах ваших полномочий.</small>
                </label>
            </div>

            <label class="field">
                <span>Родительский раздел</span>
                <select name="parent_public_id">
                    <option value="">Корень сайта</option>
                    <?php foreach (($parentPages ?? []) as $parent): ?>
                        <option
                            value="<?= $theme->e($parent->publicId) ?>"
                            <?= ($form['parent_public_id'] ?? '') === $parent->publicId ? 'selected' : '' ?>
                        >
                            <?= $theme->e($parent->title . ' · ' . $parent->path) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Недоступные организации и собственное поддерево исключены из списка.</small>
            </label>

            <label class="field">
                <span>Содержимое страницы</span>
                <textarea class="editor-body" name="body" rows="18"><?= $theme->e($form['body'] ?? '') ?></textarea>
                <small>Можно писать обычным текстом. HTML будет очищен перед сохранением.</small>
            </label>
        </section>

        <details class="editor-card editor-advanced">
            <summary>Положение и адрес</summary>

            <div class="editor-grid">
                <label class="field">
                    <span>Адрес страницы</span>
                    <input name="slug" value="<?= $theme->e($form['slug'] ?? '') ?>" maxlength="180" placeholder="создастся автоматически">
                    <small>Изменение адреса безопасно пересчитает пути дочерних страниц.</small>
                </label>

                <label class="field">
                    <span>Порядок</span>
                    <input name="sort_order" type="number" min="0" max="1000000" value="<?= $theme->e((string) ($form['sort_order'] ?? 0)) ?>">
                </label>
            </div>
        </details>

        <div class="editor-actions">
            <button class="button button--primary" type="submit"><?= $isEdit ? 'Сохранить изменения' : 'Сохранить черновик' ?></button>
        </div>
    </form>

    <?php if ($isEdit && !empty($canPublish)): ?>
        <section class="publication-state-actions">
            <?php if ($page->status->value === 'published'): ?>
                <form method="post" action="<?= $theme->e($theme->route('admin_page_unpublish', ['publicId' => $page->publicId])) ?>">
                    <?= $theme->csrfInput() ?>
                    <button class="button button--quiet" type="submit">Снять раздел с публикации</button>
                </form>
                <p>Все дочерние страницы также станут черновиками.</p>
            <?php else: ?>
                <form method="post" action="<?= $theme->e($theme->route('admin_page_publish', ['publicId' => $page->publicId])) ?>">
                    <?= $theme->csrfInput() ?>
                    <button class="button button--primary" type="submit">Опубликовать</button>
                </form>
                <?php if ($page->parentId !== null): ?>
                    <p>Родительский раздел должен быть опубликован первым.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
