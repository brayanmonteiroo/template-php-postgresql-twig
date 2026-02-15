<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Role;

class UserService
{
    public function __construct(
        private User $userModel,
        private Role $roleModel
    ) {
    }

    public function listUsers(): array
    {
        return $this->userModel->listAll();
    }

    public function listUsersPaginated(int $page = 1, int $perPage = 15, string $search = '', string $status = '', string $sort = 'name', string $order = 'asc'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $users = $this->userModel->listPaginated($offset, $perPage, $search, $status, $sort, $order);
        $total = $this->userModel->countAll($search, $status);
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        return [
            'users' => $users,
            'total' => $total,
            'pages' => $pages,
            'current_page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'order' => $order,
        ];
    }

    public function getUserById(int $id): ?array
    {
        return $this->userModel->findById($id);
    }

    public function getRolesForSelect(): array
    {
        return $this->roleModel->listAll();
    }

    public function createUser(array $data): array
    {
        $errors = $this->validateUserData($data, null);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        unset($data['password']);
        $data['status'] = $data['status'] ?? 'active';
        $id = $this->userModel->create($data);
        $roleIds = $data['role_ids'] ?? [];
        if (!empty($roleIds)) {
            $this->userModel->syncRoles($id, array_map('intval', $roleIds));
        }
        return ['success' => true, 'id' => $id];
    }

    public function updateUser(int $id, array $data): array
    {
        $user = $this->userModel->findById($id);
        if ($user === null) {
            return ['success' => false, 'errors' => ['Usuário não encontrado.']];
        }
        $errors = $this->validateUserData($data, $id);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $update = [];
        if (isset($data['name'])) {
            $update['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $update['email'] = $data['email'];
        }
        if (!empty($data['password'])) {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        if (isset($data['status'])) {
            $update['status'] = $data['status'];
        }
        if (!empty($update)) {
            $this->userModel->update($id, $update);
        }
        if (array_key_exists('role_ids', $data)) {
            $this->userModel->syncRoles($id, array_map('intval', (array) $data['role_ids']));
        }
        return ['success' => true];
    }

    public function deleteUser(int $id): bool
    {
        $adminRole = $this->roleModel->findBySlug('admin');
        if ($adminRole !== null) {
            $userRoles = $this->userModel->getRolesByUserId($id);
            $hasAdmin = false;
            foreach ($userRoles as $r) {
                if ($r['slug'] === 'admin') {
                    $hasAdmin = true;
                    break;
                }
            }
            if ($hasAdmin && $this->userModel->countUsersWithRoleId((int) $adminRole['id']) <= 1) {
                return false;
            }
        }
        return $this->userModel->delete($id);
    }

    private function validateUserData(array $data, ?int $excludeUserId): array
    {
        $errors = [];
        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Nome é obrigatório.';
        }
        $email = trim($data['email'] ?? '');
        if ($email === '') {
            $errors['email'] = 'Email é obrigatório.';
        } else {
            $existing = $this->userModel->findByEmail($email);
            if ($existing && (int) $existing['id'] !== (int) $excludeUserId) {
                $errors['email'] = 'Este email já está em uso.';
            }
        }
        if ($excludeUserId === null && empty($data['password'] ?? '')) {
            $errors['password'] = 'Senha é obrigatória.';
        }
        return $errors;
    }
}
