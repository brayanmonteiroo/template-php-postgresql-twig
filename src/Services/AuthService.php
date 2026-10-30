<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class AuthService
{
    private const SESSION_USER_KEY = 'user_id';
    private const SESSION_USER_DATA = 'user_data';

    private static ?string $dummyPasswordHash = null;

    public function __construct(
        private User $userModel
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $user = $this->userModel->findByEmail($email);
        $hash = is_array($user) ? (string) ($user['password_hash'] ?? '') : $this->dummyPasswordHash();
        if ($hash === '') {
            $hash = $this->dummyPasswordHash();
        }
        $passwordOk = password_verify($password, $hash);

        if ($user === null || ($user['status'] ?? '') !== 'active' || !$passwordOk) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->userModel->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
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
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }
            session_destroy();
        }
    }

    public function refreshSessionUser(array $data): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::SESSION_USER_KEY] = (int) $data['id'];
        $_SESSION[self::SESSION_USER_DATA] = [
            'id' => (int) $data['id'],
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
        ];
    }

    public function user(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = (int) ($_SESSION[self::SESSION_USER_KEY]
            ?? ($_SESSION[self::SESSION_USER_DATA]['id'] ?? 0));
        if ($userId <= 0) {
            return null;
        }

        $dbUser = $this->userModel->findById($userId);
        if ($dbUser === null || ($dbUser['status'] ?? '') !== 'active') {
            $this->clearSessionIdentity();
            return null;
        }

        $data = [
            'id' => (int) $dbUser['id'],
            'name' => (string) $dbUser['name'],
            'email' => (string) $dbUser['email'],
        ];
        $_SESSION[self::SESSION_USER_KEY] = $data['id'];
        $_SESSION[self::SESSION_USER_DATA] = $data;

        return $data;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    private function clearSessionIdentity(): void
    {
        unset($_SESSION[self::SESSION_USER_KEY], $_SESSION[self::SESSION_USER_DATA]);
    }

    private function dummyPasswordHash(): string
    {
        if (self::$dummyPasswordHash === null) {
            self::$dummyPasswordHash = password_hash('__timing_dummy__', PASSWORD_DEFAULT);
        }

        return self::$dummyPasswordHash;
    }
}
