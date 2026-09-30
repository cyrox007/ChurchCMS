<?php

declare(strict_types=1);

$documents = is_array($documents ?? null) ? $documents : [];
?>
<section class="content-section">
    <header class="section-heading">
        <p class="eyebrow">Официальные материалы</p>
        <h1>Документы</h1>
        <p>Опубликованные документы организации.</p>
    </header>

    <?php if ($documents === []): ?>
        <p>Опубликованных документов пока нет.</p>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($documents as $document): ?>
                <article class="card">
                    <p class="eyebrow">
                        <?= $theme->e((string) ($document['document_type'] ?? 'document')) ?>
                        <?php if (!empty($document['issued_on'])): ?>
                            · <?= $theme->e((string) $document['issued_on']) ?>
                        <?php endif; ?>
                    </p>
                    <h2>
                        <a href="<?= $theme->e((string) ($document['url'] ?? '#')) ?>">
                            <?= $theme->e((string) ($document['title'] ?? 'Документ')) ?>
                        </a>
                    </h2>
                    <?php if (!empty($document['document_number'])): ?>
                        <p>№ <?= $theme->e((string) $document['document_number']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($document['categories']) && is_array($document['categories'])): ?>
                        <p>
                            <?php foreach ($document['categories'] as $category): ?>
                                <span class="tag"><?= $theme->e((string) ($category['name'] ?? '')) ?></span>
                            <?php endforeach; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($document['summary'])): ?>
                        <p><?= $theme->e((string) $document['summary']) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
