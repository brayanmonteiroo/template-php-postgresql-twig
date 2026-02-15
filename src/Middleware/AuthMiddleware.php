<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware
{
    public static function run(array $container): bool
    {
        $auth = $container['authService'] ?? null;
        if (!$auth instanceof AuthService) {
            redirect('/login');
            return false;
        }
        if (!$auth->check()) {
            redirect('/login');
            return false;
        }
        return true;
    }
}
