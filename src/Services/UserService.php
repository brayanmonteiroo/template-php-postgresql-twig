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
