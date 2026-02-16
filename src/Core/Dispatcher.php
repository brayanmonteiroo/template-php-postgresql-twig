<?php

namespace App\Core;

use FastRoute\Dispatcher as FastRouteDispatcher;
use Twig\Environment;

class Dispatcher
{
    /**
     * Construtor do Dispatcher.
     * @param FastRouteDispatcher $fastRoute FastRouteDispatcher.
     * @param array $container Container.
     * @return void
     */
    public function __construct(
        private FastRouteDispatcher $fastRoute,
        private array $container
    ) {
    }
    /**
     * Rota a requisição para o controller apropriado.
     * @param string $method Método da requisição.
     * @param string $uri URI da requisição.
     * @return void
     */
    public function handle(string $method, string $uri): void
    {
        $routeInfo = $this->fastRoute->dispatch($method, $uri);

        switch ($routeInfo[0]) {
            case FastRouteDispatcher::NOT_FOUND:
                $this->renderError(404);
                return;
            case FastRouteDispatcher::METHOD_NOT_ALLOWED:
                $this->renderError(405);
                return;
            case FastRouteDispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                $this->invoke($handler, $vars);
                return;
        }
    }

    /**
     * Invoca o controller apropriado.
     * @param string $handler Handler da rota.
     * @param array $vars Variáveis da rota.
     * @return void
     */
    private function invoke(string $handler, array $vars): void
    {
        $authRequired = false;
        $permission = null;

        if (str_starts_with($handler, 'auth:')) {
            $authRequired = true;
            $handler = substr($handler, 5);
        }
        if (str_contains($handler, ':')) {
            $parts = explode(':', $handler, 2);
            $handler = $parts[0];
            $permission = $parts[1] ?? null;
        }

        if ($authRequired) {
            $authMiddleware = $this->container['middlewares']['auth'] ?? null;
            if ($authMiddleware && !$authMiddleware($this->container)) {
                return;
            }
        }
        if ($permission !== null) {
            $permissionMiddleware = $this->container['middlewares']['permission'] ?? null;
            if ($permissionMiddleware && !$permissionMiddleware($this->container, $permission)) {
                return;
            }
        }

        [$controllerName, $methodName] = explode('@', $handler, 2);
        $controllerClass = 'App\\Controllers\\' . $controllerName;
        if (!class_exists($controllerClass) || !method_exists($controllerClass, $methodName)) {
            $this->renderError(500);
            return;
        }
        $controller = new $controllerClass($this->container);
        $controller->{$methodName}(...array_values($vars));
    }

    /**
     * Renderiza a página de erro.
     * @param int $code Código do erro.
     * @param string $message Mensagem do erro.
     * @return void
     */
    private function renderError(int $code, string $message = ''): void
    {
        $twig = $this->container['twig'] ?? null;
        if ($twig instanceof Environment) {
            render_error($twig, $code, $message);
        } else {
            http_response_code($code);
            $defaults = [403 => '403 Forbidden', 404 => '404 Not Found', 405 => '405 Method Not Allowed', 500 => '500 Internal Server Error'];
            echo $defaults[$code] ?? (string) $code;
        }
    }
}
