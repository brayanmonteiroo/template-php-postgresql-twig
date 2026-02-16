<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\Permission;
use App\Services\RoleService;
use PHPUnit\Framework\TestCase;

class RoleServiceTest extends TestCase
{
    private function createRoleModelStub(): Role
    {
        return $this->createStub(Role::class);
    }

    private function createPermissionModelStub(): Permission
    {
        return $this->createStub(Permission::class);
    }

    public function testCreateRoleReturnsErrorsWhenNameEmpty(): void
    {
        $roleModel = $this->createRoleModelStub();
        $permissionModel = $this->createPermissionModelStub();
        $service = new RoleService($roleModel, $permissionModel);
        $result = $service->createRole([
            'name' => '',
            'slug' => 'editor',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function testCreateRoleReturnsErrorsWhenSlugEmpty(): void
    {
        $roleModel = $this->createRoleModelStub();
        $permissionModel = $this->createPermissionModelStub();
        $service = new RoleService($roleModel, $permissionModel);
        $result = $service->createRole([
            'name' => 'Editor',
            'slug' => '',
        ]);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('slug', $result['errors']);
    }

    public function testDeleteRoleReturnsFalseForAdminSlug(): void
    {
        $roleModel = $this->createRoleModelStub();
        $roleModel->method('findById')->willReturn(['id' => 1, 'name' => 'Administrador', 'slug' => 'admin']);
        $roleModel->method('delete')->willReturn(true);
        $permissionModel = $this->createPermissionModelStub();
        $service = new RoleService($roleModel, $permissionModel);
        $result = $service->deleteRole(1);
        $this->assertFalse($result);
    }

    public function testListRolesReturnsArray(): void
    {
        $roleModel = $this->createRoleModelStub();
        $roleModel->method('listAll')->willReturn([['id' => 1, 'name' => 'Admin', 'slug' => 'admin']]);
        $permissionModel = $this->createPermissionModelStub();
        $service = new RoleService($roleModel, $permissionModel);
        $roles = $service->listRoles();
        $this->assertIsArray($roles);
        $this->assertCount(1, $roles);
        $this->assertSame('admin', $roles[0]['slug']);
    }

    public function testUpdateRoleReturnsErrorWhenRoleNotFound(): void
    {
        $roleModel = $this->createRoleModelStub();
        $roleModel->method('findById')->willReturn(null);
        $permissionModel = $this->createPermissionModelStub();
        $service = new RoleService($roleModel, $permissionModel);
        $result = $service->updateRole(999, ['name' => 'Test', 'slug' => 'test']);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }
}
