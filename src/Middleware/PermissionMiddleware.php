<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\PermissionService;

class PermissionMiddleware
{
    public static function run(array $container, string $permission): bool
    {
        $permissionService = $container['permissionService'] ?? null;
        if (!$permissionService instanceof PermissionService) {
            self::send403($container);
            return false;
        }
        if (!$permissionService->hasPermission($permission)) {
            self::send403($container);
            return false;
        }
        return true;
    }

    private static function send403(array $container): void
    {
        $twig = $container['twig'] ?? null;
        if ($twig instanceof \Twig\Environment) {
            render_error($twig, 403, 'Acesso negado.');
        } else {
            http_response_code(403);
            echo '403 Forbidden';
        }
    }
}
