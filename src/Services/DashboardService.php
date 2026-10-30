<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class DashboardService
{
    public function __construct(
        private User $userModel,
        private Role $roleModel,
        private Permission $permissionModel
    ) {
    }

    public function getTotals(): array
    {
        return [
            'total_users' => $this->userModel->countAll('', ''),
            'total_roles' => count($this->roleModel->listAll()),
            'total_permissions' => count($this->permissionModel->listAll()),
        ];
    }
}
