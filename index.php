<?php

declare(strict_types=1);

define('SITEPATH', __DIR__);

require SITEPATH . '/core/ErrorHandler.php';
\ChurchCMS\Core\ErrorHandler::register(SITEPATH);

require SITEPATH . '/core.php';

\ChurchCMS\Core\Router::getInstance()->dispatch();
