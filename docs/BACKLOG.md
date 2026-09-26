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
- [ ] Migration CLI validation.
- [x] PHP 8.3 lint/theme validation CI added; runtime DB-backed smoke test pending test database.

## P0 — Installation and operator UX

- [ ] four-step web installer;
- [ ] automatic environment/self-check;
- [ ] automatic site URL/profile detection/defaults;
- [ ] automatic DB creation where permitted;
- [ ] automatic migrations;
- [ ] first-superadmin creation;
- [ ] atomic local configuration write;
- [ ] installer lock after success;
- [ ] friendly recovery messages;
- [ ] installation healthcheck;
- [ ] updater/backup workflow after installer MVP.

## P1 — Core security

- [x] hardened session lifecycle;
- [x] CSRF token service/middleware;
- [x] security headers;
- [x] auth rate limit;
- [x] administrator authentication;
- [x] roles/permissions schema and authorization service;
- [x] scope storage schema; enforcement to be added with scoped modules;
- [ ] audit log;
- password reset/rotation;
- production error handler.

## P1 — Content

- content tree/pages;
- publication categories/tags;
- menus;
- content revisions;
- scheduled publication worker/cron entry;
- canonical URL service;
- SEO metadata;
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

- optional comments module;
- [x] per-publication comments_enabled storage/domain/service switch;
- [ ] editor toggle for comments in publication form;
- premoderation by default;
- moderation queue;
- public comment rate limiting;
- plain-text comments only initially;
- spam/reject/approve workflow;
- close discussion without deleting comments;
- audit moderation actions;
- public API disabled by default for comments.
