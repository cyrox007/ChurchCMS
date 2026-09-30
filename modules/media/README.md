# Медиатека

Модуль закладывает P0-фундамент метаданных Media с обязательной
принадлежностью к локальной organization unit.

## Что хранится

`media_assets` содержит:

- stable public ID;
- `site_key`;
- обязательный `owner_organization_public_id`;
- статус;
- машинный тип материала;
- исходное имя файла;
- MIME type;
- размер;
- SHA-256;
- необязательные title и alt text;
- даты создания и изменения.

Если владелец не указан, используется site root.

## Безопасное blob-хранилище

Media имеет сервисный pipeline сохранения фактического файла, но пока без
multipart-формы Admin Shell.

`MediaBlobStorage`:

- принимает только обычный читаемый файл, не symbolic link;
- ограничивает размер через `media.max_upload_bytes`;
- копирует поток во временный файл и одновременно считает SHA-256;
- определяет MIME по содержимому через `finfo`, а не по расширению;
- разрешает только явный белый список первого этапа: JPEG/PNG/GIF/WebP/AVIF,
  MP4/WebM/QuickTime/Matroska, базовые audio MIME и PDF;
- не принимает SVG/HTML/архивы до появления отдельной политики активного и
  составного контента;
- сохраняет blob без исходного расширения в content-addressed пути
  `sha256/aa/bb/<hash>.blob`;
- использует атомарный rename и права файла `0600`;
- повторно использует уже существующий blob только после проверки размера и
  SHA-256.

`media.storage_path` обязан быть абсолютным и находиться **вне корня
ChurchCMS**. Проверяется как настроенный путь, так и фактический `realpath`
после создания каталога, поэтому symlink не должен вернуть storage внутрь
web-root.

Путь blob намеренно не хранится в `media_assets`: он детерминирован SHA-256
и остаётся внутренней деталью storage. Исходное имя сохраняется только как
метаданные и никогда не используется как имя файла на диске.

`MediaUploadService` связывает проверенный blob с существующим
`MediaService::registerMetadata()`.

Admin Shell использует тот же pipeline: PHP upload принимается только при
`UPLOAD_ERR_OK` и успешном `is_uploaded_file()`, затем передаётся в
`MediaUploadService`. Контроллер не перемещает временный файл напрямую и не
получает filesystem path итогового blob.

Раздел «Медиатека» требует `media.manage` и применяет organization scope:
оператор видит только доступные карточки и может назначить владельцем только
активную организацию из своей ветки.

Для каждой неархивной карточки Admin Shell позволяет явно выбрать границу
видимости: `private`, `public` или `federated`. Сервер повторно проверяет
organization scope самого asset перед изменением. Переход из `federated`
использует существующий tombstone lifecycle; отдельной упрощённой логики в UI
нет.

## Организационная граница

Сервис принимает только активную organization unit того же `site_key`.
Составной внешний ключ
`(site_key, owner_organization_public_id)` повторяет ограничение на уровне
PostgreSQL/MySQL.

Архивирование сохраняет stable public ID и метаданные вместо hard-delete и
сбрасывает внешнюю видимость в `private`.

## Видимость

Каждый медиаматериал создаётся как `private`. Явно поддерживаются три режима:

- `private` — только локальная служебная запись;
- `public` — разрешено будущее локальное публичное представление;
- `federated` — разрешён экспорт через federation-контракт.

Само переключение видимости не создаёт URL и не делает файл загруженным.
Federation-репозиторий возвращает только `federated` и неархивные записи.

Source-side обмен использует два bounded incremental-потока с составным
курсором `updated_at + public_id`: активные metadata-only записи и tombstone.
`GET /api/v1/partner/media` не передаёт исходное имя файла, путь или blob и
явно возвращает `blob_available=false`. Значения MIME/размера/SHA-256 пока
являются метаданными карточки, а не доказательством наличия проверенного файла.

Уход ранее federated-записи в локальную видимость или archive атомарно создаёт
tombstone. Повторное включение `federated` очищает старый tombstone.

## Принимающая federation-сторона

`FederationMediaSyncWorker` читает активные metadata-only записи и tombstone в
независимое состояние `media`. Полученные данные сохраняются только как remote
projection типа `media` и не превращаются в локальные `media_assets`.

Worker не скачивает файл и не повышает доверие к MIME, размеру или SHA-256:
до безопасного storage pipeline это остаются заявленные метаданные удалённого
узла. Частично подтверждённый курсор сохраняется до tombstone-запроса, поэтому
повторный запуск не перечитывает уже обработанную страницу.

## Безопасная агрегированная лента

`GET /api/v1/media/aggregated` объединяет только локальные неархивные
metadata-карточки с видимостью `public` и active remote projections дочерних
узлов с входящим `content.read`. Локальная видимость `federated` остаётся
отдельным разрешением на межсайтовый экспорт и не делает карточку публичной на
собственном сайте.

Агрегатор повторно строит белый список полей и не прокидывает сырой remote
payload. Исходное имя файла, filesystem path и blob отсутствуют. Поле
`blob_available=false` сохраняется и для локальных, и для удалённых карточек:
заявленные MIME, размер и SHA-256 по-прежнему не означают, что фактический файл
проверен или вообще доступен.

Для remote-карточки сохраняются исходный instance ID, organization owner, имя
узла и canonical URL. Tombstone, parent/peer, неактивные связи и связи без
`content.read` исключаются.

## Производные изображения

Для локальных image-asset доступны два стабильных варианта:

- `thumbnail` — вписывание в 320×320;
- `medium` — вписывание в 1280×1280.

Пропорции сохраняются, апскейл запрещён. Если оригинал уже помещается в
выбранный предел, derivative указывает на тот же проверенный content-addressed
blob и не создаёт лишнюю копию.

Уменьшение первой версии поддерживает JPEG, PNG и WebP через PHP GD.
Полученный файл не считается доверенным автоматически: он повторно проходит
`MediaBlobStorage`, включая MIME sniffing, SHA-256, лимит размера и безопасный
путь вне web-root. Связь asset + variant хранится отдельно в
`media_derivatives`; исходный SHA-256 и оригинальный blob не изменяются.

GIF и AVIF пока не перекодируются: для них сервис возвращает явную ошибку
вместо неявной потери анимации или несовместимой обработки.

## Публичная выдача локального blob

Локальный Media asset с `visibility=public` может быть отдан только по
hash-versioned URL:

```text
/media/{media_public_id}/{sha256}/{variant}
```

Поддерживаются `GET` и `HEAD`. Запрошенный SHA-256 обязан совпасть с
оригиналом или зарегистрированным derivative, поэтому при смене содержимого
меняется и URL. Для ответа используются `ETag`,
`Cache-Control: public, max-age=31536000, immutable` и
`X-Content-Type-Options: nosniff`.

Перед отдачей ChurchCMS повторно проверяет:

- asset существует локально;
- статус не `archived`;
- видимость строго `public`;
- тип — `image`, `document`, `audio` или `video`;
- blob является обычным читаемым файлом внутри настроенного Media storage;
- фактический размер совпадает с зарегистрированным.

Private/federated asset, неверный hash, отсутствующий derivative и remote
federation projection дают 404 и не раскрывают файловый путь.

`GET /api/v1/media/{public_id}` возвращает локальные публичные метаданные,
`blob_url` и список доступных derivatives. Агрегированная лента помечает
`blob_available=true` только для локального public asset с реально доступным
blob. Remote federation по-прежнему остаётся metadata-only.

Audio/video original blob обслуживаются тем же hash-versioned маршрутом с
HTTP byte ranges. Поддерживается один диапазон за запрос:

- `bytes=start-end`;
- `bytes=start-`;
- `bytes=-suffix`.

Корректный диапазон получает `206 Partial Content`, `Content-Range`,
`Accept-Ranges: bytes` и потоковую отдачу кусками до 1 МиБ. Некорректный,
множественный или выходящий за размер диапазон получает `416` с
`Content-Range: bytes */<size>`. `HEAD` возвращает те же метаданные без
тела. `If-Range` с несовпавшим ETag переводит запрос на полный `200`.

Это закрывает resumable download/streaming локальных public audio/video.

## Resumable outbound video pipeline

Для исходящей загрузки больших видео реализован отдельный внутренний pipeline,
который не отдаёт адаптеру filesystem path и не читает весь файл в память.

`MediaBinarySourceService`:

- разрешает только активный локальный Media asset типа `video`;
- повторно проверяет обычный файл внутри Media storage, размер и SHA-256;
- читает данные с произвольного offset ограниченными чанками до 16 МиБ;
- возвращает только `MediaBinaryChunk` с offset, next offset и total bytes.

Состояние удалённой resumable-сессии хранится в
`media_resumable_transfers`. Remote session URL/token шифруется через
`SecretVault`; открытое значение в БД не сохраняется. Offset обновляется
compare-and-set операцией, поэтому два worker не могут одновременно продвинуть
один transfer. После ошибки transfer можно перезапустить новой удалённой
сессией; завершённый transfer с тем же target key повторно не стартует.

`media.resumable-upload` также разрешает выбранное для публикации
`publication-seo/video` в безопасный descriptor
`public_id + MIME + bytes + SHA-256`. `ChannelOutboundDispatcher` передаёт
этот descriptor через `ChannelOutboundItem.media`; локальный путь туда не
попадает.

Dispatcher теперь допускает адаптер с единственной capability
`publish.video`, но только когда outbound item действительно содержит
валидный video descriptor.

Provider-specific создание resumable upload session, отправка чанков и
финализация остаются задачами самих YouTube/Rutube adapters.

## Usage references

Медиатека хранит явные ссылки использования asset другими сущностями через
`media_usage_references`. Один consumer slot уникален по
`site_key + consumer_type + consumer_public_id + usage_key`.

Архивирование Media блокируется, пока существуют активные ссылки. Замена набора
ссылок одного consumer выполняется атомарно и не позволяет сослаться на
архивный или отсутствующий asset.

## Галереи

Gallery foundation хранит редакционную карточку отдельно от изображений:
`media_galleries` содержит stable public ID, organization owner, статус,
видимость, название и описание.

Состав и порядок используют общий Media usage registry:

```text
consumer_type = gallery
consumer_public_id = <public ID галереи>
usage_key = item:000001, item:000002, ...
```

Разрешены только активные Media asset типа `image`; дубликаты запрещены.
Порядок определяется номером slot. Одна галерея ограничена 200 изображениями.

Новая галерея создаётся как `draft/private`. Публикация пустой галереи
запрещена, а `public` видимость разрешается только опубликованной карточке.
Withdraw возвращает `draft/private`. Archive переводит карточку в
`archived/private` и снимает её usage references, поэтому изображения после
этого снова можно архивировать.

Admin Shell предоставляет отдельный раздел «Галереи» под `media.manage`: создание черновика, редактирование карточки, scoped-выбор доступных изображений, числовой порядок, publish/withdraw/archive. Сервер повторно проверяет organization scope и каждого выбранного Media asset. Рядом с каждым изображением показывается его текущая Media visibility.

## Публичные галереи

Публичный vertical slice использует:

- `GET /galleries` — HTML-список;
- `GET /galleries/{public_id}` — HTML-карточка;
- `GET /api/v1/galleries` — публичный API-список;
- `GET /api/v1/galleries/{public_id}` — публичный API detail.

В projection попадают только галереи `published/public` и только их
неархивные Media asset типа `image` с `visibility=public`. Private и
federated изображения не раскрываются ни в JSON, ни в HTML.

Для отображения используются уже проверенные hash-versioned Media URLs:
сначала `medium`, затем original, затем `thumbnail` как fallback.
Derivative и original дополнительно проходят существующую
`MediaPublicFileService` проверку blob/storage. Галерея без единого public
изображения не попадает в публичный список и detail.

## Следующие инкременты

Остаются:

- подключение derivatives к редакторам и публичным шаблонам;
- binary/resumable outbound YouTube/Rutube.
