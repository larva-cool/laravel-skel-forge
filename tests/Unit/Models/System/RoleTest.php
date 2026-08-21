<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\System\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Role 模型单元测试
 */
#[CoversClass(Role::class)]
#[Group('models')]
#[Group('role')]
class RoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('继承自 Spatie Role')]
    public function extends_spatie_role(): void
    {
        $this->assertTrue(is_subclass_of(Role::class, \Spatie\Permission\Models\Role::class));
    }

    #[Test]
    #[TestDox('创建角色')]
    public function create_role(): void
    {
        $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);

        $this->assertDatabaseHas('roles', [
            'name' => 'editor',
            'guard_name' => 'admin',
        ]);
        $this->assertSame('editor', $role->name);
    }

    #[Test]
    #[TestDox('display_name 属性可设置')]
    public function display_name_can_be_set(): void
    {
        $role = Role::create([
            'name' => 'manager',
            'guard_name' => 'admin',
            'display_name' => '经理',
        ]);

        $this->assertSame('经理', $role->display_name);
    }
}
