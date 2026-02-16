<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RoleService;

class PermissionController
{
    public function __construct(
        private array $container
    ) {
    }

    private function roleService(): RoleService
    {
        return $this->container['roleService'];
    }

    public function index(): void
    {
        $data = $this->roleService()->getMatrixData();
        $data['permission_list'] = $data['permissions'];
        unset($data['permissions']);
        $twig = $this->container['twig'];
        echo $twig->render('permissions/index.twig', $data);
    }

    public function update(): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $permissionIdsByRole = $_POST['permission_ids'] ?? [];
        foreach ($permissionIdsByRole as $roleId => $ids) {
            $roleId = (int) $roleId;
            $ids = is_array($ids) ? array_map('intval', $ids) : [];
            $this->roleService()->updateRolePermissions($roleId, $ids);
        }
        flash_set('success', 'Permissões atualizadas com sucesso.');
        redirect('/permissions');
    }
}
