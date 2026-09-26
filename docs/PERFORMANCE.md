# Performance and scale

ChurchCMS is designed so public traffic can scale independently from editorial/background work.

The requirement is not a guessed "RPS number". Before 1.0, capacity is measured on documented reference hardware with a production-like dataset.

## Request-path rules

A public page request must not:

- call VK/Telegram/MAX/YouTube/Rutube or another remote API;
- run imports;
- upload/transcode video;
- dispatch syndication synchronously;
- start an admin/user PHP session for anonymous visitors;
- scan unbounded database result sets.

## Public page cache

Anonymous GET pages use a local full-page cache.

Properties:

- cache only anonymous GET;
- skip admin/API/feeds/health/assets/robots/sitemap;
- skip Authorization requests;
- skip requests carrying the admin session cookie;
- skip query-string variants;
- cache version included in the cache key;
- one version bump invalidates all old entries without deleting/scanning cache files;
- page responses expose public max-age and stale-while-revalidate.

The same HTTP headers make Nginx/CDN caching possible later.

## Cache invalidation

The public cache version changes when:

- a publication is updated;
- a publication is published/withdrawn;
- syndication/comment visibility settings change;
- SEO/social metadata changes;
- an approved comment becomes publicly visible.

## Assets

Theme asset URLs include theme version and are served with:

```
Cache-Control: public, max-age=31536000, immutable
```

A theme version bump changes the asset URL.

## Database

Public queries must:

- use prepared statements;
- use explicit limits;
- use indexes matching publication status/date/site filters;
- avoid N+1 patterns as domains expand.

A query-plan audit with production-scale fixtures is required before 1.0.

## Background workloads

These run outside public web requests:

- external channel publication;
- inbound channel synchronization;
- RSS/partner export materialization if needed;
- media derivatives;
- video upload/transcode;
- imports;
- scheduled publications;
- search indexing.

## Release load test

Before 1.0 create a repeatable load scenario covering:

- cached home page;
- cached publication detail;
- publication archive;
- cache-miss publication detail;
- public API;
- comment submission at controlled rates.

Record:

- hardware;
- PHP-FPM workers;
- DB settings;
- cache state;
- requests/sec;
- p50/p95/p99 latency;
- error rate;
- DB connection/query load;
- memory/CPU.

Release thresholds are set from pilot requirements and reference hardware.


## Репрезентативный набор данных

Для повторяемых измерений используется изолированный `site_key=benchmark`. Генератор не работает с обычными site_key и требует явного подтверждения, поэтому его нельзя случайно применить к рабочему сайту.

Рекомендуемый базовый профиль текущего MVP:

- 10 000 опубликованных материалов;
- 30 000 комментариев, из них 80% одобрены;
- длинный текст материала и заполненные excerpt/author;
- часть публикаций имеет цель syndication;
- даты распределены детерминированно для стабильной сортировки.

Создание набора:

```bash
php tools/performance/seed.php \
  --publications=10000 \
  --comments=30000 \
  --confirm=benchmark-fixture
```

Повторное создание поверх существующего benchmark-набора требует отдельного `--reset`.

## CLI benchmark базы данных

```bash
php tools/performance/benchmark.php --iterations=200
```

Команда измеряет одни и те же операции репозитория:

- первая страница архива публикаций;
- глубокая страница архива примерно на 75% набора;
- поиск опубликованного материала по slug;
- чтение одобренных комментариев материала.

Для каждой операции выводятся mean, p50, p95, p99 и максимум в миллисекундах вместе с версией PHP и драйвером БД.

Это микробенчмарк БД/репозитория, а не замена HTTP load test. Числовые release-пороги намеренно не зафиксированы до выбора эталонного оборудования и запуска HTTP-сценариев.


## Query-plan аудит

После создания полного benchmark-набора (10 000 публикаций / 30 000 комментариев) выполняется:

```bash
php tools/performance/query-plan.php
```

Инструмент обновляет статистику планировщика через `ANALYZE`, затем получает реальные `EXPLAIN` планы для критических операций:

- первой страницы публичного архива;
- глубокой страницы архива;
- detail lookup по `site_key + slug`;
- одобренных комментариев публикации;
- очереди модерации.

Проверяется не только наличие индексов в схеме, но и их фактическое использование. Ожидаются `publications_public_list_idx`, `publications_site_slug_unique`, `publication_comments_public_idx` и `publication_comments_queue_idx`.

CI выполняет проверку на PostgreSQL 16 и MySQL 8.4 с полным репрезентативным набором. Если критический запрос перестаёт использовать ожидаемый индекс, workflow завершается ошибкой.
