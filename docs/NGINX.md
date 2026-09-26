# Nginx и PHP-FPM

Референсный профиль находится в `deploy/nginx/churchcms.conf.example`.

Он является безопасной базой для обычного standalone-развёртывания ChurchCMS через Nginx + PHP-FPM. Перед использованием нужно заменить как минимум:

- `server_name churchcms.local` на реальный домен;
- `root /var/www/churchcms` на абсолютный путь установки;
- `fastcgi_pass unix:/run/php/php8.3-fpm.sock` на фактический socket или TCP endpoint PHP-FPM.

## Front controller

Обычные запросы не обслуживаются как произвольные файлы из корня репозитория. Nginx передаёт их в `index.php`, а маршрутизацию выполняет ChurchCMS.

Отдельно разрешён `install.php`, потому что web installer является основным сценарием свежей установки. После успешной установки сам ChurchCMS блокирует повторный запуск installer.

## Прямой доступ к внутренним данным

Референсный профиль возвращает запрет для внутренних каталогов:

- `app`;
- `bin`;
- `config`;
- `core`;
- `database`;
- `docs`;
- `modules`;
- `storage`;
- `themes`;
- `tools`;
- скрытых файлов, включая `.git`.

`storage/uploads` сейчас также не публикуется напрямую. Будущий Media-модуль должен выдавать только безопасно разрешённые ресурсы через собственный контракт, а не открывать весь runtime storage.

Исключение сделано для `/.well-known/acme-challenge/`, чтобы первоначальное получение TLS-сертификата не требовало изменения приложения.

## HTTPS

Example-конфигурация намеренно содержит только базовый HTTP server block, потому что пути сертификатов и способ TLS termination зависят от окружения. В production сайт должен работать по HTTPS: либо TLS завершается на этом Nginx, либо перед ним находится доверенный reverse proxy/load balancer.

При прямом TLS на Nginx следует добавить отдельный HTTPS server block и перенаправление HTTP → HTTPS после успешного выпуска сертификата.

## Кеширование

Профиль не включает `fastcgi_cache` автоматически.

ChurchCMS уже отдаёт публичные `Cache-Control`/`stale-while-revalidate` заголовки и имеет локальный dependency-free page cache. Принудительный Nginx microcache без учёта cookies, Authorization и invalidation может закешировать приватный ответ, поэтому он должен добавляться только отдельным проверенным профилем.

## Проверка

CI устанавливает Nginx только как инструмент проверки deployment profile и выполняет:

```bash
nginx -t
```

Затем на временном runner проверяются запреты внутренних URL и ACME endpoint. Установка Nginx в CI не создаёт новую PHP runtime-зависимость ChurchCMS.

Производительность этого CI не является release benchmark. RPS/p95/p99 измеряются отдельно на зафиксированном эталонном Nginx/PHP-FPM стенде.
