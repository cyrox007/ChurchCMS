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


## HTTP load-сценарий

Dependency-free runner находится в `tools/performance/http-load.php`. Он использует стандартные PHP streams и не добавляет зависимость в runtime ChurchCMS.

Сценарий распределяет запросы между:

- кешированной главной страницей;
- кешированным архивом публикаций;
- кешированной страницей материала;
- намеренно некешируемой страницей материала с query string.

Пример для локального Nginx/PHP-FPM стенда:

```bash
php tools/performance/http-load.php \
  --base=http://127.0.0.1:8080 \
  --requests=10000 \
  --concurrency=50 \
  --warmup=100 \
  --slug=benchmark-000001
```

Runner намеренно разрешает только localhost, loopback и приватные IPv4-адреса, чтобы инструмент релизной проверки нельзя было случайно направить на чужой публичный сайт.

Для HTTP-сценария нужен набор в `site_key=default`, потому что текущие публичные контроллеры обслуживают default-site. Создавать его разрешается только на disposable стенде с усиленным подтверждением:

```bash
php tools/performance/seed.php \
  --site=default \
  --publications=10000 \
  --comments=30000 \
  --reset \
  --confirm=benchmark-fixture-default
```

В CI механизм проверяется на PHP built-in server с небольшой нагрузкой только как функциональный smoke. Производительные цифры built-in server не используются для release thresholds: реальные p50/p95/p99/RPS фиксируются позже на выбранном Nginx/PHP-FPM эталонном стенде.
