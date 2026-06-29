<?php

declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once dirname(__DIR__) . '/src/Core/ConfigLoader.php';
require_once dirname(__DIR__) . '/src/Core/SecurityUtils.php';
require_once dirname(__DIR__) . '/src/Core/Logger.php';
require_once dirname(__DIR__) . '/app/models/init.php';
