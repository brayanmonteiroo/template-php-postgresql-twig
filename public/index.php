<?php
// desabilitar o strict types. Strict types é uma funcionalidade do PHP que permite que o código seja mais rigoroso e seguro. Setado com 1 ele força o uso de strict types, o que é útil para evitar erros de tipo.
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

// diretório raiz do projeto
$basePath = dirname(__DIR__);

// carrega o autoload do projeto
require $basePath . '/vendor/autoload.php';

// carrega o arquivo .env
$dotenv = Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

// carrega o arquivo de configuração do projeto
$config = require $basePath . '/config/app.php';
$GLOBALS['app_config'] = $config;

// carrega o arquivo de configuração do banco de dados  
$pdo = require $basePath . '/config/database.php';

// inicia a sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    // verifica se a sessão é segura
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

// carrega o arquivo de funções do projeto
require $basePath . '/src/Helpers/functions.php';

// carrega o loader do twig
$loader = new FilesystemLoader($basePath . '/views');

// cria o ambiente do twig
$twig = new Environment($loader, [
    'cache' => $config['debug'] ? false : $basePath . '/storage/cache/twig',
    'auto_reload' => (bool) $config['debug'],
    'autoescape' => 'html',
]);

// Instancia os models e services.
$userModel = new User($pdo);
$roleModel = new Role($pdo);
$permissionModel = new Permission($pdo);
$authService = new AuthService($userModel);
$permissionService = new PermissionService($userModel, $authService);
$userService = new UserService($userModel, $roleModel);
$roleService = new RoleService($roleModel, $permissionModel);

// cria o token CSRF se não estiver criado
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// adiciona as variáveis globais do twig
$twig->addGlobal('user', $authService->user() ?? []);
$twig->addGlobal('permissions', $permissionService->getUserPermissions());
$twig->addGlobal('csrf_token', $_SESSION['csrf_token'] ?? '');
$twig->addGlobal('flash_messages', flash_get());

// adiciona a função build_query do twig
$twig->addFunction(new TwigFunction('build_query', function (array $params): string {
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return http_build_query($params);
}));

// Cria o container. Container é um array associativo que contém as dependências do projeto.
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

// Cria o dispatcher. Dispatcher é o responsável por rotear a requisição para o controller apropriado.
$dispatcher = new Dispatcher(
    require $basePath . '/routes.php',
    $container
);

// Obtém o método e a URI da requisição.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
// Decodifica a URI da requisição.
$uri = rawurldecode($uri);

// Rota a requisição para o controller apropriado.
$dispatcher->handle($method, $uri);
