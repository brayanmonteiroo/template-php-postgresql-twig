<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;

class ProfileController
{
    public function __construct(
        private array $container
    ) {
    }

    private function userService(): UserService
    {
        return $this->container['userService'];
    }

    public function edit(): void
    {
        $user = $this->container['authService']->user();
        if ($user === null) {
            redirect('/login');
            return;
        }
        $full = $this->userService()->getUserById((int) $user['id']);
        if ($full === null) {
            redirect('/login');
            return;
        }
        unset($full['password_hash']);
        $full['role_ids'] = array_column($this->container['userModel']->getRolesByUserId((int) $full['id']), 'id');
        $twig = $this->container['twig'];
        echo $twig->render('profile/edit.twig', [
            'user' => $full,
            'roles' => $this->userService()->getRolesForSelect(),
            'errors' => [],
        ]);
    }

    public function update(): void
    {
        if (!validate_csrf()) {
            return;
        }
        $user = $this->container['authService']->user();
        if ($user === null) {
            redirect('/login');
            return;
        }
        $id = (int) $user['id'];
        $full = $this->userService()->getUserById($id);
        if ($full === null) {
            redirect('/login');
            return;
        }
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'status' => $full['status'],
            'role_ids' => array_column($this->container['userModel']->getRolesByUserId($id), 'id'),
        ];
        $result = $this->userService()->updateUser($id, $data);
        if ($result['success']) {
            $_SESSION['user_data'] = [
                'id' => $id,
                'name' => $data['name'],
                'email' => $data['email'],
            ];
            flash_set('success', 'Perfil atualizado com sucesso.');
            redirect('/profile');
            return;
        }
        $full = $this->userService()->getUserById($id);
        if ($full) {
            unset($full['password_hash']);
            $full['role_ids'] = $data['role_ids'];
        }
        $twig = $this->container['twig'];
        echo $twig->render('profile/edit.twig', [
            'user' => $full ?? array_merge($data, ['id' => $id]),
            'roles' => $this->userService()->getRolesForSelect(),
            'errors' => $result['errors'],
        ]);
    }
}
