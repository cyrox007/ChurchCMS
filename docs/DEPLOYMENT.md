# Production-развёртывание ChurchCMS

Документ описывает рекомендуемое развёртывание ChurchCMS на обычном Linux VPS с Nginx, PHP-FPM 8.3+ и PostgreSQL либо MySQL. Примеры используют каталог `/var/www/churchcms`, системного пользователя `churchcms` и домен `churchcms.example`.

## 1. Требования

Минимально необходимы:

- PHP 8.3+ CLI и PHP-FPM;
- PDO и драйвер выбранной БД (`pdo_pgsql` либо `pdo_mysql`);
- OpenSSL;
- Fileinfo;
- cURL для сетевых интеграций и обновлений;
- Nginx;
- PostgreSQL или MySQL;
- `cron` и `flock` (`util-linux` в Debian/Ubuntu);
- действующий HTTPS-сертификат для production.

Перед публикацией сайта выполните встроенный предрелизный аудит:

```bash
cd /var/www/churchcms
sudo -u churchcms php bin/release-audit.php
```

## 2. Отдельный системный пользователь

Не запускайте ChurchCMS в общем пуле PHP-FPM нескольких сайтов. Создайте отдельного системного пользователя:

```bash
sudo useradd --system --home /var/www/churchcms --shell /usr/sbin/nologin churchcms
```

Код, локальная конфигурация, служебные каталоги и внешний Media storage должны принадлежать этому пользователю. Это необходимо в том числе для встроенного обновлятора, который выполняет контролируемую замену файлов после обязательной резервной копии.

Пример базовых прав:

```bash
sudo chown -R churchcms:churchcms /var/www/churchcms
sudo find /var/www/churchcms -type d -exec chmod 0750 {} \;
sudo find /var/www/churchcms -type f -exec chmod 0640 {} \;
```

Не делайте `config/local.php`, резервные копии, Media blob и приватные ключи доступными через web-root.

## 3. База данных

ChurchCMS поддерживает PostgreSQL и MySQL. Создайте отдельную БД и отдельного пользователя с правами только на эту БД.

Для PostgreSQL пример выглядит так:

```sql
CREATE ROLE churchcms LOGIN PASSWORD 'замените-на-сложный-пароль';
CREATE DATABASE churchcms OWNER churchcms;
```

Для MySQL:

```sql
CREATE DATABASE churchcms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'churchcms'@'localhost' IDENTIFIED BY 'замените-на-сложный-пароль';
GRANT ALL PRIVILEGES ON churchcms.* TO 'churchcms'@'localhost';
FLUSH PRIVILEGES;
```

Пароль БД хранится только в локальной конфигурации установки.

## 4. PHP-FPM

В репозитории находится шаблон:

```text
deploy/php-fpm/churchcms.conf.example
```

Скопируйте его в каталог пулов PHP-FPM вашей системы, например:

```bash
sudo cp deploy/php-fpm/churchcms.conf.example /etc/php/8.3/fpm/pool.d/churchcms.conf
sudo php-fpm8.3 -t
sudo systemctl reload php8.3-fpm
```

Шаблон использует отдельный сокет:

```text
/run/php/php8.3-fpm-churchcms.sock
```

Лимиты загрузки согласованы с максимальным размером Media в ChurchCMS: `upload_max_filesize=512M`, `post_max_size=520M`. При уменьшении лимита приложения уменьшите также лимиты PHP-FPM и Nginx.

`pm.max_children` в примере — стартовое значение, а не универсальная настройка производительности. Подберите его после измерения фактического потребления памяти одним worker на вашем сервере.

## 5. Nginx и HTTPS

Production-шаблон:

```text
deploy/nginx/churchcms.conf.example
```

Перед включением замените:

- `churchcms.example` на реальный домен;
- пути `ssl_certificate` и `ssl_certificate_key`;
- `/var/www/churchcms`, если используется другой корень установки.

После этого:

```bash
sudo cp deploy/nginx/churchcms.conf.example /etc/nginx/sites-available/churchcms.conf
sudo ln -s /etc/nginx/sites-available/churchcms.conf /etc/nginx/sites-enabled/churchcms.conf
sudo nginx -t
sudo systemctl reload nginx
```

Шаблон:

- перенаправляет HTTP на HTTPS;
- оставляет HTTP-доступ к ACME challenge;
- запрещает прямую выдачу внутренних каталогов;
- запрещает прямой запуск любых PHP-файлов кроме `install.php`;
- направляет обычные URL только в `index.php`;
- использует отдельный FPM-сокет ChurchCMS;
- устанавливает `client_max_body_size 512m`.

HSTS в шаблоне намеренно закомментирован. Включайте его только после того, как HTTPS окончательно настроен и откат на HTTP не потребуется.

## 6. Первая установка

После настройки Nginx и PHP-FPM откройте:

```text
https://ваш-домен/install.php
```

Мастер выполняет проверку окружения, создаёт локальную конфигурацию, применяет миграции и создаёт первого супер-администратора. После успешной установки повторный запуск блокируется.

Сразу после установки проверьте:

```bash
curl --fail --silent --show-error https://ваш-домен/health
```

И отдельно:

```bash
cd /var/www/churchcms
sudo -u churchcms php bin/migrate.php validate
sudo -u churchcms php bin/theme-check.php
sudo -u churchcms php bin/release-audit.php
```

## 7. Фоновые задачи

Пример cron находится в:

```text
deploy/cron/churchcms.example
```

Установите его как системный cron-файл:

```bash
sudo cp deploy/cron/churchcms.example /etc/cron.d/churchcms
sudo chmod 0644 /etc/cron.d/churchcms
```

Каждая команда обёрнута в `flock -n`, поэтому новый экземпляр не стартует, пока предыдущий ещё выполняется.

Шаблон запускает:

- `publication-schedule.php` — отложенную публикацию;
- `channel-dispatch.php` — исходящую очередь внешних каналов;
- `channels-sync.php` — получение входящих данных каналов;
- `federation-sync.php` — синхронизацию ChurchCMS-узлов;
- `distribution-webhooks.php` — исходящие подписанные webhooks;
- `log-maintenance.php` — обслуживание архивов журнала ошибок.

Если соответствующая функция на конкретной установке не используется, её cron-строку можно отключить.

## 8. Резервные копии

Перед первым production-запуском проверьте каталог резервных копий в `config/local.php`. Он должен находиться вне публичного корня сайта.

Ручная проверка:

```bash
cd /var/www/churchcms
sudo -u churchcms php bin/backup.php create
```

Автоматический backup в cron-шаблоне оставлен закомментированным намеренно. Перед его включением определите:

1. частоту копирования;
2. срок хранения;
3. внешний носитель или отдельный сервер;
4. мониторинг успешности задания;
5. регулярную проверку восстановления.

Сам факт наличия backup-файла не считается проверкой аварийного восстановления.

## 9. Журналы

Основной журнал ошибок:

```text
storage/logs/error.log
```

ChurchCMS автоматически ротирует его при достижении установленного лимита. Дополнительное обслуживание:

```bash
sudo -u churchcms php /var/www/churchcms/bin/log-maintenance.php
```

Подробности находятся в `docs/OBSERVABILITY.md`.

## 10. Media, backup и staging вне web-root

По умолчанию ChurchCMS рассчитан на размещение крупных служебных данных рядом с каталогом приложения, а не внутри него. Проверьте настройки:

- `media.storage_path`;
- `operations.backup_path`;
- `operations.update_staging_path`.

Каталоги должны принадлежать пользователю `churchcms`, быть недоступны из Nginx и иметь достаточный запас дискового пространства.

## 11. Обновление production

Для штатного обновления используйте встроенный Admin Shell либо CLI обновлятора. Не заменяйте файлы поверх работающей установки вручную без резервной копии.

Перед обновлением:

```bash
cd /var/www/churchcms
sudo -u churchcms php bin/backup.php create
sudo -u churchcms php bin/release-audit.php
```

После обновления:

```bash
sudo -u churchcms php bin/migrate.php validate
curl --fail --silent --show-error https://ваш-домен/health
```

Schema-changing обновления ChurchCMS принимаются только при наличии обратимых миграций и проходят автоматический rollback при ошибке.

## 12. Post-deploy checklist

После первой установки и после инфраструктурных изменений проверьте:

```bash
sudo php-fpm8.3 -t
sudo nginx -t
sudo -u churchcms php /var/www/churchcms/bin/migrate.php validate
sudo -u churchcms php /var/www/churchcms/bin/theme-check.php
sudo -u churchcms php /var/www/churchcms/bin/release-audit.php
curl --fail --silent --show-error https://ваш-домен/health
```

Дополнительно вручную проверьте:

- вход администратора по HTTPS;
- загрузку файла размером больше 32 МБ, если такие файлы используются;
- создание и проверку резервной копии;
- выполнение cron без параллельных дублей;
- наличие новых записей в `storage/logs/error.log` при тестовой ошибке на staging;
- отсутствие HTTP-доступа к `/config`, `/storage`, `/docs`, `/deploy`, `/bin`;
- достаточный свободный диск для Media, backup и update staging.

## 13. Что не хранить в репозитории

Никогда не коммитьте:

- `config/local.php`;
- пароли БД;
- OAuth/client secrets;
- токены внешних каналов;
- приватные ключи подписи релизов;
- резервные копии;
- Media blob;
- реальные TLS private key.

Production-секреты должны существовать только на целевой установке или во внешнем защищённом secret storage.
