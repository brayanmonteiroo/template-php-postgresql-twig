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
                'csrf_token' => $this->container['csrf_token'] ?? '',
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
                'csrf_token' => $this->container['csrf_token'] ?? '',
            ]);
            return;
        }
        redirect('/dashboard');
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validate_csrf($this->container['twig'] ?? null)) {
            return;
        }
        $this->authService()->logout();
        redirect('/login');
    }
}
