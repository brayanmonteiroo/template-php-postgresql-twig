<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\RoleService;

class RoleController
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
        $roles = $this->roleService()->listRoles();
        $twig = $this->container['twig'];
        echo $twig->render('roles/index.twig', ['roles' => $roles]);
    }

    public function create(): void
    {
        $permissions = $this->roleService()->getPermissionsForSelect();
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => null,
            'permissions' => $permissions,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!validate_csrf()) {
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'permission_ids' => $_POST['permission_ids'] ?? [],
        ];
        $result = $this->roleService()->createRole($data);
        if ($result['success']) {
            flash_set('success', 'Papel criado com sucesso.');
            redirect('/roles');
            return;
        }
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => $data,
            'permissions' => $this->roleService()->getPermissionsForSelect(),
            'errors' => $result['errors'],
        ]);
    }

    public function edit(int $id): void
    {
        $role = $this->roleService()->getRoleById($id);
        if ($role === null) {
            http_response_code(404);
            echo '404 Not Found';
            return;
        }
        $role['permission_ids'] = $this->roleService()->getPermissionIdsByRoleId($id);
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => $role,
            'permissions' => $this->roleService()->getPermissionsForSelect(),
            'errors' => [],
        ]);
    }

    public function update(int $id): void
    {
        if (!validate_csrf()) {
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'permission_ids' => $_POST['permission_ids'] ?? [],
        ];
        $result = $this->roleService()->updateRole($id, $data);
        if ($result['success']) {
            flash_set('success', 'Papel atualizado com sucesso.');
            redirect('/roles');
            return;
        }
        $role = $this->roleService()->getRoleById($id);
        if ($role) {
            $role['permission_ids'] = $data['permission_ids'];
        }
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => $role ?? array_merge($data, ['id' => $id]),
            'permissions' => $this->roleService()->getPermissionsForSelect(),
            'errors' => $result['errors'],
        ]);
    }

    public function destroy(int $id): void
    {
        if (!validate_csrf()) {
            return;
        }
        $result = $this->roleService()->deleteRole($id);
        if ($result) {
            flash_set('success', 'Papel excluído com sucesso.');
        } else {
            flash_set('error', 'O papel Administrador não pode ser excluído.');
        }
        redirect('/roles');
    }
}
