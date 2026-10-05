#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

temp="${RUNNER_TEMP:-/tmp}"
log="$temp/churchcms-rc-installer.log"
cookies="$temp/churchcms-rc-cookies.txt"
server_pid=''

cleanup() {
    code=$?
    if [[ "$code" -ne 0 && -f "$log" ]]; then
        echo '--- Лог installer ---' >&2
        cat "$log" >&2 || true
    fi
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
    fi
    exit "$code"
}
trap cleanup EXIT

csrf_from() {
    sed -n 's/.*name="csrf_token" value="\([^"]*\)".*/\1/p' "$1" | head -1
}

rm -f config/local.php
mkdir -p storage/cache storage/logs storage/sessions storage/rate-limits storage/uploads
php -S 127.0.0.1:18080 -t "$root" >"$log" 2>&1 &
server_pid=$!

step2="$temp/churchcms-rc-step2.html"
ready='0'
for attempt in $(seq 1 30); do
    if ! kill -0 "$server_pid" 2>/dev/null; then
        echo 'PHP-сервер installer завершился раньше времени.' >&2
        exit 1
    fi
    if curl --fail --silent --show-error \
        --cookie-jar "$cookies" \
        'http://127.0.0.1:18080/install.php?step=2' \
        -o "$step2"; then
        ready='1'
        break
    fi
    sleep 1
done

test "$ready" = '1'
csrf="$(csrf_from "$step2")"
test -n "$csrf"

step3="$temp/churchcms-rc-step3.html"
curl --fail --silent --show-error \
    --cookie "$cookies" \
    --cookie-jar "$cookies" \
    --data-urlencode "csrf_token=$csrf" \
    --data-urlencode 'step=2' \
    --data-urlencode 'site_name=ChurchCMS RC' \
    --data-urlencode 'profile=parish' \
    --data-urlencode 'site_url=http://127.0.0.1:18080' \
    --data-urlencode 'db_driver=pgsql' \
    --data-urlencode 'db_host=127.0.0.1' \
    --data-urlencode 'db_port=5432' \
    --data-urlencode 'db_name=churchcms' \
    --data-urlencode 'db_user=churchcms' \
    --data-urlencode "db_password=${CHURCHCMS_RC_DB_PASSWORD:-churchcms}" \
    'http://127.0.0.1:18080/install.php?step=2' \
    -o "$step3"

grep -q '3. Создадим администратора' "$step3"
csrf="$(csrf_from "$step3")"
test -n "$csrf"

finished="$temp/churchcms-rc-finished.html"
admin_password="${CHURCHCMS_RC_ADMIN_PASSWORD:-ChurchCMS-RC-Password-2026!}"
curl --fail --silent --show-error \
    --cookie "$cookies" \
    --cookie-jar "$cookies" \
    --data-urlencode "csrf_token=$csrf" \
    --data-urlencode 'step=3' \
    --data-urlencode 'admin_name=Администратор RC' \
    --data-urlencode 'admin_username=rc-admin' \
    --data-urlencode 'admin_email=rc@example.test' \
    --data-urlencode "admin_password=$admin_password" \
    --data-urlencode "admin_password_confirm=$admin_password" \
    'http://127.0.0.1:18080/install.php?step=3' \
    -o "$finished"

grep -q 'ChurchCMS установлена' "$finished"
php -r '$c=require "config/local.php"; exit(($c["installation"]["completed"] ?? false) === true ? 0 : 1);'

psql postgresql://churchcms:${CHURCHCMS_RC_DB_PASSWORD:-churchcms}@127.0.0.1:5432/churchcms \
    -Atqc "SELECT COUNT(*) FROM admin_users WHERE username='rc-admin' AND status='active'" \
    | grep -qx 1
psql postgresql://churchcms:${CHURCHCMS_RC_DB_PASSWORD:-churchcms}@127.0.0.1:5432/churchcms \
    -Atqc "SELECT COUNT(*) FROM admin_user_roles aur JOIN admin_users u ON u.id=aur.user_id JOIN roles r ON r.id=aur.role_id WHERE u.username='rc-admin' AND r.role_key='superadmin'" \
    | grep -qx 1

status="$(curl --silent --output "$temp/churchcms-rc-locked.txt" --write-out '%{http_code}' 'http://127.0.0.1:18080/install.php')"
test "$status" = '404'
grep -q 'Installer is locked' "$temp/churchcms-rc-locked.txt"

echo 'Web-installer RC проверен.'
