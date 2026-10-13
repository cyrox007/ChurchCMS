# ChurchCMS roadmap

## Статус версии 1.0

Версия 1.0 **не считается функционально завершённой** до закрытия исходного объёма работ, AJAX/UX-аудита административных сценариев, эталонного performance gate и последующего живого тестового развёртывания.

Ранее созданная `release/1.0.0-rc1` остаётся промежуточным снимком. Финальная release branch будет создана заново после завершения работ ниже.

Обязательные незакрытые направления 1.0:

- legacy VPDS migration;
- исходящий контур Rutube;
- СМИ2;
- News Mail;
- современный AJAX/UX всех действий внутри открытой административной страницы;
- эталонный performance gate;
- живое тестовое развёртывание и исправление найденных дефектов.

## Phase 0 — Фундамент

Статус: базовый технический контур готов; финальные UX/performance/live gates ещё не пройдены.

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
- [ ] AJAX/UX release gate: действия внутри текущей административной страницы без полной перезагрузки, состояния выполнения/успеха/ошибки, защита от повторной отправки и аудит исключений.
- [ ] Load-test release gate: сценарий, анализ запросов и валидатор порогов реализованы; остаются измерения на утверждённом эталонном сервере и фиксация порогов.
- [ ] Живое тестовое развёртывание после закрытия функционального scope и устранение найденных дефектов.

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

Статус: обязательный объём 1.0.

- [ ] VPDS database mapping.
- [ ] Content migration.
- [ ] Media migration/checksums.
- [ ] URL/redirect migration.
- [ ] Legacy user migration strategy.
- [ ] Broken-link/media diagnostics.
- [ ] Migration dry-run/reporting.

Импортёр должен поддерживать проверяемый контракт входных данных и выдавать подробный отчёт несовместимости, если реальная legacy-схема отличается от ожидаемой.

## Phase 5 — Pilot deployments

Статус: финальная проверка 1.0 после завершения кода, AJAX/UX и performance gate, но до тега `1.0.0`.

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
- [ ] Rutube outbound: законченный provider contract, Media validation, настройки и диагностическое состояние для партнёрского доступа; реальный машинный upload включается только через подтверждённый официальный API.
- [x] RSS/Rambler syndication, export log, target-specific overrides и Rambler validator.
- [x] Signed distribution webhooks с retry/dead-letter журналом.
- [ ] СМИ2: отдельный target/profile, feed projection, validator, Admin Shell и onboarding diagnostics.
- [ ] News Mail: отдельный target/profile, feed projection, validator, Admin Shell и onboarding diagnostics.
- [x] Adapter SDK documentation for other/future platforms.

## Cross-cutting — AJAX/UX

- [ ] общий progressive-enhancement слой Admin Shell для POST-действий;
- [ ] multipart/upload без полной перезагрузки;
- [ ] информативные loading/success/error состояния и `aria-live`;
- [ ] блокировка повторной отправки;
- [ ] синхронизация рабочей области, счётчиков и навигации после операции;
- [ ] статический аудит POST-форм и явных исключений;
- [ ] браузерный smoke по всем административным модулям;
- [ ] ручной предрелизный проход сценариев без полной перезагрузки.
