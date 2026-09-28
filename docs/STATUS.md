# ChurchCMS implementation status

Last updated: 2026-09-27

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
- PostgreSQL-backed runtime smoke verifies PDO connectivity, readiness and full migration application;
- MySQL 8.4 smoke verifies DDL migrations without invalid outer transactions, idempotent rerun and installation readiness.

## Организационная структура и федерация

Реализован foundation:

- профили установки расширены от прихода до благочиния, епархии и митрополии;
- профиль задаёт стартовый набор возможностей, но не ограничивает будущие модули;
- `organization_units` хранит локальное дерево церковных организаций/подразделений;
- `organization_site_roots` связывает `site_key` с организацией, которую представляет сайт;
- новая установка получает постоянный публичный `federation.instance_id`;
- installer создаёт корневую organization unit после миграций;
- `organization_federation_links` хранит независимые связи `parent/child/peer`;
- federation link содержит remote instance/root IDs, URL, status, sync cursor, inbound/outbound scopes и encrypted outbound credential;
- организационное подчинение не создаёт federation link и не выдаёт remote admin access;
- `GET /api/v1/federation/meta` публикует безопасную discovery-информацию без credentials и внутренних DB ID;
- Admin Shell содержит раздел «Структура» с отдельным правом `organizations.manage`;
- администратор может создавать благочиния, приходы, монастыри, отделы, комиссии и другие типы, менять родителя/slug/порядок и описание;
- при изменении slug или родителя canonical path всего поддерева пересчитывается транзакционно;
- запрещены циклические перемещения, второй root и помещение активного узла внутрь архивированного;
- вместо hard-delete используется архивирование/восстановление всего поддерева; root сайта архивировать нельзя;
- default theme заявляет совместимость с parish/cathedral/monastery/deanery/diocese/metropolia/education/mixed/organization;
- `AuthorizationService` различает глобальное назначение роли и scope-ограниченное назначение через `admin_role_scopes`;
- `OrganizationAccessService` наследует organization scope вниз по canonical path поддерева, но не вверх и не на соседние ветки;
- Admin Shell показывает и разрешает изменять только доступное поддерево; перенос узла за границу scope и действия над чужой веткой запрещены;
- единственная доступная граница автоматически используется как родитель при создании, а родитель выше границы доступа блокируется от изменения;
- отдельный PostgreSQL smoke проверяет domain operations, organization-scoped RBAC, permissions и рендер Admin Shell;
- `docs/ORGANIZATIONS_AND_FEDERATION.md` фиксирует результаты анализа епархиальных/митрополичьих сайтов и общий domain contract.

Дальше:

- безопасный pairing/revoke workflow;
- выбор publication owner в Admin Shell с organization-scoped RBAC и ownership для остальных контентных модулей;
- incremental aggregation/sync с canonical ownership и tombstones.

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
- отдельный CI-smoke меняет config/uploads после backup, восстанавливает снимок, проверяет SHA-256 конфигурации, удаление лишнего upload и отсутствие временных restore-файлов;
- `php bin/update.php stage` проверяет manifest, точный payload, PHP minimum, безопасные пути и SHA-256 во внешнем staging;
- `php bin/update.php apply <staging-id> --confirm=<staging-id>` повторно проверяет staged package и применяет только code-only обновления;
- до изменения файлов code-only apply создаёт и проверяет обычную резервную копию config/uploads/БД;
- новые application-файлы готовятся рядом с целевыми путями, предыдущие версии сохраняются до успешного healthcheck;
- после применения проверяются SHA-256, встроенный PHP syntax parser, целевая `app.version` и installation healthcheck;
- при ошибке после начала замены application-файлы автоматически откатываются в обратном порядке;
- CI проверяет успешный apply, автоматический rollback на синтаксически повреждённом PHP и отказ schema-changing пакета до изменения установки;
- модуль `operations` регистрирует раздел «Система» в общем Admin Shell с правом `settings.manage`;
- экран «Система» показывает installation healthcheck, список резервных копий и staging-пакетов без раскрытия/ввода серверных путей;
- из Admin Shell можно создать и повторно проверить backup, а готовый code-only пакет — применить после явного подтверждения;
- все операции create/verify/apply записываются в audit log; повреждённый или устаревший staging-пакет в UI не становится применимым;
- недоступность backup/staging каталога не роняет весь системный экран: оператор получает безопасное предупреждение.

Остаётся:

- безопасный rollback старой схемы/данных для schema-changing пакетов: текущий логический backup не является DDL snapshot, поэтому автоматический apply migration-файлов намеренно запрещён;
- доверенный источник/аутентичность релизного пакета до автоматической сетевой загрузки;
- простой интерфейс обновлений/backup в Admin Shell без необходимости знать пути и команды.

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
- production error handler загружается до bootstrap ядра, скрывает внутренние детали и возвращает request ID;
- HTML/API ошибки HTTP 500 получают `Cache-Control: no-store`, а подробность записывается в server log и `storage/logs/error.log`;
- authenticated password rotation доступна из Admin Shell и требует текущий пароль;
- `auth_version` хранится в БД/сессии: после смены пароля все другие старые административные сессии перестают проходить `current()`;
- текущая сессия получает новую auth version, новый session ID и новый CSRF token;
- password rotation записывается в audit log без пароля/секретов;
- protected admin dashboard;
- friendly publication editor;
- comment moderation queue.

Pending:

- roles/users management UI;
- forgotten-password/reset flow с безопасным каналом доставки;
- optional 2FA;
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
- site-scoped reusable categories and tags with normalized many-to-many storage;
- category/tag editing with bounded input, normalization and duplicate removal;
- batch taxonomy loading for public lists, API collections and syndication without N+1 queries;
- categories/tags in public detail and API resources, and categories in publication lists and syndication feeds;
- PostgreSQL lifecycle smoke covers create, deduplication, reuse, update, orphan cleanup, API and syndication projections;
- MySQL runtime smoke covers taxonomy migration, shared-term reuse and batch reads;
- internal create/update/publish/withdraw service;
- public list/detail pages;
- admin list/editor;
- public API;
- partner API with incremental sync;
- RSS/Rambler syndication provider;
- отложенная публикация через статус `scheduled` и UTC-время в `published_at`;
- Admin Shell позволяет выбрать локальную дату/время установки, опубликовать сейчас или отменить планирование;
- bounded CLI worker `php bin/publication-schedule.php` atomically публикует только наступившие материалы;
- параллельные/повторные запуски worker безопасны за счёт conditional UPDATE по статусу;
- автоматическая публикация записывается в audit log и инвалидирует публичный cache;
- CI проверяет due/future/cancelled, запрет прошедшего времени, idempotent rerun и элементы редактора;
- audit/cache invalidation on changes.
- публикация хранит stable public ID локальной organization unit владельца;
- новый draft автоматически получает корневую организацию своего `site_key`, если она создана;
- `PublicationService` запрещает назначать владельца из другого сайта или архивной ветки, а public/partner API возвращает `organization_owner_id`;
- migration безопасно backfill существующих публикаций через `organization_site_roots`, оставляя nullable только legacy-строки без корня.

Next:

- выбор organization owner в Admin Shell с organization-scoped RBAC;
- revisions;
- Media/cover relation.

## Pages / content tree

Implemented:

- отдельный модуль `pages` без внешних runtime-зависимостей;
- PostgreSQL/MySQL схема со стабильными public ID и site-scoped canonical paths;
- optional parent links с `ON DELETE RESTRICT`;
- deterministic sibling order и ограничение длины path, совместимое с MySQL 8 индексами;
- unique `(site_key, path)` invariant и tree/status индексы;
- `Page`, `PageStatus`, `PageRepository` и `PageService`;
- создание draft-страницы с автоматическим slug/path;
- изменение slug пересчитывает canonical path всего поддерева атомарно;
- безопасное перемещение поддерева между родителями с пересчётом descendant paths;
- запрещены self-parent, перемещение внутрь собственного потомка и collision с существующим canonical path;
- при ошибке перемещения транзакция не оставляет частично изменённое дерево;
- body страницы проходит общий HTML sanitizer;
- страница хранит stable public ID локальной organization unit владельца;
- новый draft автоматически получает корневую организацию своего `site_key`, если она создана;
- `PageService` запрещает назначать владельца из другого сайта или архивной ветки;
- migration backfill существующих страниц через `organization_site_roots`, сохраняя nullable для legacy-строк без корня;
- PostgreSQL smoke проверяет create/rename/move/cycle/collision/rollback, общий MySQL runtime проверяет schema compatibility.

Next:

- public/admin/API vertical slice;
- выбор organization owner в Admin Shell и API с organization-scoped RBAC;
- publish/unpublish workflow в пользовательском интерфейсе;
- menu integration после стабилизации публичного Pages contract.

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
- dependency-free HTTP load-runner для cached home/archive/detail и cache-miss detail, ограниченный локальными/приватными целями;
- функциональный CI-smoke HTTP-сценария на disposable PostgreSQL-установке;
- query-plan аудит ключевых publication/comment запросов на полном benchmark-наборе для PostgreSQL 16 и MySQL 8.4;
- CI требует фактического использования ожидаемых list/detail/comment/moderation индексов;
- third-party channel sync isolated from public requests.

Pending before 1.0:

- reference hardware thresholds: заблокировано до выбора/подготовки эталонного Nginx/PHP-FPM + БД стенда и снятия реальных HTTP p50/p95/p99/RPS/error-rate; CI runner не используется как источник release-порогов;

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
- outbound dispatcher worker с atomic claim через статус `processing`;
- retry между запусками worker и dead-letter через `failed` после `social.max_attempts`;
- recovery зависшего `processing` после timeout;
- credential расшифровывается только внутри worker перед вызовом adapter;
- CLI `php bin/channel-dispatch.php` поддерживает bounded batch/max-attempts и не входит в public request path;
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
- inbound review UI;
- Telegram/VK/MAX adapters;
- YouTube/Rutube adapters;
- webhook contract.

## Administration UX

Реализовано:

- общий `layout.admin`, отдельный от публичного layout сайта;
- единый сервис `AdminShell` добавляет пользовательский контекст, активный раздел, поиск и суммарный счётчик задач;
- `AdminNavigationRegistry` позволяет модулям регистрировать раздел, маршрут, требуемое право и порядок без правки общей темы;
- `AdminSearchRegistry` и `AdminSearchService` позволяют модулям подключаться к глобальному поиску с RBAC-фильтрацией и жёсткими лимитами;
- Publications подключён первым поисковым провайдером по title/slug/excerpt;
- `AdminTaskRegistry` и `AdminTaskCenter` собирают pending moderation, редакционную работу и ошибки внешних каналов;
- повторные permission-проверки и агрегат задач кешируются внутри текущего `Request`;
- обзор, Publications, Comments, Search и Tasks используют одну административную оболочку;
- общий header содержит текущий раздел, пользователя, глобальный поиск, задачи и ссылку на публичный сайт;
- desktop использует постоянную боковую навигацию, которую можно полностью свернуть; выбор сохраняется локально в браузере;
- без JavaScript боковая навигация остаётся видимой, а на узких экранах используется компактная горизонтальная панель;
- общий компонент `admin.state` задаёт empty/success/error/loading состояния с `role`, `aria-live` и `aria-busy`;
- активный раздел отмечается через `aria-current`, есть skip-link и единый `:focus-visible`;
- `prefers-reduced-motion` отключает необязательные transitions/animation/smooth scrolling;
- dependency-free `tools/accessibility/admin-audit.php` проверяет landmarks, keyboard/focus правила, target=_blank, aria-контракты и ключевые контрастные пары в CI;
- `docs/ACCESSIBILITY.md` фиксирует автоматическую проверку и ручной pilot-QA сценарий.

Остаётся:

- подключать будущие Media, People, Worship, Events, External Channels, Users/Roles, Themes и Settings к уже существующим контрактам shell/navigation/search/tasks;
- пройти ручной pilot-QA с NVDA/VoiceOver/TalkBack и browser zoom 200%/400% на реальных целевых браузерах.

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
- `docs/ACCESSIBILITY.md`
- `docs/ERROR_HANDLING.md`
- `docs/AUTHENTICATION.md`
- `docs/PUBLICATION_SCHEDULING.md`

Update this file at the end of every substantial implementation increment.
