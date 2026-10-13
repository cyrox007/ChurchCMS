<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

/**
 * Полнотекстовый RSS 2.0 для технического согласования с Новости Mail.
 *
 * Публичная документация Новости Mail не фиксирует открытый протокол
 * партнёрской доставки. Поэтому этот renderer формирует стандартный
 * полнотекстовый RSS и не объявляет его официальным форматом Mail.
 */
final class NewsMailSyndicationRenderer extends Rss2SyndicationRenderer
{
    public function render(SyndicationFeed $feed): string
    {
        // Формат совпадает с уже проверяемым полнотекстовым RSS-контрактом.
        return (new Smi2SyndicationRenderer())->render($feed);
    }
}
