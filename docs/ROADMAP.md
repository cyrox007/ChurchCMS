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
- [ ] Update/backup workflow (verified backup, database/file restore and package staging implemented; apply/rollback orchestration pending).
- [x] Anonymous full-page caching foundation.
- [ ] Load-test release gate (repeatable HTTP scenario and query-plan audit implemented; reference hardware measurements and thresholds pending).

## Phase 1 — Parish MVP

- [ ] Unified Admin Shell for non-technical operators (shared shell/header/responsive layout and unified UI states implemented; remaining interaction/accessibility work pending).
- [ ] Admin navigation/search/notifications foundation (module navigation contract and persistent/collapsible navigation implemented; search and task center pending).
- [ ] Publications.
- [ ] Pages/content tree.
- [ ] Navigation/menu.
- [ ] Media library.
- [ ] People/clergy.
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


## Cross-cutting — External channels

- [x] Provider-agnostic adapter architecture.
- [x] Bidirectional inbox/outbox persistence foundation.
- [x] Encrypted integration credentials.
- [ ] Admin connection wizard.
- [ ] Outbound worker and retry/dead-letter processing.
- [ ] Inbound review/import workflow.
- [ ] Built-in Telegram/VK/MAX adapters.
- [ ] Built-in YouTube/Rutube adapters.
- [ ] Adapter SDK documentation for other/future platforms.
