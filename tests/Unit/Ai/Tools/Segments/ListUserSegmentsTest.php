<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Segments;

use App\Ai\Tools\Segments\ListUserSegments;
use App\Enums\AdminStatus;
use App\Enums\SegmentType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\UserSegment\Segment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户分群列表查询工具测试
 */
#[CoversClass(ListUserSegments::class)]
#[Group('ai')]
#[Group('segment')]
class ListUserSegmentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 创建一个仅拥有指定权限的管理员
     *
     * @param  array<int, string>  $permissions
     */
    private function createAdminWithPermissions(array $permissions): Admin
    {
        $suffix = uniqid();

        $admin = Admin::query()->create([
            'username' => 'limited_'.$suffix,
            'name' => '受限管理员',
            'email' => "limited_{$suffix}@example.com",
            'password' => 'password',
            'status' => AdminStatus::ACTIVE,
        ]);

        $role = Role::create([
            'name' => 'limited_'.$suffix,
            'display_name' => '受限角色',
            'guard_name' => AdminMenu::GUARD_NAME,
        ]);

        $role->syncPermissions($permissions);
        $admin->assignRole($role);

        return $admin->refresh();
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListUserSegments)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 segments.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListUserSegments)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页分群数据')]
    public function handle_returns_paginated_segments(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.index']);
        Segment::factory()->create();
        Segment::factory()->rule()->create();

        $result = (new ListUserSegments)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('type_label', $payload['data'][0]);
        $this->assertArrayHasKey('member_count', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('type 筛选仅返回规则分群')]
    public function handle_filters_by_type(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.index']);
        Segment::factory()->create();
        Segment::factory()->rule()->create();

        $result = (new ListUserSegments)->forAdmin($admin)->handle(new Request(['type' => 2]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame(SegmentType::RULE->value, $payload['data'][0]['type']);
    }

    #[Test]
    #[TestDox('keyword 筛选匹配分群名称')]
    public function handle_filters_by_keyword(): void
    {
        $admin = $this->createAdminWithPermissions(['segments.index']);
        Segment::factory()->create(['name' => '高价值用户']);
        Segment::factory()->create(['name' => '沉默用户']);

        $result = (new ListUserSegments)->forAdmin($admin)->handle(new Request(['keyword' => '高价值']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('高价值用户', $payload['data'][0]['name']);
    }
}
