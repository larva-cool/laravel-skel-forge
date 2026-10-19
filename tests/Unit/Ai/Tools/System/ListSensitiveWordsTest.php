<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\ListSensitiveWords;
use App\Enums\AdminStatus;
use App\Enums\SensitiveCategory;
use App\Enums\SensitiveLevel;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\SensitiveWord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 敏感词列表查询工具测试
 */
#[CoversClass(ListSensitiveWords::class)]
#[Group('ai')]
#[Group('sensitive-word')]
class ListSensitiveWordsTest extends TestCase
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
        $result = (new ListSensitiveWords)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 sensitive-words.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListSensitiveWords)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页敏感词数据')]
    public function handle_returns_paginated_words(): void
    {
        $admin = $this->createAdminWithPermissions(['sensitive-words.index']);
        SensitiveWord::factory()->create(['word' => '单元测试敏感词甲']);
        SensitiveWord::factory()->create(['word' => '单元测试敏感词乙']);

        $result = (new ListSensitiveWords)->forAdmin($admin)->handle(new Request(['keyword' => '单元测试敏感词']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('category_label', $payload['data'][0]);
        $this->assertArrayHasKey('level_label', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('category 与 level 组合筛选仅返回匹配敏感词')]
    public function handle_filters_by_category_and_level(): void
    {
        $admin = $this->createAdminWithPermissions(['sensitive-words.index']);
        SensitiveWord::factory()->create([
            'category' => SensitiveCategory::AD,
            'level' => SensitiveLevel::BLOCK,
        ]);
        SensitiveWord::factory()->create([
            'category' => SensitiveCategory::AD,
            'level' => SensitiveLevel::REPLACE,
        ]);

        $result = (new ListSensitiveWords)->forAdmin($admin)->handle(new Request([
            'category' => 'ad',
            'level' => 'block',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('ad', $payload['data'][0]['category']);
        $this->assertSame('block', $payload['data'][0]['level']);
    }
}
