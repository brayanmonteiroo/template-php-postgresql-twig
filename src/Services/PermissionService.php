<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class PermissionService
{
    public function __construct(
        private User $userModel,
        private AuthService $authService
    ) {
    }

    public function hasPermission(string $permissionSlug): bool
    {
        $user = $this->authService->user();
        if ($user === null) {
            return false;
        }
        $slugs = $this->userModel->getPermissionSlugsByUserId((int) $user['id']);
        return in_array($permissionSlug, $slugs, true);
    }

    public function getUserPermissions(): array
    {
        $user = $this->authService->user();
        if ($user === null) {
            return [];
        }
        return $this->userModel->getPermissionSlugsByUserId((int) $user['id']);
    }

    /**
     * Primeira rota útil após login, respeitando as permissões do usuário.
     */
    public function firstAccessiblePath(): string
    {
        $candidates = [
            'dashboard.view' => '/dashboard',
            'user.view' => '/users',
            'role.view' => '/perfis',
            'permission.view' => '/perfis',
            'user.create' => '/users/create',
            'role.create' => '/perfis/create',
        ];
        foreach ($candidates as $slug => $path) {
            if ($this->hasPermission($slug)) {
                return $path;
            }
        }

        return '/profile';
    }
}
