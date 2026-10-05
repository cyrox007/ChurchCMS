#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

temp="${RUNNER_TEMP:-/tmp}"
staging="$temp/churchcms-rc-staging"
backups="$temp/churchcms-rc-update-backups"
private_key="$temp/churchcms-rc-private.pem"

latest_stage_id() {
    local manifest
    manifest="$(find "$staging" -mindepth 2 -maxdepth 2 -name manifest.json -type f -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"
    test -n "$manifest"
    basename "$(dirname "$manifest")"
}

replace_version() {
    FROM_VERSION="$1" TO_VERSION="$2" DESTINATION="$3" python3 <<'PY'
from pathlib import Path
import os

source = Path("config/app.php").read_text()
needle = f"'version' => '{os.environ['FROM_VERSION']}'"
replacement = f"'version' => '{os.environ['TO_VERSION']}'"
if source.count(needle) != 1:
    raise SystemExit("Не удалось подготовить версию schema-changing пакета.")
Path(os.environ["DESTINATION"]).write_text(source.replace(needle, replacement, 1))
PY
}

manifest_for() {
    package="$1"
    from="$2"
    to="$3"
    shift 3
    php -r '
    $package = $argv[1];
    $from = $argv[2];
    $to = $argv[3];
    $paths = array_slice($argv, 4);
    $files = [];
    foreach ($paths as $path) {
        $file = $package . "/" . $path;
        $files[] = [
            "path" => $path,
            "bytes" => filesize($file),
            "sha256" => hash_file("sha256", $file),
        ];
    }
    file_put_contents(
        $package . "/manifest.json",
        json_encode([
            "format" => "churchcms-update-v1",
            "from_version" => $from,
            "version" => $to,
            "php_min" => "8.3",
            "files" => $files,
            "deleted_files" => [],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    );
    ' "$package" "$from" "$to" "$@"
}

code_version='999.10.0-rc-ci'
schema_version='999.10.1-rc-ci'
package="$temp/churchcms-rc-schema-package"
mkdir -p "$package/config" "$package/database/migrations"
replace_version "$code_version" "$schema_version" "$package/config/app.php"

cat > "$package/database/migrations/20991231_230000_rc_schema.php" <<'PHP'
<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20991231_230000_rc_schema';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $pdo->exec('CREATE TABLE rc_schema_gate (id INTEGER PRIMARY KEY, note VARCHAR(64) NOT NULL)');
        $pdo->exec("INSERT INTO rc_schema_gate (id, note) VALUES (1, 'applied')");
    }

    public function down(PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE rc_schema_gate');
    }
};
PHP

manifest_for \
    "$package" \
    "$code_version" \
    "$schema_version" \
    'config/app.php' \
    'database/migrations/20991231_230000_rc_schema.php'

php tools/update-sign.php "$package/manifest.json" "$private_key" rc-ci
php bin/update.php stage "$package" --path="$staging"
stage_id="$(latest_stage_id)"
php bin/update.php apply \
    "$stage_id" \
    --confirm="$stage_id" \
    --path="$staging" \
    --backup-path="$backups" \
    | tee "$temp/churchcms-rc-schema-update.txt"

grep -q 'миграций: 1' "$temp/churchcms-rc-schema-update.txt"
psql postgresql://churchcms:${CHURCHCMS_RC_DB_PASSWORD:-churchcms}@127.0.0.1:5432/churchcms \
    -Atqc 'SELECT note FROM rc_schema_gate WHERE id=1' \
    | grep -qx applied

broken_version='999.10.2-rc-ci'
package="$temp/churchcms-rc-schema-broken"
mkdir -p "$package/config" "$package/database/migrations" "$package/core"
replace_version "$schema_version" "$broken_version" "$package/config/app.php"

cat > "$package/database/migrations/20991231_230001_rc_schema_broken.php" <<'PHP'
<?php

declare(strict_types=1);

use ChurchCMS\Core\ReversibleMigration;
use PDO;

return new class implements ReversibleMigration {
    public function id(): string
    {
        return '20991231_230001_rc_schema_broken';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $pdo->exec('CREATE TABLE rc_schema_broken (id INTEGER PRIMARY KEY)');
    }

    public function down(PDO $pdo, string $driver): void
    {
        $pdo->exec('DROP TABLE rc_schema_broken');
    }
};
PHP

printf '%s\n' '<?php this is not valid php {' > "$package/core/RcBrokenMarker.php"
manifest_for \
    "$package" \
    "$schema_version" \
    "$broken_version" \
    'config/app.php' \
    'database/migrations/20991231_230001_rc_schema_broken.php' \
    'core/RcBrokenMarker.php'

php tools/update-sign.php "$package/manifest.json" "$private_key" rc-ci
php bin/update.php stage "$package" --path="$staging"
stage_id="$(latest_stage_id)"

if php bin/update.php apply \
    "$stage_id" \
    --confirm="$stage_id" \
    --path="$staging" \
    --backup-path="$backups"; then
    echo 'Пакет с post-migration ошибкой ошибочно применён.' >&2
    exit 1
fi

php -r '$c=require "config/app.php"; exit(($c["version"] ?? "") === "999.10.1-rc-ci" ? 0 : 1);'
test ! -e core/RcBrokenMarker.php
test ! -e database/migrations/20991231_230001_rc_schema_broken.php

psql postgresql://churchcms:${CHURCHCMS_RC_DB_PASSWORD:-churchcms}@127.0.0.1:5432/churchcms \
    -Atqc "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_name='rc_schema_broken'" \
    | grep -qx 0
psql postgresql://churchcms:${CHURCHCMS_RC_DB_PASSWORD:-churchcms}@127.0.0.1:5432/churchcms \
    -Atqc "SELECT COUNT(*) FROM churchcms_migrations WHERE migration_id='20991231_230001_rc_schema_broken'" \
    | grep -qx 0

echo 'Schema-changing updater и rollback RC проверены.'
