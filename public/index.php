<?php

/**
 * Front controller — punto de entrada único de la plataforma.
 */

declare(strict_types=1);

$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->run();
