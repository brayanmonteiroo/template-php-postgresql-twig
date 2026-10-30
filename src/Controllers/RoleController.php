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
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => null,
            // Não usar a chave "permissions": sobrescreve o global Twig da sidebar.
            'permission_groups' => $this->roleService()->getPermissionsGroupedForSelect(),
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'permission_ids' => $_POST['permission_ids'] ?? [],
        ];
        $result = $this->roleService()->createRole($data);
        if ($result['success']) {
            flash_set('success', 'Perfil criado com sucesso.');
            redirect('/perfis');
            return;
        }
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => $data,
            'permission_groups' => $this->roleService()->getPermissionsGroupedForSelect(),
            'errors' => $result['errors'],
        ]);
    }

    public function edit(int $id): void
    {
        $role = $this->roleService()->getRoleById($id);
        if ($role === null) {
            render_error($this->container['twig'], 404);
            return;
        }
        $role['permission_ids'] = $this->roleService()->getPermissionIdsByRoleId($id);
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => $role,
            'permission_groups' => $this->roleService()->getPermissionsGroupedForSelect(),
            'errors' => [],
        ]);
    }

    public function update(int $id): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? ''),
            'permission_ids' => $_POST['permission_ids'] ?? [],
        ];
        $result = $this->roleService()->updateRole($id, $data);
        if ($result['success']) {
            flash_set('success', 'Perfil atualizado com sucesso.');
            redirect('/perfis');
            return;
        }
        $role = $this->roleService()->getRoleById($id);
        if ($role) {
            $role['permission_ids'] = $data['permission_ids'];
        }
        $twig = $this->container['twig'];
        echo $twig->render('roles/form.twig', [
            'role' => $role ?? array_merge($data, ['id' => $id]),
            'permission_groups' => $this->roleService()->getPermissionsGroupedForSelect(),
            'errors' => $result['errors'],
        ]);
    }

    public function destroy(int $id): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $result = $this->roleService()->deleteRole($id);
        if ($result) {
            flash_set('success', 'Perfil excluído com sucesso.');
        } else {
            flash_set('error', 'O perfil Administrador não pode ser excluído.');
        }
        redirect('/perfis');
    }
}
