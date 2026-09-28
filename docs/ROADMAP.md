# ChurchCMS roadmap

## Phase 0 — Foundations

Status: in progress.

- [x] PHP 8.3+ standalone bootstrap.
- [x] Native autoloader.
- [x] Router/request/response.
- [x] PDO database manager.
- [x] Module manifest/registry/runtime.
- [x] Theme system and inheritance.
- [x] Modern default theme.
- [x] External API foundation.
- [x] API token/scopes/rate-limit foundation.
- [x] Syndication foundation.
- [x] Native migration runner.
- [x] Security/session/CSRF foundation.
- [x] Administrator authentication and RBAC foundation.
- [x] Audit/security event log foundation.
- [x] Friendly four-step web installer.
- [x] Installation healthcheck.
- [ ] Update/backup workflow (verified backup, database/file restore, package staging, code-only apply/rollback and Admin Shell operations screen implemented; trusted package source, unified restore orchestration and schema-changing migration rollback pending).
- [x] Anonymous full-page caching foundation.
- [ ] Load-test release gate (repeatable HTTP scenario and query-plan audit implemented; reference hardware measurements and thresholds pending).

## Phase 1 — Parish MVP

- [x] Unified Admin Shell for non-technical operators.
- [x] Admin navigation/search/notifications foundation.
- [ ] Публикации (основной процесс, public/admin/API/синдикация, категории/теги и отложенная публикация реализованы; остаются ревизии и связь с Media).
- [ ] Pages/content tree (schema, domain, public API and organization-scoped Admin Shell implemented; public HTML pending).
- [ ] Navigation/menu.
- [ ] Media library.
- [ ] People/clergy (schema/domain foundation и organization ownership назначений реализованы; Admin/public/API остаются).
- [ ] Worship schedule.
- [ ] Events.
- [ ] Galleries.
- [ ] Documents.
- [ ] Search.
- [x] SEO foundation: metadata, canonical, OG/social cards, robots, sitemap, sharing.
- [ ] Redirect manager.
- [ ] Revisions/history.
- [x] Optional moderated comments module foundation.

## Phase 2 — Cathedral profile

- [ ] Ministries/departments.
- [ ] Shrines.
- [ ] Saints.
- [ ] Sunday school.
- [ ] Library.
- [ ] Donation integration boundary.
- [ ] Prayer-note/moleben integration boundary.
- [ ] Multiple content streams/home-page aggregation.

## Phase 3 — Education profile

- [ ] Educational programs.
- [ ] Teachers and chairs/departments.
- [ ] Admissions.
- [ ] Educational schedules.
- [ ] Scientific activity.
- [ ] Mandatory educational-organization disclosures.
- [ ] Moodle integration.
- [ ] OJS integration.

## Phase 4 — Legacy migration

- [ ] VPDS database mapping.
- [ ] Content migration.
- [ ] Media migration/checksums.
- [ ] URL/redirect migration.
- [ ] Legacy user migration strategy.
- [ ] Broken-link/media diagnostics.
- [ ] Migration dry-run/reporting.

## Phase 5 — Pilot deployments

- [ ] Vladimir Cathedral experimental deployment.
- [ ] Evaluate large-cathedral pilot.
- [ ] Discuss theological-school pilot with TDS.
- [ ] Collect editorial UX feedback.
- [ ] Harden deployment/update path.


## Cross-cutting — Организационная структура и федерация

- [x] Профили установки: приход/собор/монастырь/благочиние/епархия/митрополия/духовная школа/смешанный.
- [x] Локальное дерево organization units и корневая организация сайта.
- [x] Постоянный `federation.instance_id` для новой установки.
- [x] Federation link foundation: `parent/child/peer`, scopes, encrypted outbound credential, sync state.
- [x] Public federation discovery metadata endpoint.
- [x] Admin UI дерева организаций/подразделений.
- [ ] Federation pairing wizard и отзыв доверия.
- [x] Organization-scoped RBAC enforcement.
- [ ] Organization ownership/references для Publications/Pages/People/Worship/Events/Media/Documents (Pages Admin/API RBAC, foundation Publications и People/назначений реализованы; Publications Admin, Worship/Events/Media/Documents остаются).
- [ ] Parent/child aggregation и remote projections/tombstones.
- [ ] Federation health/sync dashboard.

## Cross-cutting — External channels

- [x] Provider-agnostic adapter architecture.
- [x] Bidirectional inbox/outbox persistence foundation.
- [x] Encrypted integration credentials.
- [ ] Admin connection wizard.
- [x] Outbound worker and retry/dead-letter processing.
- [ ] Inbound review/import workflow.
- [ ] Built-in Telegram/VK/MAX adapters.
- [ ] Built-in YouTube/Rutube adapters.
- [ ] Adapter SDK documentation for other/future platforms.
