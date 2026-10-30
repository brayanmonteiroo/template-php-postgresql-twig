<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private function createUserModelStub(?array $byEmail = null, ?array $byId = null): User
    {
        $stub = $this->createStub(User::class);
        $stub->method('findByEmail')->willReturn($byEmail);
        $stub->method('findById')->willReturn($byId ?? $byEmail);
        return $stub;
    }

    public function testLoginFailsWhenUserNotFound(): void
    {
        $userModel = $this->createUserModelStub(null);
        $auth = new AuthService($userModel);
        $this->assertFalse($auth->login('nonexistent@example.com', 'any'));
    }

    public function testLoginFailsWhenPasswordInvalid(): void
    {
        $hash = password_hash('correct12', PASSWORD_DEFAULT);
        $userModel = $this->createUserModelStub([
            'id' => 1,
            'email' => 'u@e.com',
            'password_hash' => $hash,
            'name' => 'User',
            'status' => 'active',
        ]);
        $auth = new AuthService($userModel);
        $this->assertFalse($auth->login('u@e.com', 'wrongpass'));
    }

    public function testLoginFailsWhenUserInactive(): void
    {
        $hash = password_hash('password1', PASSWORD_DEFAULT);
        $userModel = $this->createUserModelStub([
            'id' => 1,
            'email' => 'u@e.com',
            'password_hash' => $hash,
            'name' => 'User',
            'status' => 'inactive',
        ]);
        $auth = new AuthService($userModel);
        $this->assertFalse($auth->login('u@e.com', 'password1'));
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $hash = password_hash('secret12', PASSWORD_DEFAULT);
        $userModel = $this->createUserModelStub([
            'id' => 1,
            'email' => 'valid@example.com',
            'password_hash' => $hash,
            'name' => 'Valid User',
            'status' => 'active',
        ]);
        $auth = new AuthService($userModel);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $this->assertTrue($auth->login('valid@example.com', 'secret12'));
        $this->assertNotEmpty($_SESSION);
        $this->assertArrayHasKey('user_id', $_SESSION);
    }

    public function testUserReturnsNullWhenNotLoggedIn(): void
    {
        $userModel = $this->createUserModelStub();
        $auth = new AuthService($userModel);
        $_SESSION = [];
        $this->assertNull($auth->user());
    }

    public function testCheckReturnsFalseWhenNotLoggedIn(): void
    {
        $userModel = $this->createUserModelStub();
        $auth = new AuthService($userModel);
        $_SESSION = [];
        $this->assertFalse($auth->check());
    }

    public function testUserClearsSessionWhenDbUserInactive(): void
    {
        $userModel = $this->createStub(User::class);
        $userModel->method('findById')->willReturn([
            'id' => 1,
            'name' => 'X',
            'email' => 'x@y.com',
            'status' => 'inactive',
            'password_hash' => 'x',
        ]);
        $auth = new AuthService($userModel);
        $_SESSION = [
            'user_id' => 1,
            'user_data' => ['id' => 1, 'name' => 'X', 'email' => 'x@y.com'],
        ];
        $this->assertNull($auth->user());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testUserRefreshesFromDatabaseWhenActive(): void
    {
        $userModel = $this->createStub(User::class);
        $userModel->method('findById')->willReturn([
            'id' => 2,
            'name' => 'Atualizado',
            'email' => 'novo@example.com',
            'status' => 'active',
            'password_hash' => 'x',
        ]);
        $auth = new AuthService($userModel);
        $_SESSION = [
            'user_id' => 2,
            'user_data' => ['id' => 2, 'name' => 'Antigo', 'email' => 'old@example.com'],
        ];
        $user = $auth->user();
        $this->assertNotNull($user);
        $this->assertSame('Atualizado', $user['name']);
        $this->assertSame('novo@example.com', $user['email']);
    }
}
