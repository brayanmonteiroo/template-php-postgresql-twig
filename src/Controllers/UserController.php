<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;

class UserController
{
    public function __construct(
        private array $container
    ) {
    }

    private function userService(): UserService
    {
        return $this->container['userService'];
    }

    public function index(): void
    {
        $page = max(1, (int) input_get('page', 1));
        $perPage = max(1, min(100, (int) input_get('per_page', 10)));
        $search = (string) input_get('search', '');
        $status = (string) input_get('status', '');
        $sort = (string) input_get('sort', 'name');
        $order = strtolower((string) input_get('order', 'asc')) === 'desc' ? 'desc' : 'asc';
        $data = $this->userService()->listUsersPaginated($page, $perPage, $search, $status, $sort, $order);
        $twig = $this->container['twig'];
        echo $twig->render('users/index.twig', $data);
    }

    public function create(): void
    {
        $roles = $this->userService()->getRolesForSelect();
        $twig = $this->container['twig'];
        echo $twig->render('users/form.twig', [
            'user' => null,
            'roles' => $roles,
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $data = [
            'name' => (string) input_post('name', ''),
            'email' => (string) input_post('email', ''),
            'password' => (string) input_post('password', ''),
            'status' => (string) input_post('status', 'active'),
            'role_ids' => $_POST['role_ids'] ?? [],
        ];
        $result = $this->userService()->createUser($data);
        if ($result['success']) {
            flash_set('success', 'Usuário criado com sucesso.');
            redirect('/users');
            return;
        }
        $twig = $this->container['twig'];
        echo $twig->render('users/form.twig', [
            'user' => $data,
            'roles' => $this->userService()->getRolesForSelect(),
            'errors' => $result['errors'],
        ]);
    }

    public function show(int $id): void
    {
        $user = $this->userService()->getUserById($id);
        if ($user === null) {
            render_error($this->container['twig'], 404);
            return;
        }
        unset($user['password_hash']);
        $twig = $this->container['twig'];
        echo $twig->render('users/show.twig', ['user' => $user]);
    }

    public function edit(int $id): void
    {
        $user = $this->userService()->getUserById($id);
        if ($user === null) {
            render_error($this->container['twig'], 404);
            return;
        }
        unset($user['password_hash']);
        $user['role_ids'] = array_column($this->container['userModel']->getRolesByUserId($id), 'id');
        $twig = $this->container['twig'];
        echo $twig->render('users/form.twig', [
            'user' => $user,
            'roles' => $this->userService()->getRolesForSelect(),
            'errors' => [],
        ]);
    }

    public function update(int $id): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $data = [
            'name' => (string) input_post('name', ''),
            'email' => (string) input_post('email', ''),
            'password' => (string) input_post('password', ''),
            'status' => (string) input_post('status', 'active'),
            'role_ids' => $_POST['role_ids'] ?? [],
        ];
        $result = $this->userService()->updateUser($id, $data);
        if ($result['success']) {
            flash_set('success', 'Usuário atualizado com sucesso.');
            redirect('/users');
            return;
        }
        $user = $this->userService()->getUserById($id);
        if ($user) {
            unset($user['password_hash']);
            $user['role_ids'] = $data['role_ids'];
        }
        $twig = $this->container['twig'];
        echo $twig->render('users/form.twig', [
            'user' => $user ?? array_merge($data, ['id' => $id]),
            'roles' => $this->userService()->getRolesForSelect(),
            'errors' => $result['errors'],
        ]);
    }

    public function destroy(int $id): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $authUser = $this->container['authService']->user();
        if ($authUser && (int) $authUser['id'] === (int) $id) {
            flash_set('error', 'Você não pode excluir sua própria conta.');
            redirect('/users');
            return;
        }
        $result = $this->userService()->deleteUser($id);
        if ($result) {
            flash_set('success', 'Usuário excluído com sucesso.');
        } else {
            flash_set('error', 'Não é possível excluir o último administrador.');
        }
        redirect('/users');
    }
}
