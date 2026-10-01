<?php
declare(strict_types=1);

/**
 * Convota Web Application Entry Point
 */

require_once __DIR__ . '/../src/Autoload.php';

use Convota\App;

$app = App::getInstance();
$app->run();
