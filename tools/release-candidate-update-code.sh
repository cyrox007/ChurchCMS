#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

temp="${RUNNER_TEMP:-/tmp}"
package="$temp/churchcms-rc-code-package"
staging="$temp/churchcms-rc-staging"
backups="$temp/churchcms-rc-update-backups"
private_key="$temp/churchcms-rc-private.pem"
public_key="$temp/churchcms-rc-public.pem"
mkdir -p "$package/config" "$package/core" "$staging" "$backups"

openssl genpkey \
    -algorithm RSA \
    -pkeyopt rsa_keygen_bits:2048 \
    -out "$private_key" >/dev/null 2>&1
openssl pkey \
    -in "$private_key" \
    -pubout \
    -out "$public_key" >/dev/null 2>&1

RC_PUBLIC_KEY="$public_key" php -r '
$path = "config/local.php";
$config = require $path;
$config["operations"]["update_trusted_public_keys"]["rc-ci"] = file_get_contents(
    getenv("RC_PUBLIC_KEY")
);
file_put_contents(
    $path,
    "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n",
);
'

current_version="$(php -r '$c=require "config/app.php"; echo $c["version"];')"
new_version='999.10.0-rc-ci'

FROM_VERSION="$current_version" TO_VERSION="$new_version" PACKAGE="$package" python3 <<'PY'
from pathlib import Path
import os

source = Path("config/app.php").read_text()
needle = f"'version' => '{os.environ['FROM_VERSION']}'"
replacement = f"'version' => '{os.environ['TO_VERSION']}'"
if source.count(needle) != 1:
    raise SystemExit("Не удалось подготовить config/app.php code-only пакета.")
package = Path(os.environ["PACKAGE"])
(package / "config/app.php").write_text(source.replace(needle, replacement, 1))
PY

cat > "$package/core/RcUpdateMarker.php" <<'PHP'
<?php

declare(strict_types=1);

namespace ChurchCMS\Core;

final class RcUpdateMarker
{
    public const VALUE = 'code-only-applied';
}
PHP

php -r '
$package = $argv[1];
$from = $argv[2];
$to = $argv[3];
$paths = ["config/app.php", "core/RcUpdateMarker.php"];
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
' "$package" "$current_version" "$new_version"

php tools/update-sign.php "$package/manifest.json" "$private_key" rc-ci
php bin/update.php stage "$package" --path="$staging"

manifest="$(find "$staging" -mindepth 2 -maxdepth 2 -name manifest.json -type f -printf '%T@ %p\n' | sort -nr | head -1 | cut -d' ' -f2-)"
test -n "$manifest"
stage_id="$(basename "$(dirname "$manifest")")"

php bin/update.php apply \
    "$stage_id" \
    --confirm="$stage_id" \
    --path="$staging" \
    --backup-path="$backups" \
    | tee "$temp/churchcms-rc-code-update.txt"

grep -q 'backup:' "$temp/churchcms-rc-code-update.txt"
php -r '$c=require "config/app.php"; exit(($c["version"] ?? "") === "999.10.0-rc-ci" ? 0 : 1);'
php -l core/RcUpdateMarker.php

echo 'Code-only updater RC проверен.'
