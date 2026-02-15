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
        $users = $this->userService()->listUsers();
        $twig = $this->container['twig'];
        echo $twig->render('users/index.twig', ['users' => $users]);
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
        if (!validate_csrf()) {
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'role_ids' => $_POST['role_ids'] ?? [],
        ];
        $result = $this->userService()->createUser($data);
        if ($result['success']) {
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
            http_response_code(404);
            echo '404 Not Found';
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
            http_response_code(404);
            echo '404 Not Found';
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
        if (!validate_csrf()) {
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'status' => $_POST['status'] ?? 'active',
            'role_ids' => $_POST['role_ids'] ?? [],
        ];
        $result = $this->userService()->updateUser($id, $data);
        if ($result['success']) {
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
        if (!validate_csrf()) {
            return;
        }
        $this->userService()->deleteUser($id);
        redirect('/users');
    }
}
