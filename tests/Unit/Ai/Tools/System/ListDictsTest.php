<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\System;

use App\Ai\Tools\System\ListDicts;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\System\Dict;
use App\Models\System\DictItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 字典类型与字典数据查询工具测试
 */
#[CoversClass(ListDicts::class)]
#[Group('ai')]
#[Group('dict')]
class ListDictsTest extends TestCase
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

    /**
     * 创建一个字典类型及字典项
     */
    private function createDict(string $code, string $name): Dict
    {
        $dict = Dict::query()->create([
            'name' => $name,
            'code' => $code,
            'status' => 1,
        ]);

        DictItem::query()->create([
            'dict_id' => $dict->id,
            'label' => '选项一',
            'value' => 'option_1',
        ]);
        DictItem::query()->create([
            'dict_id' => $dict->id,
            'label' => '选项二',
            'value' => 'option_2',
        ]);

        return $dict;
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListDicts)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 dicts.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListDicts)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('按编码查询时返回字典类型及其字典项')]
    public function handle_returns_dict_with_items(): void
    {
        $admin = $this->createAdminWithPermissions(['dicts.index']);
        $this->createDict('unit_test_dict', '单元测试字典');

        $result = (new ListDicts)->forAdmin($admin)->handle(new Request(['keyword' => 'unit_test_dict']));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('unit_test_dict', $payload['data'][0]['code']);
        $this->assertCount(2, $payload['data'][0]['items']);
        $this->assertSame('option_1', $payload['data'][0]['items'][0]['value']);
    }
}
