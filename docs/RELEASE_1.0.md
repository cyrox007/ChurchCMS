# ChurchCMS 1.0 — состояние релиза

## Текущий статус

ChurchCMS 1.0 находится в состоянии **release candidate ready** по коду и эксплуатационному контуру.

Финальный тег `1.0.0` пока не создаётся: обязательный эталонный performance gate должен быть выполнен на зафиксированном production-подобном сервере.

## Что входит в 1.0

### Базовая платформа

- PHP 8.3+ standalone runtime без обязательного Composer/vendor runtime;
- PostgreSQL и MySQL;
- web-installer и installation healthcheck;
- Admin Shell, RBAC, organization scopes, CSRF/session/security headers;
- audit log и production error handling;
- backup/restore;
- подписанные обновления с code-only и schema-changing rollback;
- production-профиль Nginx + отдельный PHP-FPM pool + cron-workers;
- ротация журналов.

### Контент и приходской профиль

- публикации, страницы, меню, поиск;
- Media, галереи и документы;
- духовенство и назначения;
- богослужения и события;
- SEO, sitemap, redirects, revisions;
- модерируемые комментарии.

### Соборный профиль

- служения/отделы;
- святыни;
- святые;
- воскресная школа;
- библиотека;
- donation/prayer-request integration boundaries;
- агрегируемые потоки главной страницы.

### Образовательный профиль

- образовательные программы;
- кафедры и преподаватели;
- приёмная кампания;
- обязательные сведения;
- расписания;
- научная деятельность;
- Moodle/OJS adapters.

### Федерация и внешнее распространение

- organization tree и federation links;
- pairing, scopes, remote projections, tombstones и health dashboard;
- Telegram, VK, MAX, YouTube;
- Rutube inbound sync;
- RSS/Rambler syndication;
- журнал экспортов, target-specific overrides и Rambler validator;
- подписанные distribution webhooks с retry/dead-letter.

## Что намеренно не блокирует 1.0

Эти задачи остаются открытыми, но зависят от внешних контрактов или относятся к следующему этапу:

- legacy VPDS importer — нет подтверждённого формата legacy-экспорта;
- Rutube outbound upload — нет подтверждённого официального upload API;
- СМИ2 — требуется актуальная партнёрская спецификация;
- News Mail — требуется onboarding-контракт;
- пилотные внедрения на реальных площадках и сбор UX-обратной связи.

## Единственный обязательный блокер тега 1.0.0

Необходимо выбрать и зафиксировать эталонный сервер, затем выполнить реальный HTTP load-test и утвердить пороги:

- p50;
- p95;
- p99;
- минимальный RPS;
- максимальная доля ошибок.

GitHub Actions не используется как эталонное железо.

После измерения:

```bash
php bin/release-audit.php \
  --performance-result=/path/http-load.json \
  --performance-thresholds=/path/release-thresholds.json
```

Команда должна завершиться успешно.

## Чек-лист RC

- [x] PHP 8.3 lint/runtime/theme gate;
- [x] PostgreSQL runtime и backup/restore;
- [x] MySQL migrations/readiness;
- [x] web-installer;
- [x] подписанный updater;
- [x] schema-changing rollback;
- [x] production Nginx HTTPS profile;
- [x] PHP-FPM/cron deployment configuration;
- [x] release/deployment documentation;
- [ ] эталонный performance result;
- [ ] утверждённые performance thresholds;
- [ ] финальный `release-audit.php` с performance gate;
- [ ] тег `1.0.0` и публикация релиза.

## Политика release branch

После слияния этого документа в `develop` допускается создать ветку `release/1.0.0-rc1` от проверенного `develop`.

После создания release branch:

- новые функции туда не добавляются;
- допускаются только исправления релизных дефектов и документации;
- каждый fix должен проходить полный RC gate;
- финальный тег создаётся только после performance gate.
