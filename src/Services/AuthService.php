<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class AuthService
{
    private const SESSION_USER_KEY = 'user_id';
    private const SESSION_USER_DATA = 'user_data';

    public function __construct(
        private User $userModel
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->userModel->findByEmail($email);
        if ($user === null || $user['status'] !== 'active') {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_KEY] = (int) $user['id'];
        $_SESSION[self::SESSION_USER_DATA] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
        ];
        return true;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }

    public function user(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_USER_DATA])) {
            return null;
        }
        return $_SESSION[self::SESSION_USER_DATA];
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }
}
