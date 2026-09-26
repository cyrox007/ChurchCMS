<?php declare(strict_types=1); ?>
<section class="admin-shell">
    <header class="admin-heading">
        <div>
            <p class="eyebrow">Комментарии</p>
            <h1>На проверке</h1>
            <p>Оставляйте только полезные и корректные сообщения. Остальное можно отклонить или отметить как спам.</p>
        </div>
    </header>

    <?php if (empty($comments)): ?>
        <section class="empty-state">
            <h2>Всё проверено</h2>
            <p>Новых комментариев сейчас нет.</p>
        </section>
    <?php else: ?>
        <div class="moderation-list">
            <?php foreach ($comments as $comment): ?>
                <article class="moderation-card">
                    <div class="moderation-card__context">
                        <p class="card__eyebrow">Публикация</p>
                        <?php if (!empty($comment->publicationSlug)): ?>
                            <a href="<?= $theme->e($theme->route('publication_show', ['slug' => $comment->publicationSlug])) ?>" target="_blank" rel="noopener">
                                <?= $theme->e($comment->publicationTitle ?? 'Открыть публикацию') ?>
                            </a>
                        <?php else: ?>
                            <strong><?= $theme->e($comment->publicationTitle ?? 'Публикация') ?></strong>
                        <?php endif; ?>
                    </div>

                    <div class="moderation-card__author">
                        <strong><?= $theme->e($comment->displayName) ?></strong>
                        <time datetime="<?= $theme->e($comment->createdAt->format(DATE_ATOM)) ?>">
                            <?= $theme->e($comment->createdAt->format('d.m.Y H:i')) ?>
                        </time>
                        <?php if (!empty($comment->email)): ?>
                            <span><?= $theme->e($comment->email) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="moderation-card__text">
                        <?= nl2br($theme->e($comment->bodyText), false) ?>
                    </div>

                    <div class="moderation-actions">
                        <?php foreach ([
                            'approved' => ['Одобрить', 'moderation-action--approve'],
                            'rejected' => ['Отклонить', 'moderation-action--reject'],
                            'spam' => ['Спам', 'moderation-action--spam'],
                        ] as $status => [$label, $class]): ?>
                            <form method="post" action="<?= $theme->e($theme->route('admin_comments_moderate', ['publicId' => $comment->publicId])) ?>">
                                <?= $theme->csrfInput() ?>
                                <input type="hidden" name="status" value="<?= $theme->e($status) ?>">
                                <button class="moderation-action <?= $theme->e($class) ?>" type="submit"><?= $theme->e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
