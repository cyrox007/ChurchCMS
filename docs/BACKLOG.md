# ChurchCMS backlog

Priority is ordered within each section.

## P0 — Current

- [x] Publications database schema.
- [x] Publications repository/service.
- [x] Publication public pages (read-only slice).
- [x] Publication public API.
- [x] Publication partner API/scopes (incremental updated_since sync).
- [x] Publication syndication provider.
- [x] Publication distribution target storage/service; admin UI pending Auth/RBAC.
- [x] Migration CLI validation.
- [x] PHP 8.3 lint/theme validation CI and PostgreSQL-backed runtime smoke test.

## P0 — Installation and operator UX

- [x] four-step web installer;
- [x] automatic environment/self-check;
- [x] automatic site URL/profile detection/defaults;
- [x] automatic DB creation where permitted;
- [x] automatic migrations;
- [x] first-superadmin creation;
- [x] atomic local configuration write;
- [x] installer lock after success;
- [x] friendly recovery messages;
- [x] installation healthcheck;
- [ ] updater/backup workflow after installer MVP;
  - [x] безопасный каталог резервных копий вне публичного корня;
  - [x] манифест и SHA-256 проверка целостности;
  - [x] CLI-снимок config/local.php, uploads и логического дампа PostgreSQL/MySQL через PDO;
  - [x] PostgreSQL smoke-проверка создания/проверки резервной копии в CI;
  - [ ] восстановление данных и проверка восстановления;
    - [x] транзакционное восстановление БД из проверенного снимка с проверкой схемы, строк и PostgreSQL sequence / MySQL auto_increment;
    - [ ] безопасное восстановление config/local.php и storage/uploads с откатом файловой части;
  - [x] staging/проверка пакета обновления;
  - [ ] доверенный источник/аутентичность релизного пакета до автоматической сетевой загрузки;
  - [ ] обязательная резервная копия перед миграциями/заменой файлов и сценарий отката;
  - [ ] мастер обновления/резервного копирования в Admin Shell;
- [ ] replace dashboard-card prototype with unified Admin Shell;
- [ ] persistent/collapsible admin navigation;
- [ ] shared admin header with site context and current user;
- [ ] global admin search foundation;
- [ ] task/notification center for pending moderation, failed sync and editorial work;
- [ ] responsive admin shell for tablet/mobile;
- [ ] module navigation registration contract;
- [ ] unified empty/error/success/loading states across admin screens;
- [ ] keyboard/focus/accessibility audit for administration.

## P1 — Core security

- [x] hardened session lifecycle;
- [x] CSRF token service/middleware;
- [x] security headers;
- [x] auth rate limit;
- [x] administrator authentication;
- [x] roles/permissions schema and authorization service;
- [x] scope storage schema; enforcement to be added with scoped modules;
- [x] audit log foundation and publication/comment events;
- password reset/rotation;
- production error handler.

## P1 — Content

- content tree/pages;
- publication categories/tags;
- menus;
- content revisions;
- scheduled publication worker/cron entry;
- [x] canonical URL/SEO metadata foundation;
- [x] Open Graph and Twitter/X social metadata;
- [x] XML sitemap and robots endpoints;
- [x] lightweight social share buttons;
- structured image/video SEO when Media is implemented;
- redirect manager.

## P1 — Media

- safe uploads;
- MIME sniffing;
- checksums;
- image metadata;
- usage references;
- derivatives;
- galleries;
- document catalog.

## P2 — Parish/Cathedral

- people/clergy;
- worship;
- events;
- ministries;
- shrines;
- saints;
- Sunday school;
- library;
- donations adapter;
- prayer request adapter.

## P2 — External distribution

- diocesan incremental sync endpoints;
- tombstones/withdrawals;
- syndication export log;
- target-specific title/excerpt/image overrides;
- Rambler validator;
- СМИ2 adapter after current partner spec is obtained;
- News Mail adapter after onboarding contract is obtained;
- optional webhooks with signed payloads/retry log.

## P2 — Education

- programs;
- teachers/chairs;
- admissions;
- educational disclosures;
- schedules;
- science;
- Moodle/OJS adapters.

## P3 — Migration and operations

- legacy VPDS importer;
- installer;
- updater;
- backup/restore;
- health/readiness checks;
- deployment docs;
- observability/log rotation.


## P2 — Comments

- [x] optional comments module foundation;
- [x] per-publication comments_enabled storage/domain/service switch;
- [x] editor toggle for comments in publication form;
- [x] premoderation by default;
- [x] moderation queue;
- [x] public comment rate limiting;
- [x] plain-text comments only initially;
- [x] spam/reject/approve workflow;
- close discussion without deleting comments;
- [x] audit moderation actions;
- public API disabled by default for comments.


## P0 — Performance and scale

- [x] stateless anonymous public pages;
- [x] dependency-free anonymous full-page cache;
- [x] cache invalidation for publications, SEO and visible comments;
- [x] public Cache-Control / stale-while-revalidate headers;
- [x] immutable versioned theme assets;
- [x] cache sitemap/feed output or materialize periodically;
- [x] database-backed runtime smoke test;
- [x] representative dataset benchmark;
- [x] HTTP load-test scenario for publication list/detail and home page;
- [ ] define reference hardware and release thresholds;
- [x] verify reverse-proxy Nginx configuration;
- [ ] query-plan/index audit with production-scale fixture data.

## P1 — External Channels

- [x] open-ended provider ID model;
- [x] adapter capability contract for social/video channels;
- [x] encrypted credential vault foundation;
- [x] outbound connection/outbox schema;
- [x] inbound external content inbox schema;
- [x] sync cursor/state model;
- [x] polling sync service foundation;
- [x] channel runtime/capability registration;
- [ ] outbound dispatcher worker;
- [ ] inbox review/admin UI;
- [ ] connection setup/testing UI;
- [ ] per-publication external channel selector;
- [ ] per-channel custom post text;
- [ ] Telegram adapter;
- [ ] VK adapter;
- [ ] MAX adapter;
- [ ] YouTube adapter;
- [ ] Rutube adapter;
- [ ] webhook receiver contract/signature validation;
- [ ] loop prevention tests;
- [ ] retry/dead-letter UI.
