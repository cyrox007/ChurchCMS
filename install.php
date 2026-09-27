<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

$root = __DIR__;
$localConfigPath = $root . '/config/local.php';

require_once $root . '/core/SiteProfileCatalog.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function installerHttps(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    return in_array($https, ['on', '1', 'true'], true) || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function installerHost(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));

    return preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:]+\])(?::[0-9]{1,5})?$/D', $host) === 1
        ? $host
        : 'localhost';
}

function detectedSiteUrl(): string
{
    return (installerHttps() ? 'https' : 'http') . '://' . installerHost();
}

function installerUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr(
        (ord($bytes[6]) & 0x0f) | 0x40
    );
    $bytes[8] = chr(
        (ord($bytes[8]) & 0x3f) | 0x80
    );

    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

function installerCsrf(): string
{
    $_SESSION['churchcms_install_csrf'] ??= bin2hex(random_bytes(32));
    return (string) $_SESSION['churchcms_install_csrf'];
}

function verifyInstallerCsrf(): void
{
    $expected = installerCsrf();
    $actual = (string) ($_POST['csrf_token'] ?? '');

    if ($actual === '' || !hash_equals($expected, $actual)) {
        http_response_code(419);
        exit('Сессия установки устарела. Обновите страницу и повторите действие.');
    }
}

function installationCompleted(string $path): bool
{
    if (!is_file($path) || is_link($path)) {
        return false;
    }

    $config = require $path;
    return is_array($config)
        && (($config['installation']['completed'] ?? false) === true);
}

function normalizeSiteUrl(string $url): string
{
    $url = rtrim(trim($url), '/');
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = (string) parse_url($url, PHP_URL_HOST);
    $port = parse_url($url, PHP_URL_PORT);
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

    if (
        !in_array($scheme, ['http', 'https'], true)
        || $host === ''
        || ($path !== '' && $path !== '/')
    ) {
        throw new InvalidArgumentException('Адрес сайта должен выглядеть как https://example.ru');
    }

    return $scheme . '://' . $host . ($port !== null ? ':' . (int) $port : '');
}

function normalizeDatabaseName(string $name): string
{
    $name = trim($name);

    if (preg_match('/^[A-Za-z0-9_]{1,63}$/D', $name) !== 1) {
        throw new InvalidArgumentException('Имя базы может содержать только латинские буквы, цифры и _.');
    }

    return $name;
}

function databaseConnection(
    string $driver,
    string $host,
    int $port,
    string $database,
    string $username,
    string $password,
): PDO {
    $dsn = match ($driver) {
        'pgsql' => "pgsql:host={$host};port={$port};dbname={$database}",
        'mysql' => "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        default => throw new InvalidArgumentException('Неподдерживаемый тип базы данных.'),
    };

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function connectOrCreateDatabase(
    string $driver,
    string $host,
    int $port,
    string $database,
    string $username,
    string $password,
): PDO {
    try {
        return databaseConnection($driver, $host, $port, $database, $username, $password);
    } catch (PDOException $firstError) {
        try {
            if ($driver === 'pgsql') {
                $server = databaseConnection('pgsql', $host, $port, 'postgres', $username, $password);
                $server->exec('CREATE DATABASE "' . str_replace('"', '""', $database) . '"');
            } else {
                $server = new PDO(
                    "mysql:host={$host};port={$port};charset=utf8mb4",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ],
                );
                $server->exec(
                    'CREATE DATABASE ' . $database
                    . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
            }

            return databaseConnection($driver, $host, $port, $database, $username, $password);
        } catch (Throwable $createError) {
            throw new RuntimeException(
                'Не получилось подключиться к базе или создать её автоматически. '
                . 'Создайте пустую базу в панели хостинга и повторите попытку.',
                0,
                $createError,
            );
        }
    }
}

function writeLocalConfig(string $path, array $config): void
{
    $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
        . var_export($config, true)
        . ";\n";

    $temp = $path . '.installing-' . bin2hex(random_bytes(6));

    if (file_put_contents($temp, $content, LOCK_EX) === false) {
        throw new RuntimeException('Не удалось сохранить настройки. Проверьте права папки config.');
    }

    @chmod($temp, 0600);

    if (!rename($temp, $path)) {
        @unlink($temp);
        throw new RuntimeException('Не удалось завершить сохранение настроек.');
    }

    @chmod($path, 0600);
}

function prepareStorage(string $root): void
{
    foreach ([
        'storage',
        'storage/cache',
        'storage/logs',
        'storage/sessions',
        'storage/rate-limits',
        'storage/uploads',
    ] as $relative) {
        $path = $root . '/' . $relative;

        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('Не удалось подготовить служебную папку: ' . $relative);
        }

        if (!is_writable($path)) {
            throw new RuntimeException('Нет прав на запись в служебную папку: ' . $relative);
        }
    }
}

function requirements(string $root): array
{
    return [
        'PHP 8.3 или новее' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'PDO' => extension_loaded('pdo'),
        'PostgreSQL или MySQL' => extension_loaded('pdo_pgsql') || extension_loaded('pdo_mysql'),
        'DOM' => extension_loaded('dom'),
        'Fileinfo' => extension_loaded('fileinfo'),
        'OpenSSL для безопасного хранения токенов интеграций' => extension_loaded('openssl'),
        'Безопасный генератор случайных данных' => function_exists('random_bytes'),
        'Безопасное хранение паролей' => function_exists('password_hash') && function_exists('password_verify'),
        'Можно записать настройки' => is_dir($root . '/config') && is_writable($root . '/config'),
        'Можно подготовить служебные файлы' => is_dir($root . '/storage')
            ? is_writable($root . '/storage')
            : is_writable($root),
        'Ядро ChurchCMS найдено' => is_file($root . '/core.php') && is_file($root . '/bin/migrate.php'),
    ];
}

session_set_cookie_params([
    'httponly' => true,
    'secure' => installerHttps(),
    'samesite' => 'Lax',
]);
session_start();

if (installationCompleted($localConfigPath)) {
    http_response_code(404);
    exit('Installer is locked.');
}

$step = max(1, min(4, (int) ($_GET['step'] ?? 1)));
$errors = [];
$success = '';
$checks = requirements($root);
$detectedUrl = detectedSiteUrl();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyInstallerCsrf();
    $postedStep = (int) ($_POST['step'] ?? 0);

    if ($postedStep === 2) {
        try {
            $siteName = trim((string) ($_POST['site_name'] ?? ''));
            $profile = (string) ($_POST['profile'] ?? 'parish');
            $siteUrl = normalizeSiteUrl((string) ($_POST['site_url'] ?? $detectedUrl));
            $driver = (string) ($_POST['db_driver'] ?? 'pgsql');
            $host = trim((string) ($_POST['db_host'] ?? '127.0.0.1'));
            $port = (int) ($_POST['db_port'] ?? ($driver === 'pgsql' ? 5432 : 3306));
            $database = normalizeDatabaseName((string) ($_POST['db_name'] ?? 'churchcms'));
            $username = trim((string) ($_POST['db_user'] ?? ''));
            $password = (string) ($_POST['db_password'] ?? '');

            if ($siteName === '' || strlen($siteName) > 180) {
                throw new InvalidArgumentException('Укажите название сайта.');
            }

            if (!\ChurchCMS\Core\SiteProfileCatalog::exists($profile)) {
                throw new InvalidArgumentException('Выберите тип сайта.');
            }

            if (!in_array($driver, ['pgsql', 'mysql'], true)) {
                throw new InvalidArgumentException('Выберите PostgreSQL или MySQL.');
            }

            if ($host === '' || $username === '' || $port < 1 || $port > 65535) {
                throw new InvalidArgumentException('Проверьте данные подключения к базе.');
            }

            $requiredExtension = $driver === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql';
            if (!extension_loaded($requiredExtension)) {
                throw new RuntimeException('На сервере не включено расширение ' . $requiredExtension . '.');
            }

            prepareStorage($root);
            connectOrCreateDatabase($driver, $host, $port, $database, $username, $password);

            $config = [
                'installation' => [
                    'completed' => false,
                    'started_at' => gmdate(DATE_ATOM),
                ],
                'app' => [
                    'url' => $siteUrl,
                ],
                'security' => [
                    'secret_key' => base64_encode(random_bytes(32)),
                ],
                'site' => [
                    'name' => $siteName,
                    'profile' => $profile,
                ],
                'federation' => [
                    'instance_id' => installerUuidV4(),
                    'enabled' => true,
                ],
                'database' => [
                    'driver' => $driver,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                ],
                'syndication' => [
                    'site_url' => $siteUrl,
                    'channel_title' => $siteName,
                    'channel_description' => 'Новости ' . $siteName,
                ],
            ];

            writeLocalConfig($localConfigPath, $config);

            try {
                require $root . '/core.php';

                $runner = new \ChurchCMS\Core\MigrationRunner(
                    \ChurchCMS\Core\DatabaseManager::getInstance(),
                    $root,
                );
                $runner->migrate();

                \ChurchCMS\Modules\Organizations\OrganizationService::fromDatabase()
                    ->ensureSiteRoot(
                        $siteName,
                        $profile,
                    );
            } catch (Throwable $migrationError) {
                @unlink($localConfigPath);
                throw new RuntimeException(
                    'Не удалось подготовить структуру базы. Проверьте параметры подключения и повторите.',
                    0,
                    $migrationError,
                );
            }

            $step = 3;
        } catch (Throwable $e) {
            error_log('ChurchCMS installer step 2: ' . $e->getMessage());
            $errors[] = $e->getMessage();
            $step = 2;
        }
    }

    if ($postedStep === 3) {
        try {
            if (!is_file($localConfigPath)) {
                throw new RuntimeException('Сначала настройте сайт и базу данных.');
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            require_once $root . '/core.php';

            $username = trim((string) ($_POST['admin_username'] ?? 'admin'));
            $displayName = trim((string) ($_POST['admin_name'] ?? 'Администратор'));
            $email = trim((string) ($_POST['admin_email'] ?? ''));
            $password = (string) ($_POST['admin_password'] ?? '');
            $confirmation = (string) ($_POST['admin_password_confirm'] ?? '');

            if (preg_match('/^[A-Za-z0-9_.-]{3,100}$/D', $username) !== 1) {
                throw new InvalidArgumentException('Логин: минимум 3 символа, латиница, цифры, точка, _ или -.');
            }

            if ($displayName === '') {
                throw new InvalidArgumentException('Укажите имя администратора.');
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Проверьте email.');
            }

            if (strlen($password) < 12) {
                throw new InvalidArgumentException('Пароль должен содержать не менее 12 символов.');
            }

            if (!hash_equals($password, $confirmation)) {
                throw new InvalidArgumentException('Пароли не совпадают.');
            }

            $pdo = \ChurchCMS\Core\DatabaseManager::getInstance()->connection();

            $existing = $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
            if ((int) $existing > 0) {
                throw new RuntimeException('Первый администратор уже создан.');
            }

            $pdo->beginTransaction();

            try {
                $now = gmdate('Y-m-d H:i:s');
                $insert = $pdo->prepare(
                    'INSERT INTO admin_users (
                        public_id, username, password_hash, display_name, email, status,
                        last_login_at, created_at, updated_at
                     ) VALUES (
                        :public_id, :username, :password_hash, :display_name, :email, :status,
                        NULL, :created_at, :updated_at
                     )'
                );
                $insert->execute([
                    'public_id' => \ChurchCMS\Core\Uuid::v4(),
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'display_name' => $displayName,
                    'email' => $email !== '' ? $email : null,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $findUser = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
                $findUser->execute(['username' => $username]);
                $userId = (int) $findUser->fetchColumn();

                $findRole = $pdo->prepare('SELECT id FROM roles WHERE role_key = :role_key LIMIT 1');
                $findRole->execute(['role_key' => 'superadmin']);
                $roleId = (int) $findRole->fetchColumn();

                if ($userId <= 0 || $roleId <= 0) {
                    throw new RuntimeException('Не удалось назначить права первого администратора.');
                }

                $assign = $pdo->prepare(
                    'INSERT INTO admin_user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
                );
                $assign->execute([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ]);

                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            $local = require $localConfigPath;
            if (!is_array($local)) {
                throw new RuntimeException('Повреждён файл локальных настроек.');
            }

            $local['installation'] = [
                'completed' => true,
                'completed_at' => gmdate(DATE_ATOM),
            ];
            writeLocalConfig($localConfigPath, $local);

            $step = 4;
            $success = 'Готово. ChurchCMS установлена, база подготовлена, администратор создан.';
        } catch (Throwable $e) {
            error_log('ChurchCMS installer step 3: ' . $e->getMessage());
            $errors[] = $e->getMessage();
            $step = 3;
        }
    }
}

if ($step === 1) {
    foreach ($checks as $label => $ok) {
        if (!$ok) {
            $errors[] = 'Нужно исправить: ' . $label;
        }
    }
}

$csrf = htmlspecialchars(installerCsrf(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Установка ChurchCMS</title>
<style>
:root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#18202a;background:#f5f1e9;--navy:#0b315d;--wine:#6b1f2d;--line:#d8d1c6;--muted:#67717d}*{box-sizing:border-box}body{margin:0;padding:20px}.shell{width:min(760px,100%);margin:24px auto}.card{padding:clamp(20px,5vw,36px);background:#fffdf9;border:1px solid var(--line);border-radius:22px;box-shadow:0 20px 60px rgba(7,29,54,.08)}h1{margin:0;color:var(--navy);font-family:Georgia,serif;font-size:clamp(30px,6vw,48px);line-height:1}h2{margin:28px 0 8px;color:var(--navy);font-size:22px}p{color:var(--muted);line-height:1.55}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;margin:24px 0}.steps span{height:7px;border-radius:99px;background:#e6e0d6}.steps span.on{background:var(--wine)}.notice{margin:12px 0;padding:12px 14px;border-radius:10px}.error{background:#fff0f1;color:#721c2d;border:1px solid #edc5ca}.success{background:#edf8f0;color:#235f35;border:1px solid #c8e5d0}.checks{padding:0;list-style:none}.checks li{padding:8px 0;border-bottom:1px solid #eee8df}.ok{color:#276942}.fail{color:#9b293b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{display:grid;gap:6px;margin:14px 0}.field span{font-weight:700;font-size:13px}.field small{color:var(--muted);line-height:1.35}.field input,.field select{width:100%;min-height:46px;padding:10px 12px;border:1px solid #bdb4a8;border-radius:9px;background:#fff;font:inherit}.advanced{margin-top:18px;padding:14px;border:1px solid var(--line);border-radius:12px}.advanced summary{cursor:pointer;font-weight:700}.button,button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:10px 18px;border:0;border-radius:10px;background:var(--wine);color:#fff;font:inherit;font-weight:750;text-decoration:none;cursor:pointer}.button-row{display:flex;gap:10px;margin-top:24px}.button-row>*{flex:1}.muted{background:#eef1f4;color:#26394f}.hint{margin:18px 0;padding:14px;border-left:4px solid var(--navy);background:#edf3f8;color:#37536f;border-radius:0 10px 10px 0}@media(max-width:620px){body{padding:8px}.shell{margin:8px auto}.card{border-radius:16px}.grid{grid-template-columns:1fr}.button-row{flex-direction:column}}</style>
</head>
<body>
<main class="shell">
<section class="card">
<h1>ChurchCMS</h1>
<p>Мастер сам подготовит базу, сайт и первого администратора. Для обычной установки не нужны Composer и командная строка.</p>

<div class="steps" aria-label="Шаг <?= $step ?> из 4">
<?php for ($i = 1; $i <= 4; $i++): ?><span class="<?= $i <= $step ? 'on' : '' ?>"></span><?php endfor; ?>
</div>

<?php foreach ($errors as $error): ?>
<div class="notice error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
<?php endforeach; ?>

<?php if ($success !== ''): ?>
<div class="notice success"><?= htmlspecialchars($success, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($step === 1): ?>
<h2>1. Всё ли готово?</h2>
<p>ChurchCMS проверила сервер автоматически.</p>
<ul class="checks">
<?php foreach ($checks as $label => $ok): ?>
<li class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✓' : '✕' ?> <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
<?php endforeach; ?>
</ul>
<?php if ($errors === []): ?>
<div class="button-row"><a class="button" href="?step=2">Продолжить</a></div>
<?php endif; ?>

<?php elseif ($step === 2): ?>
<h2>2. Настроим сайт</h2>
<p>Нужны только основные данные. Редкие технические параметры спрятаны.</p>

<form method="post">
<input type="hidden" name="csrf_token" value="<?= $csrf ?>">
<input type="hidden" name="step" value="2">

<label class="field">
<span>Название сайта</span>
<input name="site_name" required maxlength="180" placeholder="Например, Владимирский собор">
</label>

<label class="field">
<span>Тип сайта</span>
<select name="profile">
<?php foreach (\ChurchCMS\Core\SiteProfileCatalog::all() as $profileKey => $profileInfo): ?>
<option value="<?= htmlspecialchars($profileKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" <?= $profileKey === 'parish' ? 'selected' : '' ?>>
<?= htmlspecialchars($profileInfo['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
</option>
<?php endforeach; ?>
</select>
<small>Профиль задаёт стартовый набор возможностей, но не ограничивает дальнейшее развитие сайта.</small>
</label>

<label class="field">
<span>Адрес сайта</span>
<input name="site_url" value="<?= htmlspecialchars($detectedUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
</label>

<h2>База данных</h2>
<div class="grid">
<label class="field">
<span>Тип базы</span>
<select name="db_driver">
<option value="pgsql" <?= extension_loaded('pdo_pgsql') ? 'selected' : '' ?>>PostgreSQL</option>
<option value="mysql" <?= !extension_loaded('pdo_pgsql') && extension_loaded('pdo_mysql') ? 'selected' : '' ?>>MySQL</option>
</select>
</label>

<label class="field">
<span>Имя базы</span>
<input name="db_name" value="churchcms" required>
</label>

<label class="field">
<span>Пользователь базы</span>
<input name="db_user" required autocomplete="username">
</label>

<label class="field">
<span>Пароль базы</span>
<input name="db_password" type="password" autocomplete="current-password">
</label>
</div>

<details class="advanced">
<summary>Дополнительные настройки базы</summary>
<div class="grid">
<label class="field"><span>Сервер</span><input name="db_host" value="127.0.0.1"></label>
<label class="field"><span>Порт</span><input name="db_port" value="<?= extension_loaded('pdo_pgsql') ? '5432' : '3306' ?>" inputmode="numeric"></label>
</div>
</details>

<div class="hint">Если база ещё не создана, ChurchCMS попробует создать её сама. Если хостинг это запрещает, мастер подскажет создать пустую базу в панели хостинга.</div>

<div class="button-row"><button type="submit">Подготовить сайт</button></div>
</form>

<?php elseif ($step === 3): ?>
<h2>3. Создадим администратора</h2>
<p>Это первая учётная запись с полными правами. Остальных редакторов можно добавить позже.</p>

<form method="post">
<input type="hidden" name="csrf_token" value="<?= $csrf ?>">
<input type="hidden" name="step" value="3">

<div class="grid">
<label class="field"><span>Ваше имя</span><input name="admin_name" value="Администратор" required></label>
<label class="field"><span>Логин</span><input name="admin_username" value="admin" required autocomplete="username"></label>
</div>

<label class="field"><span>Email <small>необязательно</small></span><input name="admin_email" type="email" autocomplete="email"></label>

<div class="grid">
<label class="field"><span>Пароль</span><input name="admin_password" type="password" required minlength="12" autocomplete="new-password"></label>
<label class="field"><span>Повторите пароль</span><input name="admin_password_confirm" type="password" required minlength="12" autocomplete="new-password"></label>
</div>

<div class="button-row"><button type="submit">Завершить установку</button></div>
</form>

<?php else: ?>
<h2>4. Готово</h2>
<p>База подготовлена, настройки сохранены, первый администратор создан. При следующем открытии установщик уже будет заблокирован.</p>
<div class="button-row">
<a class="button" href="/admin/login">Войти в панель управления</a>
<a class="button muted" href="/">Открыть сайт</a>
</div>
<?php unset($_SESSION['churchcms_install_csrf']); ?>
<?php endif; ?>

</section>
</main>
</body>
</html>
