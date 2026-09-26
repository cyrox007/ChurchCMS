# ChurchCMS backlog

Priority is ordered within each section.

## P0 — Current

- Publications database schema.
- Publications repository/service.
- Publication public pages.
- Publication public API.
- Publication partner API/scopes.
- Publication syndication provider.
- Publication editorial distribution flags.
- Migration CLI validation.
- PHP syntax/smoke checks.

## P1 — Core security

- hardened session lifecycle;
- CSRF token service/middleware;
- security headers;
- auth rate limit;
- administrator authentication;
- roles/permissions;
- section-scoped permissions;
- audit log;
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
