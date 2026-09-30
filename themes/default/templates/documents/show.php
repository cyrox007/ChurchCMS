<?php

declare(strict_types=1);

$document = is_array($document ?? null) ? $document : [];
$file = is_array($document['file'] ?? null)
    ? $document['file']
    : null;
?>
<article class="article-shell">
    <header class="article-header">
        <p class="eyebrow">
            <?= $theme->e((string) ($document['document_type'] ?? 'document')) ?>
        </p>
        <h1><?= $theme->e((string) ($document['title'] ?? 'Документ')) ?></h1>
        <p>
            <?php if (!empty($document['document_number'])): ?>
                № <?= $theme->e((string) $document['document_number']) ?>
            <?php endif; ?>
            <?php if (!empty($document['issued_on'])): ?>
                · <?= $theme->e((string) $document['issued_on']) ?>
            <?php endif; ?>
        </p>
    </header>

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

    <?php if ($file !== null && !empty($file['url'])): ?>
        <p>
            <a
                class="button button--primary"
                href="<?= $theme->e((string) $file['url']) ?>"
            >
                Открыть файл
            </a>
        </p>
        <p>
            <small>
                <?= $theme->e((string) ($file['mime_type'] ?? '')) ?>
                · <?= $theme->e((int) ($file['bytes'] ?? 0)) ?> байт
            </small>
        </p>
    <?php else: ?>
        <p>Публичный файл для этого документа не прикреплён.</p>
    <?php endif; ?>
</article>
