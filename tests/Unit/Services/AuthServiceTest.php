<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private function createUserModelStub(?array $user = null): User
    {
        $stub = $this->createStub(User::class);
        $stub->method('findByEmail')->willReturn($user);
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
        $hash = password_hash('correct', PASSWORD_BCRYPT);
        $userModel = $this->createUserModelStub([
            'id' => 1,
            'email' => 'u@e.com',
            'password_hash' => $hash,
            'name' => 'User',
            'status' => 'active',
        ]);
        $auth = new AuthService($userModel);
        $this->assertFalse($auth->login('u@e.com', 'wrong'));
    }

    public function testLoginFailsWhenUserInactive(): void
    {
        $hash = password_hash('pass', PASSWORD_BCRYPT);
        $userModel = $this->createUserModelStub([
            'id' => 1,
            'email' => 'u@e.com',
            'password_hash' => $hash,
            'name' => 'User',
            'status' => 'inactive',
        ]);
        $auth = new AuthService($userModel);
        $this->assertFalse($auth->login('u@e.com', 'pass'));
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);
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
        $this->assertTrue($auth->login('valid@example.com', 'secret'));
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
}
