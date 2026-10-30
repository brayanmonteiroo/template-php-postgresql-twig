<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Role;

class UserService
{
    public const MIN_PASSWORD_LENGTH = 8;

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

    public function getRoleIdsByUserId(int $id): array
    {
        return array_column($this->userModel->getRolesByUserId($id), 'id');
    }

    public function createUser(array $data): array
    {
        $errors = $this->validateUserData($data, null);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        unset($data['password']);
        $data['status'] = $data['status'] ?? 'active';
        $roleIds = $data['role_ids'] ?? [];
        unset($data['role_ids']);
        $id = $this->userModel->create($data);
        if (!empty($roleIds)) {
            $this->userModel->syncRoles($id, array_map('intval', $roleIds));
        }
        return ['success' => true, 'id' => $id];
    }

    /**
     * Registro público: cria usuário ativo com perfil Visualizador (viewer).
     */
    public function registerViewer(array $data): array
    {
        $viewer = $this->roleModel->findBySlug('viewer');
        if ($viewer === null) {
            if (\function_exists('app_log')) {
                \app_log('error', 'Registro falhou: perfil viewer não encontrado');
            }
            return [
                'success' => false,
                'errors' => [
                    '_form' => 'Não foi possível concluir o registro. Contate o administrador.',
                ],
            ];
        }
        $data['status'] = 'active';
        $data['role_ids'] = [(int) $viewer['id']];
        return $this->createUser($data);
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
            $update['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
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
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        } else {
            $existing = $this->userModel->findByEmail($email);
            if ($existing && (int) $existing['id'] !== (int) $excludeUserId) {
                $errors['email'] = 'Este email já está em uso.';
            }
        }

        $password = (string) ($data['password'] ?? '');
        if ($excludeUserId === null) {
            if ($password === '') {
                $errors['password'] = 'Senha é obrigatória.';
            } elseif (strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $errors['password'] = 'A senha deve ter pelo menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.';
            }
        } elseif ($password !== '' && strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'A senha deve ter pelo menos ' . self::MIN_PASSWORD_LENGTH . ' caracteres.';
        }

        return $errors;
    }
}
