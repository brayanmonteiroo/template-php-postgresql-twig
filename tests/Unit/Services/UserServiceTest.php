<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Role;
use App\Services\UserService;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private function createUserModelStub(): User
    {
        return $this->createStub(User::class);
    }

    private function createRoleModelStub(): Role
    {
        return $this->createStub(Role::class);
    }

    public function testCreateUserReturnsErrorsWhenNameEmpty(): void
    {
        $userModel = $this->createUserModelStub();
        $roleModel = $this->createRoleModelStub();
        $service = new UserService($userModel, $roleModel);
        $result = $service->createUser([
            'name' => '',
            'email' => 'a@b.com',
            'password' => 'secret',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function testCreateUserReturnsErrorsWhenPasswordEmpty(): void
    {
        $userModel = $this->createUserModelStub();
        $roleModel = $this->createRoleModelStub();
        $service = new UserService($userModel, $roleModel);
        $result = $service->createUser([
            'name' => 'Test',
            'email' => 'a@b.com',
            'password' => '',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function testCreateUserReturnsErrorsWhenEmailEmpty(): void
    {
        $userModel = $this->createUserModelStub();
        $roleModel = $this->createRoleModelStub();
        $service = new UserService($userModel, $roleModel);
        $result = $service->createUser([
            'name' => 'Test',
            'email' => '',
            'password' => 'secret',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testListUsersPaginatedReturnsStructure(): void
    {
        $userModel = $this->createUserModelStub();
        $userModel->method('listPaginated')->willReturn([]);
        $userModel->method('countAll')->willReturn(0);
        $roleModel = $this->createRoleModelStub();
        $service = new UserService($userModel, $roleModel);
        $result = $service->listUsersPaginated(1, 15, '', '', 'name', 'asc');
        $this->assertArrayHasKey('users', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(15, $result['per_page']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['pages']);
    }
}
