<?php declare(strict_types=1); ?>
<section class="comments" id="comments" aria-labelledby="comments-title">
    <header class="comments__header">
        <div>
            <p class="eyebrow">Обсуждение</p>
            <h2 id="comments-title">Комментарии</h2>
        </div>
        <span class="comments__count"><?= $theme->e(count(is_array($comments) ? $comments : [])) ?></span>
    </header>

    <?php if (is_array($commentFlash ?? null)): ?>
        <div class="comment-notice comment-notice--<?= $theme->e(($commentFlash['type'] ?? '') === 'error' ? 'error' : 'success') ?>" role="status">
            <?= $theme->e($commentFlash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($comments)): ?>
        <p class="comments__empty">
            <?= !empty($commentsOpen)
                ? 'Пока комментариев нет. Можно начать обсуждение.'
                : 'Обсуждение закрыто.' ?>
        </p>
    <?php else: ?>
        <ol class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <li class="comment">
                    <div class="comment__meta">
                        <strong><?= $theme->e($comment->displayName) ?></strong>
                        <time datetime="<?= $theme->e($comment->createdAt->format(DATE_ATOM)) ?>">
                            <?= $theme->e($comment->createdAt->format('d.m.Y H:i')) ?>
                        </time>
                    </div>
                    <div class="comment__body"><?= nl2br($theme->e($comment->bodyText), false) ?></div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if (!empty($commentsOpen)): ?>
    <form
        class="comment-form"
        method="post"
        action="<?= $theme->e($theme->route('comment_submit', ['slug' => $publication->slug])) ?>"
    >
        <input
            type="hidden"
            name="public_form_token"
            value="<?= $theme->e($theme->publicFormToken('comments.submit')) ?>"
        >

        <h3>Оставить комментарий</h3>
        <p>Комментарий появится после проверки модератором.</p>

        <div class="comment-form__grid">
            <label class="field">
                <span>Ваше имя</span>
                <input
                    type="text"
                    name="display_name"
                    maxlength="100"
                    autocomplete="name"
                    required
                >
            </label>

            <label class="field">
                <span>Email <small>не публикуется</small></span>
                <input
                    type="email"
                    name="email"
                    maxlength="255"
                    autocomplete="email"
                >
            </label>
        </div>

        <label class="field">
            <span>Комментарий</span>
            <textarea
                name="body_text"
                maxlength="<?= $theme->e((int) ($commentsMaxLength ?? 4000)) ?>"
                required
            ></textarea>
        </label>

        <button class="button button--primary" type="submit">Отправить комментарий</button>
    </form>
    <?php else: ?>
        <p class="comments__closed">
            Обсуждение закрыто. Ранее опубликованные комментарии сохранены.
        </p>
    <?php endif; ?>
</section>
