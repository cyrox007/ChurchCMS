# Release-gate производительности

ChurchCMS не использует GitHub Actions как эталонное железо для производительности. CI проверяет только корректность инструментов. Релизный порог фиксируется после измерения на выбранном реальном стенде Nginx + PHP-FPM + PostgreSQL или MySQL.

## Что уже автоматизировано

1. `tools/performance/seed.php` создаёт репрезентативный набор данных.
2. `tools/performance/benchmark.php` измеряет операции уровня репозитория.
3. `tools/performance/query-plan.php` проверяет критические индексы на полном benchmark-наборе.
4. `tools/performance/http-load.php` прогоняет смешанный HTTP-сценарий и выводит JSON с RPS, ошибками и p50/p95/p99.
5. `tools/performance/release-gate.php` сравнивает JSON `http-load.php` с зафиксированным профилем эталонного стенда и возвращает ненулевой код при нарушении порогов.

## Как зафиксировать эталонный стенд

Перед первым релизным измерением в отдельном JSON-файле необходимо записать фактическую конфигурацию:

```json
{
  "profile": "reference-postgresql-1",
  "reference_hardware": {
    "cpu": "описание CPU и число доступных vCPU",
    "memory_mb": 4096,
    "storage": "NVMe SSD",
    "web_server": "Nginx 1.x",
    "php": "PHP 8.3.x FPM, число worker",
    "database": "PostgreSQL 16.x"
  },
  "limits": {
    "max_errors": 0,
    "max_error_rate": 0,
    "min_requests_per_second": 0,
    "max_latency_p50_ms": 0,
    "max_latency_p95_ms": 0,
    "max_latency_p99_ms": 0,
    "scenarios": {
      "publication_detail_cache_miss": {
        "max_p95_ms": 0,
        "max_p99_ms": 0
      }
    }
  }
}
```

Нули в примере — **не рекомендуемые пороги**. Их нельзя переносить в release profile. Сначала нужно снять несколько стабильных серий измерений на одном и том же сервере, после чего зафиксировать согласованные значения вместе с описанием железа и конфигурации PHP-FPM/БД.

## Порядок релизного прогона

```bash
php tools/performance/seed.php
php tools/performance/query-plan.php > storage/performance-query-plan.json
php tools/performance/http-load.php \
  --base-url=http://127.0.0.1 \
  --requests=1000 \
  --concurrency=20 \
  --warmup=30 \
  > storage/performance-http-load.json

php tools/performance/release-gate.php \
  --result=storage/performance-http-load.json \
  --thresholds=config/performance-release-thresholds.json
```

Проверка должна выполняться на прогретом приложении, без параллельных задач обновления/backup/import и на том же профиле Nginx/PHP-FPM/БД, для которого зафиксированы пороги.

## Что считать релизным блокером

Релиз блокируется, если:

- HTTP-тест вернул ошибки выше разрешённого порога;
- фактический RPS ниже минимального;
- общий p50/p95/p99 выше лимитов;
- один из отдельно ограниченных сценариев вышел за свой p50/p95/p99;
- `query-plan.php` перестал видеть ожидаемый индекс;
- измерение выполнено на другом железе или существенно другой конфигурации и профиль больше нельзя считать сопоставимым.

До появления первого реального профиля задача `define reference hardware and release thresholds` остаётся незавершённой. Это намеренно: цифры производительности нельзя получать из GitHub Actions или назначать без измерения.
