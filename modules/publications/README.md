# Модуль публикаций

Модуль `publications` отвечает за редакционный цикл публикаций, публичную
выдачу, API и передачу материалов внешним системам.

## Реализовано

- статусы и типы публикаций;
- создание и редактирование через внутренний сервис;
- очистка HTML при сохранении;
- публичные список и карточка материала;
- публичный API;
- partner API с incremental-синхронизацией;
- stable `organization_owner_id` для локальной и федеративной атрибуции;
- категории и теги;
- отложенная публикация;
- включение/выключение комментариев для отдельного материала;
- RSS/Rambler и явные targets распространения;
- organization-scoped RBAC в Admin Shell;
- tombstone-поток для снятых partner-публикаций.

## Публичные маршруты

```
GET /publications
GET /publications/{slug}
```

Публично видны только записи со статусом `published`, заполненным
`published_at` и наступившим временем публикации.

## API

```
GET /api/v1/publications
GET /api/v1/publications/{slug}
GET /api/v1/partner/publications
GET /api/v1/partner/publications/tombstones
```

Partner-маршруты требуют scope `content.read`.

Основной incremental-поток:

```
GET /api/v1/partner/publications?updated_since=2026-09-26T00:00:00Z
```

Tombstone-поток:

```
GET /api/v1/partner/publications/tombstones?updated_since=2026-09-26T00:00:00Z
```

Если ответ tombstone-потока содержит `has_more=true`, следующий запрос должен
передать одновременно `next_updated_since` и `next_after`:

```
?updated_since=<next_updated_since>&after=<next_after>
```

`next_after` содержит stable public ID публикации и нужен только как
tie-breaker для нескольких событий с одинаковой секундой `updated_at`.
Внутренние числовые ID БД наружу не выдаются.

При `withdraw()` ранее опубликованного материала с target `diocese`
tombstone записывается в одной транзакции со сменой статуса. Повторный withdraw
не создаёт дубликат. Материалы без partner target в этот поток не попадают.

## Владение и области доступа

`organization_owner_id` содержит stable public ID локальной organization unit,
которая является каноническим владельцем материала. Новый draft по умолчанию
получает корневую организацию текущего `site_key`, если она существует.

Сервис отклоняет владельца из другого сайта и архивную организацию.
Ограниченная роль видит и изменяет только публикации своего разрешённого
поддерева. Legacy-записи без владельца доступны только глобальной роли.

## Targets распространения

Публикация хранит явный список, например:

```json
["rss", "diocese", "rambler"]
```

Редакционный статус и внешнее распространение намеренно разделены.

## Запись

`PublicationService` поддерживает создание draft, редактирование, публикацию,
снятие с публикации, планирование, изменение targets, владельца и настройки
комментариев.

Публичных HTTP-маршрутов записи нет. Изменяющие действия выполняются через
Admin Shell с аутентификацией, RBAC, CSRF и audit log.

## Контракт темы

Модуль использует:

```
publication.index
publication.show
layout.article
```

Дочерняя тема может переопределить эти шаблоны.

## Следующие задачи

- ревизии/история изменений;
- связь с Media/cover;
- полноценный federation sync worker и remote projections;
- журнал фактического экспорта по внешним targets.
