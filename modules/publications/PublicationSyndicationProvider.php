<?php

declare(strict_types=1);

namespace ChurchCMS\Modules\Publications;

use ChurchCMS\Core\Config;
use ChurchCMS\Core\SyndicationEntry;
use ChurchCMS\Core\TargetAwareSyndicationProvider;
use ChurchCMS\Modules\Organizations\FederationRepository;

final class PublicationSyndicationProvider implements TargetAwareSyndicationProvider
{
    public function __construct(private readonly string $siteKey = 'default')
    {
    }

    public function entries(): iterable
    {
        return $this->buildEntries(null);
    }

    public function entriesForTarget(string $target): iterable
    {
        return $this->buildEntries($target);
    }

    /**
     * Список каналов, которые действительно настроены для редактора.
     * Само наличие поддержки канала в CMS не означает, что редактор должен
     * видеть его до подключения соответствующего источника или партнёра.
     *
     * @return array<string,array{label:string,hint:string}>
     */
    public static function editorTargets(
        string $siteKey = 'default',
    ): array {
        if (Config::get('syndication.enabled', true) !== true) {
            return [];
        }

        $targets = [];

        if (Config::get('syndication.targets.rss.enabled', true) === true) {
            $targets['rss'] = [
                'label' => 'RSS',
                'hint' => 'Новостная лента этого сайта',
            ];
        }

        if (self::hasActiveParentLink($siteKey)) {
            $targets['diocese'] = [
                'label' => 'Связанный вышестоящий сайт',
                'hint' => 'Материал доступен активной федеративной связи',
            ];
        }

        if (Config::get('syndication.targets.rambler.enabled', false) === true) {
            $targets['rambler'] = [
                'label' => 'Рамблер/Новости',
                'hint' => 'Канал включён в настройках сайта',
            ];
        }

        if (
            Config::get('syndication.targets.smi2.enabled', false) === true
            && Config::get(
                'syndication.targets.smi2.partner_approved',
                false,
            ) === true
        ) {
            $targets['smi2'] = [
                'label' => 'СМИ2',
                'hint' => 'Партнёрский канал подтверждён и включён',
            ];
        }

        if (
            Config::get('syndication.targets.news_mail.enabled', false) === true
            && Config::get(
                'syndication.targets.news_mail.partner_approved',
                false,
            ) === true
            && Config::get(
                'syndication.targets.news_mail.delivery_contract_confirmed',
                false,
            ) === true
        ) {
            $targets['news_mail'] = [
                'label' => 'Новости Mail',
                'hint' => 'Партнёрский канал подтверждён и включён',
            ];
        }

        return $targets;
    }

    /** @return list<SyndicationEntry> */
    private function buildEntries(?string $target): array
    {
        $base = rtrim((string) Config::get('syndication.site_url', ''), '/');
        if ($base === '') {
            return [];
        }

        $repository = PublicationRepository::fromDatabase();
        $publications = $repository->published(
            $this->siteKey,
            100,
            0,
        );
        $publicationIds = array_map(
            static fn(Publication $publication): int => $publication->id,
            $publications,
        );
        $taxonomy = PublicationTaxonomyService::fromDatabase()
            ->forPublications($publicationIds);
        $overrides = $target === null
            ? []
            : PublicationSyndicationOverrideRepository::fromDatabase()
                ->forTarget($publicationIds, $target);

        $result = [];
        foreach ($publications as $publication) {
            if (
                $publication->syndicationTargets === []
                || $publication->publishedAt === null
            ) {
                continue;
            }

            $categories = [$publication->type->value];
            foreach (
                $taxonomy[$publication->id]['categories'] ?? []
                as $category
            ) {
                $name = trim((string) ($category['name'] ?? ''));
                if ($name !== '') {
                    $categories[] = $name;
                }
            }
            $categories = array_values(array_unique($categories));

            $override = $overrides[$publication->id] ?? null;
            $result[] = new SyndicationEntry(
                id: $publication->publicId,
                url: $base . '/publications/' . rawurlencode($publication->slug),
                title: $override['title']
                    ?? $publication->syndicationTitle
                    ?? $publication->title,
                description: $override['excerpt']
                    ?? $publication->syndicationExcerpt
                    ?? $publication->excerpt,
                contentHtml: $publication->bodyHtml,
                publishedAt: $publication->publishedAt,
                updatedAt: $publication->updatedAt,
                author: $publication->authorName,
                categories: $categories,
                imageUrl: $override['image_url'] ?? null,
                imageMime: $override['image_mime'] ?? null,
                targets: $publication->syndicationTargets,
            );
        }

        return $result;
    }

    private static function hasActiveParentLink(string $siteKey): bool
    {
        if (!class_exists(FederationRepository::class)) {
            return false;
        }

        try {
            foreach (FederationRepository::fromDatabase()->links($siteKey) as $link) {
                if ($link->status !== 'active' || $link->relation !== 'parent') {
                    continue;
                }

                if (
                    $link->remoteProfile === null
                    || in_array(
                        $link->remoteProfile,
                        ['deanery', 'diocese', 'metropolia'],
                        true,
                    )
                ) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
