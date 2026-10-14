# ChurchCMS roadmap

## Статус версии 1.0

Версия 1.0 **не считается готовой к релизу** до завершения внешних обязательных приёмок, эталонного performance gate и последующего живого тестового развёртывания.

Ранее созданная `release/1.0.0-rc1` остаётся промежуточным снимком. Финальная release branch будет создана заново после завершения работ ниже.

Кодовый функциональный scope 1.0 по состоянию на текущий аудит закрыт. Обязательные незакрытые направления перед тегом `1.0.0`:

- внешняя приёмка Rutube после получения официального партнёрского upload-контракта;
- внешний onboarding и сквозная приёмка СМИ2;
- внешний onboarding и сквозная приёмка News Mail;
- проверка Media archive на реальном S3-compatible хранилище;
- эталонный performance gate;
- живое тестовое развёртывание, ручной проход пользовательских сценариев и исправление найденных дефектов.

## Phase 0 — Фундамент

Статус: базовый технический контур и автоматические AJAX/UX-проверки готовы; остаются performance/live gates.

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
- [x] AJAX/UX release gate: progressive enhancement, POST/FormData, loading/success/error, защита повторной отправки, фильтры/пагинация и браузерный Chromium smoke.
- [ ] Load-test release gate: сценарий, анализ запросов и валидатор порогов реализованы; остаются измерения на утверждённом эталонном сервере и фиксация порогов.
- [ ] Живое тестовое развёртывание после внешних приёмок и performance gate с устранением найденных дефектов.

## Phase 1 — Приходской профиль

- [x] Unified Admin Shell for non-technical operators.
- [x] Admin navigation/search/notifications foundation.
- [x] Публикации: public/admin/API/синдикация, категории/теги, отложенная публикация, ревизии и Media usage references.
- [x] Pages/content tree: schema, domain, public API, organization-scoped Admin Shell и canonical HTML.
- [x] Navigation/menu: primary menu, ordered Page/system-route/HTTPS items и Admin Shell.
- [x] Media library: upload/storage, MIME sniffing, SHA-256, derivatives, usage references, public blob/Range streaming и galleries.
- [x] Media Storage & Optimization: дедупликация, WebP-веб-версии, пакетная оптимизация архива, S3-compatible archive, проверяемое восстановление, dry-run и прогноз заполнения диска.
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

Статус: кодовый объём 1.0 закрыт проверяемым manifest-контрактом; конкретная нестандартная схема VPDS должна предварительно экспортироваться в этот контракт.

- [x] VPDS database/input mapping через версионированный manifest.
- [x] Content migration.
- [x] Media migration/checksums.
- [x] URL/redirect migration.
- [x] Безопасная legacy user migration strategy без переноса старых парольных хэшей.
- [x] Broken-link/media diagnostics.
- [x] Migration dry-run/reporting.
- [x] Идемпотентный повторный запуск и обновление изменившихся материалов.

Импортёр поддерживает проверяемый контракт входных данных и выдаёт подробный отчёт несовместимости, если реальная legacy-схема отличается от ожидаемой.

## Phase 5 — Pilot deployments

Статус: финальная проверка 1.0 после внешних приёмок и performance gate, но до тега `1.0.0`.

- [ ] Vladimir Cathedral experimental deployment.
- [ ] Evaluate large-cathedral pilot.
- [ ] Discuss theological-school pilot with TDS.
- [ ] Collect editorial UX feedback.
- [x] Harden deployment/update path.
- [ ] Проверить S3-compatible Media archive и восстановление старого файла на живом стенде.

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
- [x] Rutube outbound — внутренний provider contract, Media validation, очередь/dead-letter и диагностическое состояние партнёрского доступа.
- [ ] Rutube outbound — официальный партнёрский upload-контракт и реальная сквозная загрузка.
- [x] RSS/Rambler syndication, export log, target-specific overrides и Rambler validator.
- [x] Signed distribution webhooks с retry/dead-letter журналом.
- [x] СМИ2: отдельный target/profile, полнотекстовая feed projection, validator, Admin Shell и onboarding diagnostics.
- [ ] СМИ2: внешний onboarding и сквозная приёмка канала партнёром.
- [x] News Mail: отдельный target/profile, feed projection, validator, Admin Shell и onboarding diagnostics.
- [ ] News Mail: внешний onboarding и сквозная приёмка канала партнёром.
- [x] Adapter SDK documentation for other/future platforms.

## Cross-cutting — AJAX/UX

- [x] общий progressive-enhancement слой Admin Shell для POST-действий;
- [x] multipart/upload без полной перезагрузки;
- [x] информативные loading/success/error состояния и `aria-live`;
- [x] блокировка повторной отправки;
- [x] синхронизация рабочей области, счётчиков и навигации после операции;
- [x] статический аудит POST-форм и явных исключений;
- [x] браузерный Chromium smoke: POST + redirect, multipart/FormData, двойная отправка, фильтры, пагинация, Back/Forward, HTTP 500 и обычные переходы;
- [ ] ручной предрелизный проход сценариев на живом пилоте без полной перезагрузки.
