<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;

class RoleService
{
    public function __construct(
        private Role $roleModel,
        private Permission $permissionModel
    ) {
    }

    public function listRoles(): array
    {
        return $this->roleModel->listAll();
    }

    public function getRoleById(int $id): ?array
    {
        return $this->roleModel->findById($id);
    }

    public function getPermissionsForSelect(): array
    {
        return $this->permissionModel->listAll();
    }

    /**
     * Permissões agrupadas na ordem da sidebar: Painel → Usuários → Perfis.
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public function getPermissionsGroupedForSelect(): array
    {
        $groups = [
            [
                'key' => 'painel',
                'label' => 'Painel',
                'prefixes' => ['dashboard.'],
                'items' => [],
            ],
            [
                'key' => 'usuarios',
                'label' => 'Usuários',
                'prefixes' => ['user.'],
                'items' => [],
            ],
            [
                'key' => 'perfis',
                'label' => 'Perfis',
                'prefixes' => ['role.', 'permission.'],
                'items' => [],
            ],
        ];

        $actionOrder = ['view' => 1, 'create' => 2, 'edit' => 3, 'delete' => 4, 'manage' => 5];

        foreach ($this->permissionModel->listAll() as $perm) {
            $slug = (string) ($perm['slug'] ?? '');
            foreach ($groups as &$group) {
                foreach ($group['prefixes'] as $prefix) {
                    if (str_starts_with($slug, $prefix)) {
                        $group['items'][] = $perm;
                        continue 3;
                    }
                }
            }
            unset($group);
            // Fallback: última seção (Perfis)
            $groups[array_key_last($groups)]['items'][] = $perm;
        }

        foreach ($groups as &$group) {
            usort($group['items'], static function (array $a, array $b) use ($actionOrder): int {
                $actionA = explode('.', (string) ($a['slug'] ?? ''))[1] ?? '';
                $actionB = explode('.', (string) ($b['slug'] ?? ''))[1] ?? '';
                $rankA = $actionOrder[$actionA] ?? 99;
                $rankB = $actionOrder[$actionB] ?? 99;
                if ($rankA !== $rankB) {
                    return $rankA <=> $rankB;
                }
                return strcmp((string) ($a['slug'] ?? ''), (string) ($b['slug'] ?? ''));
            });
            unset($group['prefixes']);
        }
        unset($group);

        return array_values(array_filter(
            $groups,
            static fn (array $g): bool => $g['items'] !== []
        ));
    }

    public function getPermissionIdsByRoleId(int $roleId): array
    {
        return $this->roleModel->getPermissionIdsByRoleId($roleId);
    }

    public function createRole(array $data): array
    {
        $errors = $this->validate($data, null);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $id = $this->roleModel->create([
            'name' => $data['name'],
            'slug' => $this->normalizeSlug($data['slug'] ?? $data['name']),
        ]);
        $permissionIds = array_map('intval', (array) ($data['permission_ids'] ?? []));
        if (!empty($permissionIds)) {
            $this->roleModel->syncPermissions($id, $permissionIds);
        }
        return ['success' => true, 'id' => $id];
    }

    public function updateRole(int $id, array $data): array
    {
        $role = $this->roleModel->findById($id);
        if ($role === null) {
            return ['success' => false, 'errors' => ['Perfil não encontrado.']];
        }
        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $this->roleModel->update($id, [
            'name' => $data['name'],
            'slug' => $this->normalizeSlug($data['slug'] ?? $data['name']),
        ]);
        if (array_key_exists('permission_ids', $data)) {
            $this->roleModel->syncPermissions($id, array_map('intval', (array) $data['permission_ids']));
        }
        return ['success' => true];
    }

    public function deleteRole(int $id): bool
    {
        $role = $this->roleModel->findById($id);
        if ($role !== null && ($role['slug'] ?? '') === 'admin') {
            return false;
        }
        return $this->roleModel->delete($id);
    }

    public function getMatrixData(): array
    {
        $roles = $this->roleModel->listAll();
        $permissions = $this->permissionModel->listAll();
        $matrix = [];
        foreach ($roles as $role) {
            $matrix[$role['id']] = $this->roleModel->getPermissionIdsByRoleId((int) $role['id']);
        }
        return ['roles' => $roles, 'permissions' => $permissions, 'matrix' => $matrix];
    }

    public function updateRolePermissions(int $roleId, array $permissionIds): bool
    {
        $role = $this->roleModel->findById($roleId);
        if ($role === null) {
            return false;
        }
        $this->roleModel->syncPermissions($roleId, array_map('intval', $permissionIds));
        return true;
    }

    private function validate(array $data, ?int $excludeId): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Nome é obrigatório.';
        }
        $slug = $this->normalizeSlug($data['slug'] ?? $name);
        if ($slug === '') {
            $errors['slug'] = 'Slug é obrigatório.';
        } elseif ($this->roleModel->slugExists($slug, $excludeId)) {
            $errors['slug'] = 'Este slug já está em uso.';
        }
        return $errors;
    }

    private function normalizeSlug(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value);
        return strtolower(trim($value, '_'));
    }
}
