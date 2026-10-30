<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\UserService;

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

    private function userService(): UserService
    {
        return $this->container['userService'];
    }

    public function showLogin(): void
    {
        if ($this->authService()->check()) {
            redirect($this->homePath());
        }
        $twig = $this->container['twig'];
        echo $twig->render('auth/login.twig', [
            'error' => null,
            'email' => '',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function login(): void
    {
        if ($this->authService()->check()) {
            redirect($this->homePath());
        }
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        if (!rate_limit_login_ok()) {
            $minutes = rate_limit_login_retry_after_minutes();
            $twig = $this->container['twig'];
            http_response_code(429);
            echo $twig->render('auth/login.twig', [
                'error' => 'Muitas tentativas de login. Tente novamente em ' . $minutes . ' minuto(s).',
                'email' => (string) input_post('email', ''),
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
            return;
        }
        $email = (string) input_post('email', '');
        $password = (string) input_post('password', '');
        $error = null;
        if ($email === '' || $password === '') {
            $error = 'Email e senha são obrigatórios.';
        } elseif (!$this->authService()->login($email, $password)) {
            rate_limit_login_record();
            app_log('warning', 'Login failed', ['email' => $email, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
            $error = 'Credenciais inválidas.';
        }
        if ($error !== null) {
            $twig = $this->container['twig'];
            echo $twig->render('auth/login.twig', [
                'error' => $error,
                'email' => $email,
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
            return;
        }
        rotate_csrf_token();
        redirect($this->homePath());
    }

    public function showRegister(): void
    {
        if ($this->authService()->check()) {
            redirect($this->homePath());
        }
        $twig = $this->container['twig'];
        echo $twig->render('auth/register.twig', [
            'errors' => [],
            'name' => '',
            'email' => '',
            'csrf_token' => $_SESSION['csrf_token'] ?? '',
        ]);
    }

    public function register(): void
    {
        if ($this->authService()->check()) {
            redirect($this->homePath());
        }
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        if (!rate_limit_register_ok()) {
            $minutes = rate_limit_register_retry_after_minutes();
            $twig = $this->container['twig'];
            http_response_code(429);
            echo $twig->render('auth/register.twig', [
                'errors' => ['_form' => 'Muitas tentativas de registro. Tente novamente em ' . $minutes . ' minuto(s).'],
                'name' => (string) input_post('name', ''),
                'email' => (string) input_post('email', ''),
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
            return;
        }

        $data = [
            'name' => (string) input_post('name', ''),
            'email' => (string) input_post('email', ''),
            'password' => (string) input_post('password', ''),
        ];
        $result = $this->userService()->registerViewer($data);
        if (!$result['success']) {
            rate_limit_register_record();
            $twig = $this->container['twig'];
            echo $twig->render('auth/register.twig', [
                'errors' => $result['errors'],
                'name' => $data['name'],
                'email' => $data['email'],
                'csrf_token' => $_SESSION['csrf_token'] ?? '',
            ]);
            return;
        }

        if (!$this->authService()->login($data['email'], $data['password'])) {
            flash_set('success', 'Conta criada. Faça login para continuar.');
            redirect('/login');
            return;
        }
        rotate_csrf_token();
        flash_set('success', 'Conta criada com sucesso.');
        redirect($this->homePath());
    }

    public function logout(): void
    {
        if (!validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $this->authService()->logout();
        redirect('/login');
    }

    private function homePath(): string
    {
        $permissionService = $this->container['permissionService'] ?? null;
        if ($permissionService instanceof \App\Services\PermissionService) {
            return $permissionService->firstAccessiblePath();
        }

        return '/dashboard';
    }
}
