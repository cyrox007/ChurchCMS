# Publications module

Initial read-only public slice of the ChurchCMS publication domain.

## Current capabilities

- publication workflow states;
- publication types;
- database migration;
- internal write service;
- safe rich HTML sanitization on draft creation;
- published-list repository;
- public site list/detail pages;
- public API;
- trusted partner incremental sync API;
- syndication provider for RSS/aggregator targets;
- стабильная ссылка на organization owner для локальной и федеративной атрибуции;
- выбор активного organization owner в Admin Shell с ограничением списка и
  действий по scope разрешений `publications.read/create/edit/publish`;
- per-publication `comments_enabled` toggle, disabled by default.

## Public routes

```
GET /publications
GET /publications/{slug}
```

Only records with:

- `status = published`;
- non-null `published_at`;
- `published_at <= now`

are visible.

## API routes

```
GET /api/v1/publications
GET /api/v1/publications/{slug}
GET /api/v1/partner/publications
```

Partner sync requires the `content.read` scope.

`organization_owner_id` содержит stable public ID локальной organization unit,
которая владеет материалом. Новый draft автоматически получает корневую
организацию текущего сайта, если она создана. Сервис отклоняет владельца из
другого `site_key` и архивную организацию. Legacy-строки могут временно
оставаться без владельца до явного назначения.

Ограниченная роль видит в списке, глобальном Admin-поиске, редакционных задачах
и редакторе только публикации и организации своего поддерева. Создание,
редактирование, смена статуса и смена владельца за границами scope завершаются
отказом. Публикации без владельца доступны только роли с глобальным
разрешением.

Incremental sync:

```
GET /api/v1/partner/publications?updated_since=2026-09-26T00:00:00Z
```

## Syndication targets

A publication stores an explicit target list such as:

```json
["rss", "diocese", "rambler"]
```

The local publication workflow and external distribution are intentionally separate.

## Write access

`PublicationService` already supports internal draft creation, publication, withdrawal, syndication-target changes and enabling/disabling comments per publication.

No HTTP write routes are exposed yet.

They must not be added until ChurchCMS has:

- administrator authentication;
- RBAC;
- CSRF protection;
- audit logging.

This prevents an insecure temporary admin API from becoming part of the product.

## Theme contracts

The module currently expects:

```
publication.index
publication.show
layout.article
```

A child theme can override any of them.

## Planned next steps

- categories/tags;
- revisions;
- scheduled publication execution;
- editor/admin UI after Auth/RBAC;
- stable tombstones for partner sync;
- media/cover relation;
- SEO metadata;
- syndication export log and target-specific overrides.
