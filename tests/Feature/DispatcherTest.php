<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Dispatcher;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DispatcherTest extends TestCase
{
    private function createMinimalContainer(): array
    {
        $loader = new ArrayLoader([
            'errors/404.twig' => '<html><body>404 - Página não encontrada</body></html>',
            'errors/403.twig' => '<html><body>403 - Acesso negado</body></html>',
            'errors/405.twig' => '<html><body>405 - Método não permitido</body></html>',
            'errors/500.twig' => '<html><body>500 - Erro interno</body></html>',
        ]);
        $twig = new Environment($loader);

        $authService = $this->createStub(\App\Services\AuthService::class);
        $authService->method('check')->willReturn(false);

        $permissionService = $this->createStub(\App\Services\PermissionService::class);
        $permissionService->method('hasPermission')->willReturn(true);

        return [
            'twig' => $twig,
            'authService' => $authService,
            'permissionService' => $permissionService,
            'middlewares' => [
                'auth' => fn (array $c) => \App\Middleware\AuthMiddleware::run($c),
                'permission' => fn (array $c, string $p) => \App\Middleware\PermissionMiddleware::run($c, $p),
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['app_base_path'] = dirname(__DIR__, 2);
        if (!function_exists('render_error')) {
            require dirname(__DIR__, 2) . '/src/Helpers/functions.php';
        }
    }

    public function testNotFoundReturns404AndRendersErrorPage(): void
    {
        $basePath = dirname(__DIR__, 2);
        $routes = require $basePath . '/routes.php';
        $container = $this->createMinimalContainer();
        $dispatcher = new Dispatcher($routes, $container);

        ob_start();
        $dispatcher->handle('GET', '/nonexistent-route-xyz');
        $output = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertStringContainsString('404', $output);
    }

    public function testMethodNotAllowedReturns405(): void
    {
        $basePath = dirname(__DIR__, 2);
        $routes = require $basePath . '/routes.php';
        $container = $this->createMinimalContainer();
        $dispatcher = new Dispatcher($routes, $container);

        ob_start();
        $dispatcher->handle('POST', '/');
        $output = ob_get_clean();

        $this->assertSame(405, http_response_code());
        $this->assertStringContainsString('405', $output);
    }
}
