<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use App\Services\PermissionService;
use PHPUnit\Framework\TestCase;

class PermissionServiceTest extends TestCase
{
    private function createAuthServiceStub(?array $user = null): AuthService
    {
        $stub = $this->createStub(AuthService::class);
        $stub->method('user')->willReturn($user);
        return $stub;
    }

    private function createUserModelStub(array $permissions): User
    {
        $stub = $this->createStub(User::class);
        $stub->method('getPermissionSlugsByUserId')->willReturn($permissions);
        return $stub;
    }

    public function testHasPermissionReturnsFalseWhenUserNotLoggedIn(): void
    {
        $auth = $this->createAuthServiceStub(null);
        $userModel = $this->createUserModelStub(['user.view']);
        $permission = new PermissionService($userModel, $auth);
        $this->assertFalse($permission->hasPermission('user.view'));
    }

    public function testHasPermissionReturnsFalseWhenUserDoesNotHavePermission(): void
    {
        $auth = $this->createAuthServiceStub(['id' => 1, 'name' => 'Test']);
        $userModel = $this->createUserModelStub(['user.view']);
        $userModel->method('getPermissionSlugsByUserId')->with(1)->willReturn(['user.view']);
        $permission = new PermissionService($userModel, $auth);
        $this->assertFalse($permission->hasPermission('user.edit'));
    }

    public function testHasPermissionReturnsTrueWhenUserHasPermission(): void
    {
        $auth = $this->createAuthServiceStub(['id' => 1, 'name' => 'Test']);
        $userModel = $this->createUserModelStub(['user.view', 'user.edit']);
        $userModel->method('getPermissionSlugsByUserId')->with(1)->willReturn(['user.view', 'user.edit']);
        $permission = new PermissionService($userModel, $auth);
        $this->assertTrue($permission->hasPermission('user.edit'));
    }

    public function testGetUserPermissionsReturnsEmptyWhenNotLoggedIn(): void
    {
        $auth = $this->createAuthServiceStub(null);
        $userModel = $this->createUserModelStub([]);
        $permission = new PermissionService($userModel, $auth);
        $this->assertSame([], $permission->getUserPermissions());
    }

    public function testGetUserPermissionsReturnsListWhenLoggedIn(): void
    {
        $auth = $this->createAuthServiceStub(['id' => 1]);
        $userModel = $this->createUserModelStub(['user.view', 'user.create']);
        $userModel->method('getPermissionSlugsByUserId')->with(1)->willReturn(['user.view', 'user.create']);
        $permission = new PermissionService($userModel, $auth);
        $this->assertSame(['user.view', 'user.create'], $permission->getUserPermissions());
    }
}
