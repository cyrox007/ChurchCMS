# ChurchCMS implementation status

Last updated: 2026-09-29

## Current branch

`develop`

## Runtime baseline

Implemented:

- PHP 8.3+ standalone runtime;
- no mandatory Composer/framework/npm dependency;
- native autoloader;
- router/request/response;
- PDO PostgreSQL/MySQL infrastructure;
- migration runner;
- database-free migration CLI validation (`php bin/migrate.php validate`);
- module manifests/runtime providers/capabilities;
- theme manifests and inheritance;
- PHP 8.3 lint + migration validation + theme validation CI;
- PostgreSQL-backed runtime smoke verifies PDO connectivity, readiness and full migration application;
- MySQL 8.4 smoke verifies DDL migrations without invalid outer transactions, idempotent rerun and installation readiness.

## Организационная структура и федерация

Реализован foundation:

- профили установки расширены от прихода до благочиния, епархии и митрополии;
- профиль задаёт стартовый набор возможностей, но не ограничивает будущие модули;
- `organization_units` хранит локальное дерево церковных организаций/подразделений;
- `organization_site_roots` связывает `site_key` с организацией, которую представляет сайт;
- новая установка получает постоянный публичный `federation.instance_id`;
- installer создаёт корневую organization unit после миграций;
- `organization_federation_links` хранит независимые связи `parent/child/peer`;
- federation link содержит remote instance/root IDs, URL, status, sync cursor, inbound/outbound scopes и encrypted outbound credential;
- организационное подчинение не создаёт federation link и не выдаёт remote admin access;
- `GET /api/v1/federation/meta` публикует безопасную discovery-информацию без credentials и внутренних DB ID;
- Admin Shell содержит отдельный раздел «Связи» с правом `settings.manage` и двухшаговым federation pairing;
- мастер сначала выполняет discovery и показывает identity удалённого узла, а непосредственно перед сохранением повторяет discovery и не доверяет hidden-полям формы;
- публичный discovery разрешён только по HTTPS; частные/локальные адреса по умолчанию запрещены и включаются явной настройкой `federation.allow_private_discovery` для закрытой сети;
- DNS-адрес проверяется до запроса, смешанные public/private ответы и link-local/служебные адреса отклоняются;
- revoke переводит связь в `revoked`, очищает encrypted credential и sync cursor, но сохраняет запись для аудита и безопасного переподключения;
- повторное pairing ранее отозванного instance ID переиспользует существующий federation link вместо создания дубля;
- Admin Shell позволяет вручную проверить состояние неотозванной federation-связи без передачи сохранённого credential в discovery;
- совпадение постоянных remote instance/organization ID переводит связь в `active`, сетевой/протокольный сбой — в `error`, а смена identity — в `conflict`;
- `last_seen_at` и безопасный `last_error` показываются оператору; повторная проверка служит штатным retry и не меняет scopes, credential или sync cursor;
- health-переходы записываются в audit log, а PostgreSQL smoke проверяет success/error/conflict/retry и сохранность credential;
- принимающая сторона federation sync хранит удалённые объекты отдельно в `federation_remote_projections`, не смешивая их с локальными каноническими сущностями;
- remote projection идентифицируется по federation link, типу объекта и stable public ID источника; сохраняются canonical URL, remote owner, удалённое время изменения и ограниченный JSON payload;
- upsert принимается только от активной доверенной связи, устаревшие события игнорируются, tombstone с тем же временем имеет приоритет над active-состоянием, а более новая повторная публикация может безопасно восстановить projection;
- tombstone без предварительного full sync сохраняется как deleted projection, поэтому удаление не теряется при частичной синхронизации;
- PostgreSQL и MySQL smoke проверяют lifecycle remote projection, порядок событий и запрет записи после revoke;
- partner-потоки публикаций и tombstone используют составной курсор `updated_at + public_id`, поэтому записи с одинаковым временем изменения не теряются на границе страниц;
- `FederationPublicationSyncWorker` получает upsert и tombstone отдельными потоками, применяет их через существующий сервис remote projections и хранит курсор каждого потока отдельно в `sync_cursor`;
- после успешно применённой страницы публикаций её курсор сохраняется до запроса tombstone: сбой второго потока не заставляет повторно сканировать уже подтверждённую страницу, а неприменённый поток остаётся на прежнем курсоре;
- `last_sync_at` и `last_sync_error` отделены от discovery-health состояния: сетевой сбой синхронизации не переводит доверенную связь из `active`;
- `php bin/federation-sync.php` выполняет ограниченный проход по активным связям и подходит для cron; один сбой не останавливает обработку остальных связей;
- Admin Shell показывает отдельное состояние каждого применимого sync worker, время последнего успеха и безопасную ошибку;
- `FederationSyncDashboardService` считает активные связи и worker в состояниях successful/failed/partial/not-started, учитывает scopes и не передаёт содержимое sync cursor в шаблон;
- экран «Связи» показывает сводные метрики и подробный прогресс Publications/Events/Worship/Documents; PostgreSQL/MySQL smoke проверяет расчёт, scope-фильтрацию и отсутствие cursor в read-model;
- PostgreSQL/MySQL smoke проверяет частичный сетевой сбой, сохранение безопасного курсора, повторный запуск и tie-breaker публикаций с одинаковым `updated_at`;
- `FederatedPublicationFeedService` объединяет локальные опубликованные материалы с active remote projections только от доверенных `child`-связей, у которых разрешён входящий `content.read` или `publications.read`;
- публичный `GET /api/v1/publications/aggregated` отдаёт ограниченную общую ленту, где каждый элемент содержит явный блок `source` с видом источника, исходным `instance_id`, organization owner, названием и canonical URL;
- remote projection в агрегированную ленту нормализуется по белому списку полей списка; сохранённый `body_html` и произвольные поля удалённого payload наружу не копируются;
- remote projection без корректного `published_at` или с временем публикации в будущем не показывается;
- tombstone, revoked/error связь, `parent`/`peer` и child-связь без входящего publication scope не попадают в агрегированную ленту;
- PostgreSQL/MySQL smoke проверяет локальный + child материал, сохранность canonical source и исключение tombstone/peer/parent/no-scope источников; HTTP smoke отдельно проверяет публичный контракт endpoint;
- remote Publications подключены к общему RSS только для target `rss`: GUID включает `instance_id + public_id`, а стандартный RSS `source` сохраняет имя и canonical URL исходного узла;
- главная страница получает последние Publications через capability модуля, поэтому app/core не зависит напрямую от реализации Publications;
- публичный блок использует тот же безопасный агрегированный projection: remote `body_html` не передаётся шаблону, источник внешнего материала показывается явно, ссылка ведёт на canonical URL исходного ChurchCMS;
- если Publications недоступен или чтение агрегированной ленты завершилось ошибкой, главная продолжает работать без блока материалов, а подробность остаётся в серверном журнале;
- Admin Shell содержит раздел «Структура» с отдельным правом `organizations.manage`;
- администратор может создавать благочиния, приходы, монастыри, отделы, комиссии и другие типы, менять родителя/slug/порядок и описание;
- при изменении slug или родителя canonical path всего поддерева пересчитывается транзакционно;
- запрещены циклические перемещения, второй root и помещение активного узла внутрь архивированного;
- вместо hard-delete используется архивирование/восстановление всего поддерева; root сайта архивировать нельзя;
- default theme заявляет совместимость с parish/cathedral/monastery/deanery/diocese/metropolia/education/mixed/organization;
- `AuthorizationService` различает глобальное назначение роли и scope-ограниченное назначение через `admin_role_scopes`;
- `OrganizationAccessService` наследует organization scope вниз по canonical path поддерева, но не вверх и не на соседние ветки;
- Admin Shell показывает и разрешает изменять только доступное поддерево; перенос узла за границу scope и действия над чужой веткой запрещены;
- единственная доступная граница автоматически используется как родитель при создании, а родитель выше границы доступа блокируется от изменения;
- отдельный PostgreSQL smoke проверяет domain operations, organization-scoped RBAC, permissions и рендер Admin Shell;
- модуль `people` хранит карточку человека с обязательным каноническим organization owner и отдельные назначения в конкретные organization units;
- `PeopleService` по умолчанию назначает владельцем site root, запрещает cross-site владельцев/назначения и валидирует период назначения;
- составные внешние ключи `(site_key, public_id)` дополнительно запрещают cross-site связи на уровне PostgreSQL/MySQL;
- модуль `worship` хранит расписание с обязательным `owner_organization_public_id`, временем начала/окончания, типом, местом и статусом;
- `WorshipScheduleService` назначает site root владельцем по умолчанию, нормализует время в UTC, запрещает окончание раньше начала и cross-site/архивного владельца;
- PostgreSQL/MySQL проверяют составной внешний ключ `(site_key, owner_organization_public_id)` и smoke подтверждает создание, смену владельца, отмену и запрет чужого `site_key`;
- модуль `events` хранит события с обязательным `owner_organization_public_id`, временем начала/окончания, all-day признаком, местом, кратким описанием и статусом;
- `EventService` назначает site root владельцем по умолчанию, нормализует время в UTC, запрещает обратный временной интервал и cross-site/архивного владельца;
- PostgreSQL/MySQL smoke проверяет Events foundation, включая составной FK, смену владельца, отмену и запрет чужого `site_key`;
- модуль `media` хранит только безопасный foundation метаданных: stable public ID, organization owner, тип, исходное имя, MIME, размер, SHA-256, title/alt и статус;
- `MediaService` назначает site root владельцем по умолчанию, валидирует MIME/SHA-256/размер и запрещает cross-site/архивного владельца;
- Media foundation намеренно не хранит произвольный filesystem path и не объявляет upload реализованным; PostgreSQL/MySQL smoke проверяет metadata lifecycle, owner FK и запрет чужого `site_key`;
- модуль `documents` хранит карточку документа с обязательным `owner_organization_public_id`, типом, номером, датой и кратким описанием;
- `DocumentService` назначает site root владельцем по умолчанию, валидирует дату и запрещает cross-site/архивного владельца; PostgreSQL/MySQL smoke проверяет owner FK, смену владельца и архивирование;
- Documents foundation намеренно не хранит filesystem path и не считает файл прикреплённым до отдельной связи с проверенным Media asset;
- federation sync вынесен в общий `FederationSyncWorker` contract и `FederationSyncCoordinator`;
- существующий Publications worker реализует общий contract, а `bin/federation-sync.php` запускает coordinator вместо жёсткого вызова одного типа данных;
- coordinator суммирует результаты worker'ов и не останавливает остальные типы, если один worker завершился исключением; отдельный smoke проверяет несколько worker, агрегацию статистики, изоляцию ошибки и запрет повторяющихся worker ID;
- курсор, время последнего успеха и ошибка federation sync теперь хранятся отдельно для каждого `(federation_link_id, worker_id)`, поэтому второй worker не может перетереть состояние Publications;
- migration переносит существующий legacy Publications cursor в worker `publications`; этот worker временно зеркалит состояние обратно в поля link для совместимости текущего Admin Shell;
- optimistic locking выполняется внутри конкретного worker state, а revoke/reconnect удаляют все состояния worker этой связи; PostgreSQL/MySQL smoke проверяет независимость курсоров/ошибок Publications и Events;
- Events получил source-side federation contract: publish/withdraw/cancel lifecycle, incremental partner feed по `updated_at + public_id` и отдельный tombstone поток;
- partner projection Events реализует `ApiResource` и не отдаёт сырой `description_html`; наружу идут только безопасные поля списка и stable organization owner;
- снятие/отмена только ранее опубликованного события создаёт tombstone, а повторная публикация очищает устаревший tombstone; PostgreSQL/MySQL smoke проверяет весь этот lifecycle;
- `FederationEventSyncWorker` получает Events и tombstones отдельными bounded-потоками, применяет их как `event` remote projections и подключён к общему coordinator;
- состояние Events хранится отдельно от Publications в `federation_worker_sync_states`; smoke проверяет частичный сетевой сбой, безопасный повторный запуск и отсутствие перетирания publication cursor;
- `FederatedEventFeedService` объединяет ближайшие локальные опубликованные события и active `event` projections только от доверенных дочерних связей с входящим `content.read`;
- публичный `GET /api/v1/events/aggregated` возвращает безопасный список с явным `source`, не копирует сырой `description_html`, исключает tombstone/peer/parent/no-scope источники и сортирует события по времени начала;
- PostgreSQL/MySQL smoke проверяет локальный + дочерний Events feed, сохранность исходного узла и исключение запрещённых источников;
- Worship получил source-side federation contract: `scheduled` и `cancelled` остаются partner-visible, `withdraw` создаёт tombstone, а повторное `schedule` очищает его;
- partner projection Worship отдаёт stable owner, тип службы, статус, время и место, но не экспортирует сырой `description_html`;
- partner endpoints Worship используют bounded page size и составной курсор `updated_at + public_id`; PostgreSQL/MySQL smoke проверяет lifecycle и отсутствие HTML в projection;
- `FederationWorshipSyncWorker` принимает активный Worship-поток и tombstones как отдельные bounded-потоки, применяет их через remote projections типа `worship` и подключён к общему coordinator;
- состояние Worship хранится под worker ID `worship` отдельно от Publications/Events; smoke проверяет частичный сбой tombstone-потока, безопасное продолжение и сохранность состояния другого worker;
- `FederatedWorshipFeedService` объединяет ближайшие локальные `scheduled/cancelled` записи и active `worship` projections только от доверенных дочерних связей с входящим `content.read`;
- публичный `GET /api/v1/worship/aggregated` сохраняет `cancelled` как видимое состояние, явно передаёт `source`, не копирует сырой `description_html` и исключает tombstone/peer/parent/no-scope источники;
- PostgreSQL/MySQL smoke проверяет локальную + дочернюю Worship-ленту, сохранность источника, отменённое состояние и исключение запрещённых источников;
- `docs/ORGANIZATIONS_AND_FEDERATION.md` фиксирует результаты анализа епархиальных/митрополичьих сайтов и общий domain contract.

- Media/Documents получили явную видимость `private/public/federated`; Documents разрешает внешнюю видимость только после публикации, а снятие публикации и архивирование возвращают `private`;

Дальше:

- Admin/public/API слой People и расширенные сведения о духовенстве;
- определить явную public/federation visibility Media/Documents и затем добавить для них source/worker federation sync;
- Admin/public/API и повторяющиеся правила Worship;
- Admin/public/API, публикация и календарные представления Events;
- безопасный upload/storage pipeline Media, MIME sniffing, derivatives и usage references;
- связь Documents → проверенный Media asset, Admin/public/API и версии файла;
- расширить source-preserving federation-контракт с Publications/Events/Worship на Media/Documents после определения их публичной видимости.

## Installation

Implemented:

- four-step browser installer;
- environment/extension/writeability checks;
- automatic URL defaults;
- site profile selection;
- PostgreSQL/MySQL setup;
- automatic DB creation where credentials allow it;
- automatic migrations;
- first superadmin creation;
- atomic local configuration;
- generated 256-bit secret key;
- completed-installer lock;
- interrupted-install recovery before completion;
- `/health` installation/readiness checks for PHP baseline, completed install state, 256-bit secret key, writable runtime storage, migration validity and database connectivity;
- health endpoint returns HTTP 503 with boolean-only diagnostics when the installation is not ready.

Pending:

- update/backup wizard.

## Обновления и резервные копии

Реализовано:

- каталог резервных копий по умолчанию находится рядом с каталогом сайта, а не внутри публичного web-root;
- `php bin/backup.php create` создаёт снимок `config/local.php`, `storage/uploads` и данных всех таблиц;
- дамп БД формируется через PDO без обязательных `pg_dump`/`mysqldump`;
- значения строк БД сохраняются без потери бинарных данных как base64-or-null;
- манифест содержит версию формата, версию ChurchCMS, список файлов/таблиц, размеры и SHA-256;
- создание считается успешным только после внутренней проверки манифеста и контрольных сумм;
- `php bin/backup.php verify <id>` повторно проверяет готовую копию;
- PostgreSQL CI smoke создаёт тестовую схему миграциями и проверяет резервную копию;
- `php bin/backup.php restore-db <id> --confirm=<id>` восстанавливает данные БД только после повторной проверки manifest/SHA-256 и полного совпадения схемы;
- PostgreSQL restore выполняется транзакционно с порядком внешних ключей и синхронизацией sequence; MySQL сохраняет совместимость и синхронизирует auto_increment;
- CI намеренно меняет данные после создания копии, восстанавливает их и проверяет следующий ID sequence;
- `php bin/backup.php restore-files <id> --confirm=<id>` восстанавливает `config/local.php` и точный снимок `storage/uploads`;
- файловая часть сначала собирается и проверяется во временных кандидатах рядом с целевыми каталогами, затем переключается через `rename`; при ошибке выполняется возврат прежних config/uploads;
- отдельный CI-smoke меняет config/uploads после backup, восстанавливает снимок, проверяет SHA-256 конфигурации, удаление лишнего upload и отсутствие временных restore-файлов;
- `php bin/update.php stage` проверяет manifest, точный payload, PHP minimum, безопасные пути и SHA-256 во внешнем staging;
- staging дополнительно требует цифровую подпись манифеста: `signature.algorithm=openssl-sha256`, доверенный `key_id` и Base64-подпись по однозначному представлению всех остальных полей manifest;
- доверенные публичные ключи задаются в `operations.update_trusted_public_keys`; при пустом наборе обновления намеренно заблокированы, а Admin Shell показывает оператору отдельное предупреждение;
- `tools/update-sign.php` подписывает готовый манифест приватным ключом по абсолютному пути, запрещает хранить приватный ключ внутри репозитория и не копирует его в пакет;
- staged-манифест повторно проверяется при каждом `inspect`, поэтому подмена manifest уже после staging блокирует apply до создания backup и изменения файлов;
- Admin Shell и CLI показывают `key_id` подтверждённой подписи, а успешный apply записывает его в audit metadata;
- CI проверяет валидную подпись, unsigned-пакет, неизвестный ключ, подменённый подписанный manifest и повторную проверку уже staged-пакета;
- `php bin/update.php apply <staging-id> --confirm=<staging-id>` повторно проверяет staged package и применяет только code-only обновления;
- до изменения файлов code-only apply создаёт и проверяет обычную резервную копию config/uploads/БД;
- новые application-файлы готовятся рядом с целевыми путями, предыдущие версии сохраняются до успешного healthcheck;
- после применения проверяются SHA-256, встроенный PHP syntax parser, целевая `app.version` и installation healthcheck;
- при ошибке после начала замены application-файлы автоматически откатываются в обратном порядке;
- CI проверяет успешный apply, автоматический rollback на синтаксически повреждённом PHP и отказ schema-changing пакета до изменения установки;
- модуль `operations` регистрирует раздел «Система» в общем Admin Shell с правом `settings.manage`;
- экран «Система» показывает installation healthcheck, список резервных копий и staging-пакетов без раскрытия/ввода серверных путей;
- из Admin Shell можно создать и повторно проверить backup, а готовый code-only пакет — применить после явного подтверждения;
- Admin Shell умеет единым подтверждённым действием восстановить БД, `config/local.php` и `storage/uploads` из проверенной копии;
- перед browser restore автоматически создаётся и проверяется аварийный снимок текущего состояния; при ошибке после начала изменений оркестратор пытается вернуть из него и БД, и файлы;
- browser restore заранее отклоняет копию, которая переключила бы установку на другое подключение к БД или другой каталог резервных копий; перенос между окружениями остаётся операторской процедурой;
- PostgreSQL smoke намеренно меняет организацию в БД, локальную конфигурацию и uploads, затем проверяет единое восстановление и наличие отдельного аварийного снимка;
- все операции create/verify/restore/apply записываются в audit log; повреждённый или устаревший staging-пакет в UI не становится применимым;
- недоступность backup/staging каталога не роняет весь системный экран: оператор получает безопасное предупреждение;
- сетевой источник релиза задаётся только локальной настройкой `operations.update_release_manifest_url`; браузер не принимает произвольный URL;
- `UpdateReleaseDownloader` разрешает только точный HTTPS `manifest.json`, запрещает redirects/credentials/query/fragment и приватные, loopback, link-local и зарезервированные адреса;
- для доменных имён DNS проверяется до запроса, а выбранный публичный адрес закрепляется через cURL; манифест проверяется подписью до скачивания payload;
- payload скачивается только по путям подписанного манифеста с лимитами 64 МиБ на файл / 256 МиБ на пакет и повторной проверкой размера/SHA-256;
- `php bin/update.php download` и Admin Shell могут получить пакет без ручного staging; успешная загрузка и ошибка фиксируются отдельными audit-событиями;
- smoke использует подменяемый транспорт и проверяет успешную загрузку, повреждённый payload и запрет path traversal без обращения в Интернет.

Остаётся:

- безопасный rollback старой схемы/данных для schema-changing пакетов: текущий логический backup не является DDL snapshot, поэтому автоматический apply migration-файлов намеренно запрещён;
- доверенная автоматическая загрузка релизного пакета из удалённого источника; криптографическая аутентичность локального/staged пакета уже проверяется;
- получение подписанного релизного пакета из Admin Shell без ручного staging.

## Source-side federation документов

- Documents source-side federation отдаёт только `published/federated` карточки через `/api/v1/partner/documents`, без filesystem path/blob; отдельный endpoint tombstones использует тот же составной курсор `updated_at + public_id`;
- уход опубликованного документа из `federated` при смене visibility, withdraw или archive атомарно записывает tombstone; возврат в federation очищает устаревшее удаление;
- PostgreSQL/MySQL smoke проверяет фильтрацию private-документов, безопасную projection, lifecycle tombstone и отказ некорректного public-ID cursor;
- `FederationDocumentSyncWorker` принимает активный поток и tombstone в независимое состояние `documents`, сохраняет удалённые карточки только как remote projection типа `document` и не создаёт из них локальные канонические Documents;
- подтверждённый курсор активного потока сохраняется до запроса tombstone: частичный сетевой сбой не заставляет повторно читать уже применённую страницу;
- PostgreSQL/MySQL smoke проверяет частичный сбой, повторный запуск, tombstone и независимость состояния от Publications;
- для Documents остаётся агрегированное представление с сохранением исходного узла.

## Source-side federation Media

- наружу попадают только явно разрешённые неархивные записи с видимостью `federated` через `/api/v1/partner/media`;
- выдаётся только безопасная карточка метаданных: без исходного имени файла, серверного пути и blob; `blob_available=false` явно показывает, что этот контракт не подтверждает наличие загруженного файла;
- MIME, размер и SHA-256 в текущей модели являются заявленными метаданными карточки и не заменяют будущую проверку фактического blob;
- смена видимости с `federated` и архивирование атомарно записывают tombstone, а повторное включение federation очищает устаревшее удаление;
- PostgreSQL/MySQL smoke проверяет фильтрацию приватных записей, безопасную projection, lifecycle tombstone и составной курсор `updated_at + public_id`;
- принимающий Media worker, remote projection, агрегация и передача реального файла остаются отдельными следующими инкрементами.

## Security / administration

Implemented:

- hardened session cookies;
- CSRF for authenticated browser mutations;
- stateless HMAC token for anonymous public forms;
- security headers/CSP;
- login throttling;
- admin authentication with password_hash/password_verify;
- automatic password rehash;
- RBAC schema/services;
- scope storage;
- audit event foundation;
- production error handler загружается до bootstrap ядра, скрывает внутренние детали и возвращает request ID;
- HTML/API ошибки HTTP 500 получают `Cache-Control: no-store`, а подробность записывается в server log и `storage/logs/error.log`;
- authenticated password rotation доступна из Admin Shell и требует текущий пароль;
- `auth_version` хранится в БД/сессии: после смены пароля все другие старые административные сессии перестают проходить `current()`;
- текущая сессия получает новую auth version, новый session ID и новый CSRF token;
- password rotation записывается в audit log без пароля/секретов;
- protected admin dashboard;
- friendly publication editor;
- comment moderation queue.

Pending:

- roles/users management UI;
- forgotten-password/reset flow с безопасным каналом доставки;
- optional 2FA;
- scoped permission enforcement in future domain modules.

## Publications

Implemented:

- types and workflow statuses;
- DB schema and public UUID;
- site key;
- automatic Russian-friendly slug;
- title/excerpt/body/author;
- comments_enabled per publication;
- explicit syndication targets;
- site-scoped reusable categories and tags with normalized many-to-many storage;
- category/tag editing with bounded input, normalization and duplicate removal;
- batch taxonomy loading for public lists, API collections and syndication without N+1 queries;
- categories/tags in public detail and API resources, and categories in publication lists and syndication feeds;
- PostgreSQL lifecycle smoke covers create, deduplication, reuse, update, orphan cleanup, API and syndication projections;
- MySQL runtime smoke covers taxonomy migration, shared-term reuse and batch reads;
- internal create/update/publish/withdraw service;
- public list/detail pages;
- admin list/editor;
- public API;
- partner API with incremental sync;
- отдельный `GET /api/v1/partner/publications/tombstones` отдаёт минимальные delete-события для ранее partner-видимых публикаций;
- `withdraw()` атомарно сохраняет tombstone вместе со сменой статуса, поэтому сбой журнала не оставляет агрегатор без сигнала удаления;
- tombstone содержит stable public ID публикации, `organization_owner_id`, причину и UTC-время удаления, но не раскрывает body или внутренние DB ID;
- повторный withdraw не создаёт дубликат; RSS-only материал не попадает в partner tombstones;
- PostgreSQL HTTP smoke проверяет Bearer scope и JSON-контракт, тот же доменный lifecycle запускается в MySQL 8.4;
- RSS/Rambler syndication provider;
- отложенная публикация через статус `scheduled` и UTC-время в `published_at`;
- Admin Shell позволяет выбрать локальную дату/время установки, опубликовать сейчас или отменить планирование;
- bounded CLI worker `php bin/publication-schedule.php` atomically публикует только наступившие материалы;
- параллельные/повторные запуски worker безопасны за счёт conditional UPDATE по статусу;
- автоматическая публикация записывается в audit log и инвалидирует публичный cache;
- CI проверяет due/future/cancelled, запрет прошедшего времени, idempotent rerun и элементы редактора;
- audit/cache invalidation on changes.
- публикация хранит stable public ID локальной organization unit владельца;
- новый draft автоматически получает корневую организацию своего `site_key`, если она создана;
- `PublicationService` запрещает назначать владельца из другого сайта или архивной ветки, а public/partner API возвращает `organization_owner_id`;
- migration безопасно backfill существующих публикаций через `organization_site_roots`, оставляя nullable только legacy-строки без корня;
- Admin Shell позволяет выбрать только активного владельца из доступного organization scope;
- `publications.read/create/edit/publish` ограничивают список, глобальный Admin-поиск, счётчик редакционных задач и изменяющие действия поддеревом назначения роли; legacy-публикация без владельца доступна только глобальной роли;
- PostgreSQL/MySQL smoke проверяет owner control, фильтрацию списка, наследование scope и атомарную смену владельца.

Next:

- revisions;
- Media/cover relation.

## Pages / content tree

Implemented:

- отдельный модуль `pages` без внешних runtime-зависимостей;
- PostgreSQL/MySQL схема со стабильными public ID и site-scoped canonical paths;
- optional parent links с `ON DELETE RESTRICT`;
- deterministic sibling order и ограничение длины path, совместимое с MySQL 8 индексами;
- unique `(site_key, path)` invariant и tree/status индексы;
- `Page`, `PageStatus`, `PageRepository` и `PageService`;
- создание draft-страницы с автоматическим slug/path;
- изменение slug пересчитывает canonical path всего поддерева атомарно;
- безопасное перемещение поддерева между родителями с пересчётом descendant paths;
- запрещены self-parent, перемещение внутрь собственного потомка и collision с существующим canonical path;
- при ошибке перемещения транзакция не оставляет частично изменённое дерево;
- body страницы проходит общий HTML sanitizer;
- страница хранит stable public ID локальной organization unit владельца;
- новый draft автоматически получает корневую организацию своего `site_key`, если она создана;
- `PageService` запрещает назначать владельца из другого сайта или архивной ветки;
- migration backfill существующих страниц через `organization_site_roots`, сохраняя nullable для legacy-строк без корня;
- `PageService::publish()` требует опубликованного родителя, перенос опубликованного узла под draft запрещён, а `unpublish()` атомарно скрывает всё поддерево;
- `GET /api/v1/pages` и `GET /api/v1/pages/{public_id}` отдают только опубликованные страницы, stable parent/owner IDs и canonical path;
- Admin Shell содержит organization-scoped дерево Pages, создание/редактирование, выбор владельца и publish/unpublish;
- права `pages.read/create/edit/publish` наследуют organization scope вниз по дереву организаций;
- список владельцев, родительские страницы и изменяющие действия не выходят за доступную ветку; операции над поддеревом требуют доступа ко всем затрагиваемым узлам;
- содержимое, родитель, canonical path, порядок и владелец сохраняются атомарно;
- PostgreSQL smoke проверяет create/rename/move/cycle/collision/rollback, publish/unpublish, API/Admin projection, маршруты и границы RBAC; общий MySQL runtime проверяет schema compatibility.
- опубликованные страницы доступны по canonical path, включая вложенные wildcard-маршруты; черновики публично не выдаются;
- публичный HTML получает canonical/meta, хлебные крошки и стабильный URL из Page API; профильный smoke покрывает canonical routing.

Next:

- menu integration на основе стабилизированного публичного Pages contract.

## Comments

Implemented:

- optional module;
- per-publication on/off switch;
- approved public comments;
- premoderated submissions by default;
- plain-text input;
- stateless public form token;
- rate limiting;
- approve/reject/spam moderation;
- audit events.

## SEO / sharing

Implemented:

- SEO module;
- per-publication SEO storage;
- automatic SEO defaults from publication;
- optional SEO title/description/keywords;
- canonical override;
- index/follow controls;
- Open Graph;
- Twitter/X card tags;
- social title/description/image overrides;
- Article/NewsArticle microdata;
- author/published/modified semantic metadata;
- dynamic robots.txt;
- dynamic XML sitemap;
- Sitemap directive;
- configurable share providers;
- Telegram/VK/OK share links;
- native Web Share button;
- copy-link action;
- no third-party share SDK/runtime dependency.

Next:

- Media image dimensions/variants in Article markup;
- video structured metadata when Video/Media module lands;
- organization/site-level SEO settings UI;
- redirect manager.

## Performance

Implemented:

- anonymous pages do not start PHP sessions;
- dependency-free full-page cache;
- version-based O(1) cache invalidation;
- public max-age + stale-while-revalidate;
- versioned immutable theme assets;
- dependency-free generated-output cache for sitemap and syndication feeds;
- sitemap/feed cache shares O(1) publication invalidation with the public page cache;
- query/API result bounds;
- референсный Nginx/PHP-FPM front-controller профиль с запретом прямого доступа к внутренним каталогам;
- Nginx CI проверяет синтаксис, закрытые URL и ACME challenge;
- детерминированный benchmark-набор для изолированного `site_key=benchmark`;
- CLI-измерения первой/глубокой страницы архива, detail lookup и комментариев с mean/p50/p95/p99;
- отдельный CI-smoke performance-инструментов на PostgreSQL;
- dependency-free HTTP load-runner для cached home/archive/detail и cache-miss detail, ограниченный локальными/приватными целями;
- функциональный CI-smoke HTTP-сценария на disposable PostgreSQL-установке;
- query-plan аудит ключевых publication/comment запросов на полном benchmark-наборе для PostgreSQL 16 и MySQL 8.4;
- CI требует фактического использования ожидаемых list/detail/comment/moderation индексов;
- third-party channel sync isolated from public requests.

Pending before 1.0:

- reference hardware thresholds: заблокировано до выбора/подготовки эталонного Nginx/PHP-FPM + БД стенда и снятия реальных HTTP p50/p95/p99/RPS/error-rate; CI runner не используется как источник release-порогов;

## External channels / social / video

Architecture implemented:

- open provider IDs: no fixed provider whitelist in storage;
- adapter capability vocabulary;
- generic inbound/outbound DTOs;
- encrypted credential storage foundation;
- connection schema;
- publication outbox schema;
- inbound external-content inbox;
- remote ID/fingerprint deduplication;
- sync cursor/error state;
- polling synchronization service;
- outbound dispatcher worker с atomic claim через статус `processing`;
- retry между запусками worker и dead-letter через `failed` после `social.max_attempts`;
- recovery зависшего `processing` после timeout;
- credential расшифровывается только внутри worker перед вызовом adapter;
- CLI `php bin/channel-dispatch.php` поддерживает bounded batch/max-attempts и не входит в public request path;
- runtime capability.

The intended model is:

```
official ChurchCMS publication
        |
        +--> selected external channels (outbox)
        |
        +--> canonical official URL

external network/video content
        |
        +--> inbox/review
        |
        +--> explicit import/link by operator
```

External content never becomes official automatically by default.

Next:

- connection/admin UI;
- inbound review UI;
- Telegram/VK/MAX adapters;
- YouTube/Rutube adapters;
- webhook contract.

## Administration UX

Реализовано:

- общий `layout.admin`, отдельный от публичного layout сайта;
- единый сервис `AdminShell` добавляет пользовательский контекст, активный раздел, поиск и суммарный счётчик задач;
- `AdminNavigationRegistry` позволяет модулям регистрировать раздел, маршрут, требуемое право и порядок без правки общей темы;
- `AdminSearchRegistry` и `AdminSearchService` позволяют модулям подключаться к глобальному поиску с RBAC-фильтрацией и жёсткими лимитами;
- Publications подключён первым поисковым провайдером по title/slug/excerpt;
- `AdminTaskRegistry` и `AdminTaskCenter` собирают pending moderation, редакционную работу и ошибки внешних каналов;
- повторные permission-проверки и агрегат задач кешируются внутри текущего `Request`;
- обзор, Publications, Comments, Search и Tasks используют одну административную оболочку;
- общий header содержит текущий раздел, пользователя, глобальный поиск, задачи и ссылку на публичный сайт;
- desktop использует постоянную боковую навигацию, которую можно полностью свернуть; выбор сохраняется локально в браузере;
- без JavaScript боковая навигация остаётся видимой, а на узких экранах используется компактная горизонтальная панель;
- общий компонент `admin.state` задаёт empty/success/error/loading состояния с `role`, `aria-live` и `aria-busy`;
- активный раздел отмечается через `aria-current`, есть skip-link и единый `:focus-visible`;
- `prefers-reduced-motion` отключает необязательные transitions/animation/smooth scrolling;
- dependency-free `tools/accessibility/admin-audit.php` проверяет landmarks, keyboard/focus правила, target=_blank, aria-контракты и ключевые контрастные пары в CI;
- `docs/ACCESSIBILITY.md` фиксирует автоматическую проверку и ручной pilot-QA сценарий.

Остаётся:

- подключать будущие Media, People, Worship, Events, External Channels, Users/Roles, Themes и Settings к уже существующим контрактам shell/navigation/search/tasks;
- пройти ручной pilot-QA с NVDA/VoiceOver/TalkBack и browser zoom 200%/400% на реальных целевых браузерах.

## Durable project memory

- `docs/PRODUCT_REQUIREMENTS.md`
- `docs/ROADMAP.md`
- `docs/BACKLOG.md`
- `docs/DECISIONS.md`
- `docs/STATUS.md`
- `docs/ARCHITECTURE.md`
- `docs/THEMES.md`
- `docs/DESIGN_SYSTEM.md`
- `docs/API.md`
- `docs/SYNDICATION.md`
- `docs/COMMENTS.md`
- `docs/INSTALLATION.md`
- `docs/BACKUPS.md`
- `docs/UPDATES.md`
- `docs/NGINX.md`
- `docs/ACCESSIBILITY.md`
- `docs/ERROR_HANDLING.md`
- `docs/AUTHENTICATION.md`
- `docs/PUBLICATION_SCHEDULING.md`

Update this file at the end of every substantial implementation increment.
