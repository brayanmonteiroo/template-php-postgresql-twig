<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$config = require $basePath . '/config/app.php';
$GLOBALS['app_config'] = $config;

$pdo = require $basePath . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => $config['session_lifetime'] ?? 7200,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require $basePath . '/src/Helpers/functions.php';

$loader = new \Twig\Loader\FilesystemLoader($basePath . '/views');
$twig = new \Twig\Environment($loader, [
    'cache' => $config['debug'] ? false : $basePath . '/storage/cache/twig',
    'auto_reload' => (bool) $config['debug'],
    'autoescape' => 'html',
]);

$userModel = new App\Models\User($pdo);
$roleModel = new App\Models\Role($pdo);
$authService = new App\Services\AuthService($userModel);
$permissionService = new App\Services\PermissionService($userModel, $authService);
$userService = new App\Services\UserService($userModel, $roleModel);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$twig->addGlobal('user', $authService->user() ?? []);
$twig->addGlobal('permissions', $permissionService->getUserPermissions());
$twig->addGlobal('csrf_token', $_SESSION['csrf_token'] ?? '');

$container = [
    'config' => $config,
    'pdo' => $pdo,
    'twig' => $twig,
    'authService' => $authService,
    'userModel' => $userModel,
    'permissionService' => $permissionService,
    'userService' => $userService,
    'roleModel' => $roleModel,
    'csrf_token' => $_SESSION['csrf_token'],
    'middlewares' => [
        'auth' => fn (array $c) => \App\Middleware\AuthMiddleware::run($c),
        'permission' => fn (array $c, string $p) => \App\Middleware\PermissionMiddleware::run($c, $p),
    ],
];

$dispatcher = new App\Core\Dispatcher(
    require $basePath . '/routes.php',
    $container
);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$dispatcher->handle($method, $uri);
