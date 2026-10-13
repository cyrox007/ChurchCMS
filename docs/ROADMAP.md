# ChurchCMS roadmap

## Статус версии 1.0

Функциональный состав версии 1.0 реализован. Кодовый релизный контур готов; перед созданием тега `1.0.0` остаётся обязательный эталонный performance gate на реальном production-подобном стенде.

Не блокируют 1.0 и ведутся отдельно:

- legacy VPDS migration — до получения подтверждённого формата legacy-экспорта;
- исходящая загрузка Rutube — до подтверждённого официального upload API;
- СМИ2 и News Mail — до получения актуальных партнёрских контрактов;
- пилотные внедрения и накопление редакторской обратной связи.

## Phase 0 — Фундамент

Статус: функционально завершён для 1.0; performance gate ожидает эталонный стенд.

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
- [x] Production deployment profile: Nginx HTTPS, отдельный PHP-FPM pool, cron-workers, журналирование и post-deploy проверки.
- [ ] Load-test release gate: сценарий, анализ запросов и валидатор порогов реализованы; остаются измерения на утверждённом эталонном сервере и фиксация порогов.

## Phase 1 — Приходской профиль

- [x] Unified Admin Shell for non-technical operators.
- [x] Admin navigation/search/notifications foundation.
- [x] Публикации: public/admin/API/синдикация, категории/теги, отложенная публикация, ревизии и Media usage references.
- [x] Pages/content tree: schema, domain, public API, organization-scoped Admin Shell и canonical HTML.
- [x] Navigation/menu: primary menu, ordered Page/system-route/HTTPS items и Admin Shell.
- [x] Media library: upload/storage, MIME sniffing, SHA-256, derivatives, usage references, public blob/Range streaming и galleries.
- [x] People/clergy: organization-scoped Admin Shell, public/API, Media portraits и lifecycle назначений.
- [x] Worship schedule: Admin Shell, public/API, federation, recurring rules и праздничные шаблоны.
- [x] Events: Admin Shell, publish/withdraw/cancel, public HTML/API, calendar, federation и recurring rules.
- [x] Galleries.
- [x] Documents: visibility/federation, categories, Admin Shell, public HTML/API, Media-файл и история версий.
- [x] Search.
- [x] SEO foundation: metadata, canonical, OG/social cards, robots, sitemap, sharing.
- [x] Redirect manager.
- [x] Revisions/history.
- [x] Optional moderated comments.

## Phase 2 — Соборный профиль

- [x] Ministries/departments.
- [x] Shrines.
- [x] Saints.
- [x] Sunday school.
- [x] Library.
- [x] Donation integration boundary.
- [x] Prayer-note/moleben integration boundary.
- [x] Multiple content streams/home-page aggregation.

## Phase 3 — Образовательный профиль

- [x] Educational programs.
- [x] Teachers and chairs/departments.
- [x] Admissions.
- [x] Educational schedules.
- [x] Scientific activity.
- [x] Mandatory educational-organization disclosures.
- [x] Moodle integration.
- [x] OJS integration.

## Phase 4 — Legacy migration

Статус: после 1.0, заблокировано до получения подтверждённого формата legacy-экспорта VPDS.

- [ ] VPDS database mapping.
- [ ] Content migration.
- [ ] Media migration/checksums.
- [ ] URL/redirect migration.
- [ ] Legacy user migration strategy.
- [ ] Broken-link/media diagnostics.
- [ ] Migration dry-run/reporting.

## Phase 5 — Pilot deployments

Статус: отдельный пост-релизный цикл проверки на реальных площадках.

- [ ] Vladimir Cathedral experimental deployment.
- [ ] Evaluate large-cathedral pilot.
- [ ] Discuss theological-school pilot with TDS.
- [ ] Collect editorial UX feedback.
- [x] Harden deployment/update path.

## Cross-cutting — Организационная структура и федерация

- [x] Профили установки: приход/собор/монастырь/благочиние/епархия/митрополия/духовная школа/смешанный.
- [x] Локальное дерево organization units и корневая организация сайта.
- [x] Постоянный `federation.instance_id` для новой установки.
- [x] Federation link foundation: `parent/child/peer`, scopes, encrypted outbound credential, sync state.
- [x] Public federation discovery metadata endpoint.
- [x] Admin UI дерева организаций/подразделений.
- [x] Federation pairing wizard и отзыв доверия.
- [x] Organization-scoped RBAC enforcement.
- [x] Organization ownership/references для Publications/Pages/People/Worship/Events/Media/Documents.
- [x] Parent/child aggregation и remote projections/tombstones.
- [x] Federation health/sync dashboard.

## Cross-cutting — Внешние каналы и распространение

- [x] Provider-agnostic adapter architecture.
- [x] Bidirectional inbox/outbox persistence foundation.
- [x] Encrypted integration credentials.
- [x] Admin connection wizard.
- [x] Outbound worker and retry/dead-letter processing.
- [x] Inbound review/import workflow.
- [x] Built-in Telegram/VK/MAX adapters.
- [x] Built-in YouTube adapter: inbound Data API sync + OAuth resumable video upload через Media pipeline.
- [x] Rutube inbound sync.
- [ ] Rutube outbound upload — внешний блокер: нет подтверждённого официального upload API.
- [x] RSS/Rambler syndication, export log, target-specific overrides и Rambler validator.
- [x] Signed distribution webhooks с retry/dead-letter журналом.
- [ ] СМИ2 — внешний блокер: требуется актуальная партнёрская спецификация.
- [ ] News Mail — внешний блокер: требуется onboarding-контракт.
- [x] Adapter SDK documentation for other/future platforms.
