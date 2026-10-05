<?php

declare(strict_types=1);

$items = is_array($items ?? null) ? $items : [];
$organizationUnits = is_array($organizationUnits ?? null) ? $organizationUnits : [];
$canManage = ($canManage ?? false) === true;
$defaultOwnerPublicId = (string) ($defaultOwnerPublicId ?? '');
?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Приходская жизнь</p>
            <h1>Библиотека</h1>
            <p>Каталог книг и других изданий с данными автора, издательства, ISBN, места хранения и доступности.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <section class="admin-operations-section">
            <h2>Новое издание</h2>
            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_library_create')) ?>">
                <?= $theme->csrfInput() ?>
                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><option value="">Выберите организацию</option><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $defaultOwnerPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                <label><span>Название</span><input name="title" maxlength="500" required></label>
                <label><span>Автор</span><input name="author_name" maxlength="500"></label>
                <label><span>Издательство</span><input name="publisher_name" maxlength="255"></label>
                <label><span>Год издания</span><input type="number" name="publication_year" min="1" max="2200"></label>
                <label><span>ISBN</span><input name="isbn" maxlength="32"></label>
                <label><span>Шифр / место хранения</span><input name="shelf_code" maxlength="100"></label>
                <label><span>Доступность</span><input name="availability_note" maxlength="500" placeholder="Например: доступна в читальном зале"></label>
                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"></textarea></label>
                <label><span>Полное описание</span><textarea name="description" rows="6"></textarea></label>
                <label><span>Порядок</span><input type="number" name="sort_order" value="0"></label>
                <button class="button button--primary" type="submit">Добавить издание</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="admin-operations-section">
        <h2>Каталог</h2>
        <?php if ($items === []): ?>
            <?= $theme->component('admin.state', ['kind' => 'empty', 'title' => 'Каталог пока пуст', 'message' => 'Добавьте первое издание через форму выше.']) ?>
        <?php else: ?>
            <div class="admin-operations-list" role="list">
                <?php foreach ($items as $item): ?>
                    <article class="admin-operations-row" role="listitem">
                        <div class="admin-operations-row__body">
                            <strong><?= $theme->e($item->title) ?></strong>
                            <span><?= $theme->e($item->status) ?></span>
                            <?php if ($item->authorName !== null): ?><small><?= $theme->e($item->authorName) ?></small><?php endif; ?>
                            <small>владелец: <?= $theme->e($item->ownerOrganizationPublicId) ?></small>
                        </div>
                        <?php if ($canManage): ?>
                            <form class="admin-update-apply" method="post" action="<?= $theme->e($theme->route('admin_library_update', ['publicId' => $item->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <label><span>Организация-владелец</span><select name="owner_organization_public_id" required><?php foreach ($organizationUnits as $unit): ?><option value="<?= $theme->e($unit->publicId) ?>" <?= $unit->publicId === $item->ownerOrganizationPublicId ? 'selected' : '' ?>><?= $theme->e($unit->name) ?></option><?php endforeach; ?></select></label>
                                <label><span>Название</span><input name="title" maxlength="500" required value="<?= $theme->e($item->title) ?>"></label>
                                <label><span>Автор</span><input name="author_name" maxlength="500" value="<?= $theme->e($item->authorName ?? '') ?>"></label>
                                <label><span>Издательство</span><input name="publisher_name" maxlength="255" value="<?= $theme->e($item->publisherName ?? '') ?>"></label>
                                <label><span>Год издания</span><input type="number" name="publication_year" min="1" max="2200" value="<?= $item->publicationYear === null ? '' : (int) $item->publicationYear ?>"></label>
                                <label><span>ISBN</span><input name="isbn" maxlength="32" value="<?= $theme->e($item->isbn ?? '') ?>"></label>
                                <label><span>Шифр / место хранения</span><input name="shelf_code" maxlength="100" value="<?= $theme->e($item->shelfCode ?? '') ?>"></label>
                                <label><span>Доступность</span><input name="availability_note" maxlength="500" value="<?= $theme->e($item->availabilityNote ?? '') ?>"></label>
                                <label><span>Краткое описание</span><textarea name="summary" rows="3" maxlength="2000"><?= $theme->e($item->summary) ?></textarea></label>
                                <label><span>Полное описание</span><textarea name="description" rows="6"><?= $theme->e($item->descriptionHtml) ?></textarea></label>
                                <label><span>Порядок</span><input type="number" name="sort_order" value="<?= (int) $item->sortOrder ?>"></label>
                                <button class="button button--primary" type="submit">Сохранить</button>
                            </form>
                            <div class="admin-actions">
                                <?php if ($item->status === 'draft'): ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_library_publish', ['publicId' => $item->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Опубликовать</button></form>
                                <?php else: ?>
                                    <form method="post" action="<?= $theme->e($theme->route('admin_library_unpublish', ['publicId' => $item->publicId])) ?>"><?= $theme->csrfInput() ?><button class="button button--quiet" type="submit">Снять с публикации</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
