# Финальный performance gate ChurchCMS 1.0

Этот проход выполняется **после** зелёного автоматического RC gate и перед созданием финального тега 1.0.

GitHub Actions для этого шага не подходит: его виртуальное железо меняется и не является эталонным сервером ChurchCMS.

## 1. Зафиксировать эталонный стенд

На сервере, где будет выполняться замер, из корня ChurchCMS запустите:

```bash
php tools/performance/reference-host.php \
  --output=/tmp/churchcms-reference-host.json
```

Инструмент фиксирует:

- модель CPU и число логических ядер;
- объём RAM;
- параметры корневого хранилища;
- версию веб-сервера;
- PHP/PHP-FPM;
- версию серверной PostgreSQL/MySQL, если доступна установленная ChurchCMS;
- ОС, ядро и архитектуру.

Секреты БД, токены, пароли и содержимое `config/local.php` в результат не попадают.

Если часть окружения нельзя определить автоматически, её нужно указать явно:

```bash
php tools/performance/reference-host.php \
  --web-server='nginx/1.26.3' \
  --php-runtime='PHP 8.3.35 FPM' \
  --database='PostgreSQL 16.10' \
  --storage='NVMe SSD, 40 GiB' \
  --output=/tmp/churchcms-reference-host.json
```

Не следует заменять реальные характеристики стенда описанием вроде `production`, `fast VPS` или `CI`.

## 2. Подготовить данные

Нагрузочный замер должен выполняться на репрезентативном наборе данных, а не на пустой установке.

Используйте существующий seed/benchmark-контур ChurchCMS и убедитесь, что публикация с переданным `--slug` существует и доступна публично.

До замера:

1. завершите миграции;
2. прогрейте PHP-FPM;
3. убедитесь, что Nginx работает с теми же настройками, которые планируются для пилота;
4. не запускайте одновременно backup, импорт, обновление или другие тяжёлые служебные задачи;
5. зафиксируйте commit/tag тестируемой ChurchCMS.

## 3. Запустить HTTP-нагрузку

`http-load.php` намеренно принимает только localhost/loopback/приватный IPv4, чтобы инструмент нельзя было случайно использовать для внешней нагрузки.

Пример:

```bash
php tools/performance/http-load.php \
  --base=http://127.0.0.1 \
  --requests=10000 \
  --concurrency=20 \
  --warmup=100 \
  --slug=benchmark-000001 \
  > /tmp/churchcms-http-load.json
```

Результат содержит:

- общее число запросов;
- concurrency;
- ошибки и долю ошибок;
- RPS;
- p50/p95/p99 общей задержки;
- p50/p95/p99 по сценариям;
- распределение HTTP status codes.

Для утверждения порогов рекомендуется выполнить минимум три одинаковых прогона после прогрева и не брать за основу единичный лучший результат.

## 4. Утвердить thresholds.json

Пороговый файл **не генерируется автоматически**, потому что решение о допустимой деградации — релизное решение, а не функция тестового скрипта.

Минимальная структура:

```json
{
  "profile": "churchcms-1.0-reference-pgsql",
  "reference_hardware": {
    "cpu": "...",
    "memory_mb": 2048,
    "storage": "...",
    "web_server": "...",
    "php": "...",
    "database": "..."
  },
  "limits": {
    "max_errors": 0,
    "max_error_rate": 0,
    "min_requests_per_second": 0,
    "max_latency_p50_ms": 0,
    "max_latency_p95_ms": 0,
    "max_latency_p99_ms": 0,
    "scenarios": {}
  }
}
```

Нули в примере для RPS/latency — **не рекомендуемые значения** и не должны копироваться как реальные пороги. Их нужно заменить утверждёнными числами по результатам эталонных прогонов.

Блок `reference_hardware` переносится из `/tmp/churchcms-reference-host.json` без изменения фактов о стенде.

## 5. Проверить release-gate

После утверждения порогов:

```bash
php tools/performance/release-gate.php \
  --result=/tmp/churchcms-http-load.json \
  --thresholds=/path/to/churchcms-1.0-thresholds.json
```

Или выполнить тот же gate вместе с полным предрелизным аудитом:

```bash
php bin/release-audit.php \
  --performance-result=/tmp/churchcms-http-load.json \
  --performance-thresholds=/path/to/churchcms-1.0-thresholds.json
```

Команда должна завершиться с кодом `0`.

## 6. Что сохранить вместе с релизным решением

Для воспроизводимости 1.0 нужно сохранить:

- SHA commit или финальный tag;
- JSON профиля эталонного сервера;
- сырой результат каждого принятого HTTP load-test;
- утверждённый thresholds-файл;
- итоговый вывод `release-gate.php`;
- дату замера.

Приватные ключи обновлений, `config/local.php`, пароли БД и другие секреты к этим материалам не прикладываются.

## Условие готовности 1.0

Performance-задача в `docs/BACKLOG.md` закрывается только после реального замера на зафиксированном эталонном стенде и успешного `release-gate.php`. Успешный smoke в GitHub Actions этого условия не заменяет.
