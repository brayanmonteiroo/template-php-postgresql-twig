<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;

class AuthController
{
    public function __construct(
        private array $container
    ) {
    }

    private function authService(): AuthService
    {
        return $this->container['authService'];
    }

    public function showLogin(): void
    {
        if ($this->authService()->check()) {
            redirect('/dashboard');
        }
        $twig = $this->container['twig'];
        echo $twig->render('auth/login.twig', [
            'error' => null,
            'csrf_token' => $this->container['csrf_token'] ?? '',
        ]);
    }

    public function login(): void
    {
        if ($this->authService()->check()) {
            redirect('/dashboard');
        }
        if (!validate_csrf()) {
            return;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $error = null;
        if ($email === '' || $password === '') {
            $error = 'Email e senha são obrigatórios.';
        } elseif (!$this->authService()->login($email, $password)) {
            $error = 'Credenciais inválidas.';
        }
        if ($error !== null) {
            $twig = $this->container['twig'];
            echo $twig->render('auth/login.twig', [
                'error' => $error,
                'email' => $email,
                'csrf_token' => $this->container['csrf_token'] ?? '',
            ]);
            return;
        }
        redirect('/dashboard');
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf()) {
            return;
        }
        $this->authService()->logout();
        redirect('/login');
    }
}
