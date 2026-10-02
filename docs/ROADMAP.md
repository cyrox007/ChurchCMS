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
- [x] Обновления и резервные копии: backup/restore, staging, цифровая подпись, защищённая HTTPS-доставка, code-only apply/rollback и schema-changing apply только через новые обратимые миграции с восстановлением данных.
- [x] Anonymous full-page caching foundation.
- [ ] Load-test release gate (repeatable HTTP scenario and query-plan audit implemented; reference hardware measurements and thresholds pending).

## Phase 1 — Parish MVP

- [x] Unified Admin Shell for non-technical operators.
- [x] Admin navigation/search/notifications foundation.
- [x] Публикации (основной процесс, public/admin/API/синдикация, категории/теги, отложенная публикация, ревизии и связь с Media через usage references реализованы).
- [x] Pages/content tree (schema, domain, public API, organization-scoped Admin Shell and public canonical HTML implemented).
- [x] Navigation/menu (primary menu, ordered Page/system-route/HTTPS items, Admin Shell и автоматическая передача navigation в публичную тему).
- [x] Media library (foundation, organization owner, visibility, federation, безопасный upload/storage, MIME sniffing, SHA-256, Admin upload, технические размеры, derivatives, usage references, public image/document/audio/video blob, HTTP Range streaming и полный gallery vertical slice реализованы; связь Documents с Media-файлом также реализована).
- [ ] People/clergy (основа схемы, доменного слоя и организационных связей назначений реализована; Admin Shell, публичная часть и API остаются).
- [x] Worship schedule (organization-scoped Admin Shell, public/API, federation, ежедневные/еженедельные повторяющиеся правила и идемпотентные праздничные шаблоны реализованы).
- [ ] Events (schema/domain foundation с обязательным organization owner реализован; Admin Shell, публикация, public/API, календарные представления и повторяющиеся правила остаются).
- [x] Galleries (schema/domain foundation, organization owner, ordered image slots, lifecycle, Admin Shell и public/API реализованы).
- [ ] Documents (foundation, organization owner, visibility, federation, связь с Media-файлом, рубрики, organization-scoped Admin Shell и обычный public HTML/API реализованы; остаются версии/замены файла).
- [ ] Search.
- [x] SEO foundation: metadata, canonical, OG/social cards, robots, sitemap, sharing.
- [x] Redirect manager.
- [x] Revisions/history.
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
- [x] Federation pairing wizard и отзыв доверия.
- [x] Organization-scoped RBAC enforcement.
- [x] Organization ownership/references для Publications/Pages/People/Worship/Events/Media/Documents (organization owner/reference foundation реализован для всех перечисленных типов).
- [x] Parent/child aggregation и remote projections/tombstones (Publications, Events, Worship, Documents и Media проходят source → worker → remote projection/tombstone → безопасное агрегированное представление с сохранением источника).
- [x] Federation health/sync dashboard (health/retry/conflict UI, отдельные состояния Publications/Events/Worship, безопасный прогресс и сводные метрики реализованы).

## Cross-cutting — External channels

- [x] Provider-agnostic adapter architecture.
- [x] Bidirectional inbox/outbox persistence foundation.
- [x] Encrypted integration credentials.
- [x] Admin connection wizard.
- [x] Outbound worker and retry/dead-letter processing.
- [x] Inbound review/import workflow.
- [x] Built-in Telegram/VK/MAX adapters.
- [ ] Built-in YouTube/Rutube adapters.
- [x] Adapter SDK documentation for other/future platforms.
