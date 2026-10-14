<?php

declare(strict_types=1);

define('SITEPATH', __DIR__);

require SITEPATH . '/core/ErrorHandler.php';
\ChurchCMS\Core\ErrorHandler::register(SITEPATH);

$localConfigPath = SITEPATH . '/config/local.php';
$installationCompleted = false;

if (is_file($localConfigPath) && !is_link($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (!is_array($localConfig)) {
        throw new RuntimeException('Локальная конфигурация ChurchCMS должна возвращать массив.');
    }

    $installationCompleted = ($localConfig['installation']['completed'] ?? false) === true;
}

if (!$installationCompleted) {
    $scriptName = str_replace(
        '\\',
        '/',
        (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'),
    );
    $lastSlash = strrpos($scriptName, '/');
    $basePath = $lastSlash === false ? '' : substr($scriptName, 0, $lastSlash);
    $installerUrl = rtrim($basePath, '/') . '/install.php';
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $status = in_array($method, ['GET', 'HEAD'], true) ? 302 : 303;

    header('Cache-Control: no-store');
    header('Location: ' . $installerUrl, true, $status);
    exit;
}

require SITEPATH . '/core.php';

\ChurchCMS\Core\Router::getInstance()->dispatch();
