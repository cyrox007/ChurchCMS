# Worker внешних каналов

## Назначение

Публикация во внешние соцсети, мессенджеры и видеоплатформы не выполняется в публичном HTTP-запросе ChurchCMS.

Очередь хранится в `publication_social_posts`, а отправку выполняет отдельный bounded worker:

```bash
php bin/channel-dispatch.php
```

Дополнительные параметры:

```bash
php bin/channel-dispatch.php --limit=20 --max-attempts=5
```

Значения по умолчанию задаются в `social.dispatch_batch_size` и `social.max_attempts`.

## Состояния outbox

- `pending` — запись ждёт отправки или повторной попытки;
- `processing` — запись атомарно зарезервирована одним запуском worker;
- `sent` — adapter подтвердил отправку;
- `failed` — достигнут лимит попыток, запись считается dead-letter.

Перед выборкой worker возвращает слишком старые `processing` обратно в `pending`. Это защищает очередь от зависшего/убитого процесса.

## Доставка

Worker:

1. atomically claims bounded batch;
2. проверяет, что connection включён и разрешает outbound;
3. находит зарегистрированный provider adapter;
4. проверяет capability публикации;
5. убеждается, что исходная публикация сейчас публична;
6. расшифровывает credential через `SecretVault`;
7. формирует `ChannelOutboundItem` со стабильным `sourceId` и canonical URL сайта;
8. вызывает adapter;
9. отмечает запись `sent` либо возвращает в retry/dead-letter.

Credential не записывается в outbox и не передаётся в публичный request path.

## Семантика надёжности

Базовый worker обеспечивает **at-least-once delivery**.

Если удалённый сервис принял публикацию, но процесс ChurchCMS аварийно завершился до `markSent`, после recovery запись может быть отправлена повторно.

Поэтому adapter должен использовать стабильный `ChannelOutboundItem::sourceId` и remote provider возможности идемпотентности/поиска дубля там, где это доступно.

ChurchCMS не заявляет exactly-once delivery там, где удалённая платформа не предоставляет подходящий протокол.

## Запуск

Для обычной установки worker можно запускать cron/systemd timer с разумным интервалом, например раз в минуту.

Параллельные процессы допустимы: статус `processing` не даёт им одновременно зарезервировать одну и ту же `pending` запись.

## CI

`social-outbound-worker-smoke.yml` использует локальный mock adapter без внешней сети и проверяет:

- успешную отправку;
- расшифровку credential;
- canonical URL и стабильный source ID;
- retry с увеличением attempts;
- переход в dead-letter после лимита;
- recovery зависшего `processing`.
