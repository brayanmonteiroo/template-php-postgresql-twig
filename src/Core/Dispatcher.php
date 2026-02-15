<?php

namespace App\Core;

use FastRoute\Dispatcher as FastRouteDispatcher;

class Dispatcher
{
    public function __construct(
        private FastRouteDispatcher $fastRoute,
        private array $container
    ) {
    }

    public function handle(string $method, string $uri): void
    {
        $routeInfo = $this->fastRoute->dispatch($method, $uri);

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                http_response_code(404);
                echo '404 Not Found';
                return;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                http_response_code(405);
                echo '405 Method Not Allowed';
                return;
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                $this->invoke($handler, $vars);
                return;
        }
    }

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
            http_response_code(500);
            echo '500 Internal Server Error';
            return;
        }
        $controller = new $controllerClass($this->container);
        $controller->{$methodName}(...array_values($vars));
    }
}
