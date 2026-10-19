<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\ListRecharges;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\Wallet\Recharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 充值订单查询工具测试
 */
#[CoversClass(ListRecharges::class)]
#[Group('ai')]
#[Group('wallet')]
class ListRechargesTest extends TestCase
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
        $result = (new ListRecharges)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 wallet.orders 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListRecharges)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页充值订单')]
    public function handle_returns_paginated_recharges(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);
        Recharge::factory()->count(2)->create();

        $result = (new ListRecharges)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('order_no', $payload['data'][0]);
        $this->assertArrayHasKey('total_amount', $payload['data'][0]);
        $this->assertArrayHasKey('pay_status_label', $payload['data'][0]);
        $this->assertArrayHasKey('user', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('pay_status 筛选仅返回对应状态订单')]
    public function handle_filters_by_pay_status(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);
        Recharge::factory()->create();
        Recharge::factory()->success()->create();

        $result = (new ListRecharges)->forAdmin($admin)->handle(new Request(['pay_status' => 'success']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('success', $payload['data'][0]['pay_status']);
    }

    #[Test]
    #[TestDox('user_id 筛选仅返回该用户的订单')]
    public function handle_filters_by_user_id(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);
        $user = User::factory()->create();
        Recharge::factory()->forUser($user)->create();
        Recharge::factory()->create();

        $result = (new ListRecharges)->forAdmin($admin)->handle(new Request(['user_id' => $user->id]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($user->id, $payload['data'][0]['user']['id']);
    }
}
