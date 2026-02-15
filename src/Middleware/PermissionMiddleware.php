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
            http_response_code(403);
            echo '403 Forbidden';
            return false;
        }
        if (!$permissionService->hasPermission($permission)) {
            http_response_code(403);
            echo '403 Forbidden';
            return false;
        }
        return true;
    }
}
