# ChurchCMS implementation status

Last updated: 2026-09-26

## Current branch

`develop`

## Runtime baseline

Implemented:

- PHP 8.3+ standalone runtime;
- no mandatory Composer/framework/npm dependency;
- native autoloader;
- router/request/response;
- PDO PostgreSQL/MySQL infrastructure;
- migration runner;
- database-free migration CLI validation (`php bin/migrate.php validate`);
- module manifests/runtime providers/capabilities;
- theme manifests and inheritance;
- PHP 8.3 lint + migration validation + theme validation CI;
- PostgreSQL-backed runtime smoke verifies PDO connectivity, readiness and full migration application.

## Installation

Implemented:

- four-step browser installer;
- environment/extension/writeability checks;
- automatic URL defaults;
- site profile selection;
- PostgreSQL/MySQL setup;
- automatic DB creation where credentials allow it;
- automatic migrations;
- first superadmin creation;
- atomic local configuration;
- generated 256-bit secret key;
- completed-installer lock;
- interrupted-install recovery before completion;
- `/health` installation/readiness checks for PHP baseline, completed install state, 256-bit secret key, writable runtime storage, migration validity and database connectivity;
- health endpoint returns HTTP 503 with boolean-only diagnostics when the installation is not ready.

Pending:

- update/backup wizard.

## Обновления и резервные копии

Реализовано:

- каталог резервных копий по умолчанию находится рядом с каталогом сайта, а не внутри публичного web-root;
- `php bin/backup.php create` создаёт снимок `config/local.php`, `storage/uploads` и данных всех таблиц;
- дамп БД формируется через PDO без обязательных `pg_dump`/`mysqldump`;
- значения строк БД сохраняются без потери бинарных данных как base64-or-null;
- манифест содержит версию формата, версию ChurchCMS, список файлов/таблиц, размеры и SHA-256;
- создание считается успешным только после внутренней проверки манифеста и контрольных сумм;
- `php bin/backup.php verify <id>` повторно проверяет готовую копию;
- PostgreSQL CI smoke создаёт тестовую схему миграциями и проверяет резервную копию;
- `php bin/backup.php restore-db <id> --confirm=<id>` восстанавливает данные БД только после повторной проверки manifest/SHA-256 и полного совпадения схемы;
- PostgreSQL restore выполняется транзакционно с порядком внешних ключей и синхронизацией sequence; MySQL сохраняет совместимость и синхронизирует auto_increment;
- CI намеренно меняет данные после создания копии, восстанавливает их и проверяет следующий ID sequence;
- `php bin/backup.php restore-files <id> --confirm=<id>` восстанавливает `config/local.php` и точный снимок `storage/uploads`;
- файловая часть сначала собирается и проверяется во временных кандидатах рядом с целевыми каталогами, затем переключается через `rename`; при ошибке выполняется возврат прежних config/uploads;
- отдельный CI-smoke меняет config/uploads после backup, восстанавливает снимок, проверяет SHA-256 конфигурации, удаление лишнего upload и отсутствие временных restore-файлов.

Остаётся:

- связка «проверенная копия → обновление файлов → миграции → healthcheck → откат при ошибке»;
- простой интерфейс в Admin Shell без необходимости знать пути и команды.

## Security / administration

Implemented:

- hardened session cookies;
- CSRF for authenticated browser mutations;
- stateless HMAC token for anonymous public forms;
- security headers/CSP;
- login throttling;
- admin authentication with password_hash/password_verify;
- automatic password rehash;
- RBAC schema/services;
- scope storage;
- audit event foundation;
- protected admin dashboard;
- friendly publication editor;
- comment moderation queue.

Pending:

- unified Admin Shell replacing the current dashboard-card prototype;
- common admin navigation/header/search/notifications;
- shared admin states and responsive shell;
- roles/users management UI;
- password reset;
- optional 2FA;
- production error handler;
- scoped permission enforcement in future domain modules.

## Publications

Implemented:

- types and workflow statuses;
- DB schema and public UUID;
- site key;
- automatic Russian-friendly slug;
- title/excerpt/body/author;
- comments_enabled per publication;
- explicit syndication targets;
- internal create/update/publish/withdraw service;
- public list/detail pages;
- admin list/editor;
- public API;
- partner API with incremental sync;
- RSS/Rambler syndication provider;
- audit/cache invalidation on changes.

Next:

- categories/tags;
- revisions;
- scheduled publication execution;
- Media/cover relation.

## Comments

Implemented:

- optional module;
- per-publication on/off switch;
- approved public comments;
- premoderated submissions by default;
- plain-text input;
- stateless public form token;
- rate limiting;
- approve/reject/spam moderation;
- audit events.

## SEO / sharing

Implemented:

- SEO module;
- per-publication SEO storage;
- automatic SEO defaults from publication;
- optional SEO title/description/keywords;
- canonical override;
- index/follow controls;
- Open Graph;
- Twitter/X card tags;
- social title/description/image overrides;
- Article/NewsArticle microdata;
- author/published/modified semantic metadata;
- dynamic robots.txt;
- dynamic XML sitemap;
- Sitemap directive;
- configurable share providers;
- Telegram/VK/OK share links;
- native Web Share button;
- copy-link action;
- no third-party share SDK/runtime dependency.

Next:

- Media image dimensions/variants in Article markup;
- video structured metadata when Video/Media module lands;
- organization/site-level SEO settings UI;
- redirect manager.

## Performance

Implemented:

- anonymous pages do not start PHP sessions;
- dependency-free full-page cache;
- version-based O(1) cache invalidation;
- public max-age + stale-while-revalidate;
- versioned immutable theme assets;
- dependency-free generated-output cache for sitemap and syndication feeds;
- sitemap/feed cache shares O(1) publication invalidation with the public page cache;
- query/API result bounds;
- референсный Nginx/PHP-FPM front-controller профиль с запретом прямого доступа к внутренним каталогам;
- Nginx CI проверяет синтаксис, закрытые URL и ACME challenge;
- детерминированный benchmark-набор для изолированного `site_key=benchmark`;
- CLI-измерения первой/глубокой страницы архива, detail lookup и комментариев с mean/p50/p95/p99;
- отдельный CI-smoke performance-инструментов на PostgreSQL;
- third-party channel sync isolated from public requests.

Pending before 1.0:

- HTTP load tests;
- query-plan/index audit;
- reference hardware thresholds;

## External channels / social / video

Architecture implemented:

- open provider IDs: no fixed provider whitelist in storage;
- adapter capability vocabulary;
- generic inbound/outbound DTOs;
- encrypted credential storage foundation;
- connection schema;
- publication outbox schema;
- inbound external-content inbox;
- remote ID/fingerprint deduplication;
- sync cursor/error state;
- polling synchronization service;
- runtime capability.

The intended model is:

```
official ChurchCMS publication
        |
        +--> selected external channels (outbox)
        |
        +--> canonical official URL

external network/video content
        |
        +--> inbox/review
        |
        +--> explicit import/link by operator
```

External content never becomes official automatically by default.

Next:

- connection/admin UI;
- outbound worker;
- inbound review UI;
- Telegram/VK/MAX adapters;
- YouTube/Rutube adapters;
- webhook contract.

## Administration UX

Реализовано:

- общий `layout.admin`, отдельный от публичного layout сайта;
- единый сервис `AdminShell` добавляет пользовательский контекст, доступные разделы, активный раздел и счётчик ожидающих комментариев;
- обзор, Publications и Comments используют одну административную оболочку;
- общий header содержит текущий раздел, пользователя и ссылку на публичный сайт;
- desktop использует постоянную боковую навигацию, на узких экранах она перестраивается в компактную горизонтальную панель;
- активный раздел отмечается через `aria-current`;
- выход из системы вынесен в общую оболочку;
- отдельный CI-smoke проверяет регистрацию layout, использование shell контроллерами и базовую отрисовку.

Остаётся:

- контракт регистрации навигации модулями вместо текущего списка известных разделов;
- сворачиваемая desktop-навигация;
- глобальный поиск;
- общий центр задач/уведомлений;
- единые loading/error/success/empty состояния;
- полный keyboard/focus/accessibility audit;
- подключение будущих Media, People, Worship, Events, External Channels, Users/Roles, Themes, Settings, Backups и System Health.

## Durable project memory

- `docs/PRODUCT_REQUIREMENTS.md`
- `docs/ROADMAP.md`
- `docs/BACKLOG.md`
- `docs/DECISIONS.md`
- `docs/STATUS.md`
- `docs/ARCHITECTURE.md`
- `docs/THEMES.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/API.md`
- `docs/SYNDICATION.md`
- `docs/COMMENTS.md`
- `docs/INSTALLATION.md`
- `docs/BACKUPS.md`
- `docs/UPDATES.md`
- `docs/NGINX.md`

Update this file at the end of every substantial implementation increment.
