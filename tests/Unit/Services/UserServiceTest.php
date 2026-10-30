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
            'password' => 'secret12',
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

    public function testCreateUserReturnsErrorsWhenPasswordTooShort(): void
    {
        $userModel = $this->createUserModelStub();
        $roleModel = $this->createRoleModelStub();
        $service = new UserService($userModel, $roleModel);
        $result = $service->createUser([
            'name' => 'Test',
            'email' => 'a@b.com',
            'password' => 'short',
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
            'password' => 'secret12',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function testRegisterViewerFailsWhenViewerRoleMissing(): void
    {
        $userModel = $this->createUserModelStub();
        $roleModel = $this->createRoleModelStub();
        $roleModel->method('findBySlug')->with('viewer')->willReturn(null);
        $service = new UserService($userModel, $roleModel);
        $result = $service->registerViewer([
            'name' => 'Novo',
            'email' => 'novo@example.com',
            'password' => 'secret12',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('_form', $result['errors']);
    }

    public function testRegisterViewerAssignsViewerRole(): void
    {
        $userModel = $this->createMock(User::class);
        $userModel->method('findByEmail')->willReturn(null);
        $userModel->expects($this->once())->method('create')->willReturn(10);
        $userModel->expects($this->once())->method('syncRoles')->with(10, [3]);

        $roleModel = $this->createStub(Role::class);
        $roleModel->method('findBySlug')->with('viewer')->willReturn(['id' => 3, 'slug' => 'viewer']);

        $service = new UserService($userModel, $roleModel);
        $result = $service->registerViewer([
            'name' => 'Novo',
            'email' => 'novo@example.com',
            'password' => 'secret12',
        ]);
        $this->assertTrue($result['success']);
        $this->assertSame(10, $result['id']);
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
