<?php

declare(strict_types=1);

use App\Core\Dispatcher;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use App\Services\PermissionService;
use App\Services\RoleService;
use App\Services\UserService;
use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

$basePath = dirname(__DIR__);
$GLOBALS['app_base_path'] = $basePath;

$dotenv = Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

$config = require $basePath . '/config/app.php';
$GLOBALS['app_config'] = $config;

require $basePath . '/src/Helpers/functions.php';

security_headers();

set_exception_handler(function (Throwable $e): void {
    $debug = $GLOBALS['app_config']['debug'] ?? false;
    $logMessage = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    if (function_exists('app_log')) {
        app_log('error', $logMessage, ['trace' => $e->getTraceAsString()]);
    } else {
        error_log($logMessage . "\n" . $e->getTraceAsString());
    }
    if ($debug) {
        http_response_code(500);
        echo '<h1>500 Internal Server Error</h1><pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()) . '</pre>';
        return;
    }
    $twig = $GLOBALS['app_twig'] ?? null;
    if ($twig instanceof Environment) {
        render_error($twig, 500);
    } else {
        http_response_code(500);
        echo '500 Internal Server Error';
    }
});

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

$loader = new FilesystemLoader($basePath . '/views');
$twig = new Environment($loader, [
    'cache' => $config['debug'] ? false : $basePath . '/storage/cache/twig',
    'auto_reload' => (bool) $config['debug'],
    'autoescape' => 'html',
]);
$GLOBALS['app_twig'] = $twig;

$userModel = new User($pdo);
$roleModel = new Role($pdo);
$permissionModel = new Permission($pdo);
$authService = new AuthService($userModel);
$permissionService = new PermissionService($userModel, $authService);
$userService = new UserService($userModel, $roleModel);
$roleService = new RoleService($roleModel, $permissionModel);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$twig->addGlobal('user', $authService->user() ?? []);
$twig->addGlobal('permissions', $permissionService->getUserPermissions());
$twig->addGlobal('csrf_token', $_SESSION['csrf_token'] ?? '');
$twig->addGlobal('flash_messages', flash_get());
$twig->addFunction(new TwigFunction('build_query', function (array $params): string {
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}));

$container = [
    'config' => $config,
    'pdo' => $pdo,
    'twig' => $twig,
    'authService' => $authService,
    'userModel' => $userModel,
    'permissionService' => $permissionService,
    'userService' => $userService,
    'roleModel' => $roleModel,
    'permissionModel' => $permissionModel,
    'roleService' => $roleService,
    'csrf_token' => $_SESSION['csrf_token'],
    'middlewares' => [
        'auth' => fn (array $c) => AuthMiddleware::run($c),
        'permission' => fn (array $c, string $p) => PermissionMiddleware::run($c, $p),
    ],
];

$dispatcher = new Dispatcher(
    require $basePath . '/routes.php',
    $container
);

return [
    'container' => $container,
    'dispatcher' => $dispatcher,
];
