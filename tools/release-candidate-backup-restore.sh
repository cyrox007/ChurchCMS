#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

temp="${RUNNER_TEMP:-/tmp}"
backup_path="$temp/churchcms-rc-backups"
db_password="${CHURCHCMS_RC_DB_PASSWORD:-churchcms}"
dsn="postgresql://churchcms:${db_password}@127.0.0.1:5432/churchcms"
mkdir -p "$backup_path" storage/uploads

echo 'до резервной копии' > storage/uploads/rc-restore-marker.txt
psql "$dsn" -v ON_ERROR_STOP=1 \
    -c "UPDATE admin_users SET display_name='До резервной копии' WHERE username='rc-admin'"

php bin/backup.php create --path="$backup_path" | tee "$temp/churchcms-rc-backup-create.txt"
backup_id="$(sed -n 's/^Резервная копия создана и проверена: \([^;]*\);.*/\1/p' "$temp/churchcms-rc-backup-create.txt")"
if [[ -z "$backup_id" ]]; then
    echo 'Не удалось определить ID созданной резервной копии.' >&2
    exit 1
fi

php bin/backup.php verify "$backup_id" --path="$backup_path"

psql "$dsn" -v ON_ERROR_STOP=1 \
    -c "UPDATE admin_users SET display_name='После резервной копии' WHERE username='rc-admin'"
echo 'после резервной копии' > storage/uploads/rc-restore-marker.txt

php -r '
$path = "config/local.php";
$config = require $path;
$config["release_candidate_mutation"] = true;
file_put_contents(
    $path,
    "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n",
);
'

php bin/backup.php restore-db \
    "$backup_id" \
    --confirm="$backup_id" \
    --path="$backup_path"
php bin/backup.php restore-files \
    "$backup_id" \
    --confirm="$backup_id" \
    --path="$backup_path"

psql "$dsn" \
    -Atqc "SELECT display_name FROM admin_users WHERE username='rc-admin'" \
    | grep -qx 'До резервной копии'
grep -qx 'до резервной копии' storage/uploads/rc-restore-marker.txt

php -r '
$config = require "config/local.php";
if (array_key_exists("release_candidate_mutation", $config)) {
    fwrite(STDERR, "config/local.php не восстановлен из резервной копии.\n");
    exit(1);
}
'

echo 'Backup и restore RC проверены.'
