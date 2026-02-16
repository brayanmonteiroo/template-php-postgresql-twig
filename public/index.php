<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/vendor/autoload.php';

$app = require $basePath . '/config/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$app['dispatcher']->handle($method, $uri);
