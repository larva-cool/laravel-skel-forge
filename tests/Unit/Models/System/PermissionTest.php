<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Models\System;

use App\Models\System\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Permission 模型单元测试
 */
#[CoversClass(Permission::class)]
#[Group('models')]
#[Group('permission')]
class PermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[TestDox('继承自 Spatie Permission')]
    public function extends_spatie_permission(): void
    {
        $this->assertTrue(is_subclass_of(Permission::class, \Spatie\Permission\Models\Permission::class));
    }

    #[Test]
    #[TestDox('filterByGuard 返回指定 guard 的权限 ID 数组')]
    public function filter_by_guard_returns_ids(): void
    {
        $p1 = Permission::create(['name' => 'test.create', 'guard_name' => 'admin']);
        $p2 = Permission::create(['name' => 'test.edit', 'guard_name' => 'admin']);
        $p3 = Permission::create(['name' => 'test.delete', 'guard_name' => 'web']);

        $result = Permission::filterByGuard([$p1->id, $p2->id, $p3->id], 'admin');

        $this->assertCount(2, $result);
        $this->assertContains($p1->id, $result);
        $this->assertContains($p2->id, $result);
        $this->assertNotContains($p3->id, $result);
    }

    #[Test]
    #[TestDox('filterByGuard 空数组返回空数组')]
    public function filter_by_guard_empty_returns_empty(): void
    {
        $this->assertSame([], Permission::filterByGuard([], 'admin'));
    }

    #[Test]
    #[TestDox('filterByGuard 不存在的 ID 被过滤')]
    public function filter_by_guard_filters_nonexistent(): void
    {
        $p1 = Permission::create(['name' => 'test.view', 'guard_name' => 'admin']);

        $result = Permission::filterByGuard([$p1->id, 99999], 'admin');

        $this->assertCount(1, $result);
        $this->assertContains($p1->id, $result);
    }

    #[Test]
    #[TestDox('collectByGuard 返回 Collection')]
    public function collect_by_guard_returns_collection(): void
    {
        $p1 = Permission::create(['name' => 'test.create', 'guard_name' => 'admin']);
        $p2 = Permission::create(['name' => 'test.delete', 'guard_name' => 'web']);

        $result = Permission::collectByGuard([$p1->id, $p2->id], 'admin');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
        $this->assertSame('test.create', $result->first()->name);
    }

    #[Test]
    #[TestDox('collectByGuard 空数组返回空 Collection')]
    public function collect_by_guard_empty_returns_empty_collection(): void
    {
        $result = Permission::collectByGuard([], 'admin');

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }
}
