<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\ListWithdraws;
use App\Enums\AdminStatus;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\Wallet\Withdraw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 提现申请查询工具测试
 */
#[CoversClass(ListWithdraws::class)]
#[Group('ai')]
#[Group('wallet')]
class ListWithdrawsTest extends TestCase
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
        $result = (new ListWithdraws)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 wallet.withdraws 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListWithdraws)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页提现申请，且不返回收款账户明细')]
    public function handle_returns_paginated_withdraws(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        Withdraw::factory()->count(2)->create();

        $result = (new ListWithdraws)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('order_no', $payload['data'][0]);
        $this->assertArrayHasKey('actual_amount', $payload['data'][0]);
        $this->assertArrayHasKey('status_label', $payload['data'][0]);
        $this->assertArrayNotHasKey('account_info', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('status 筛选仅返回对应状态申请')]
    public function handle_filters_by_status(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        Withdraw::factory()->create();
        Withdraw::factory()->rejected()->create();

        $result = (new ListWithdraws)->forAdmin($admin)->handle(new Request(['status' => 'rejected']));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame('rejected', $payload['data'][0]['status']);
    }

    #[Test]
    #[TestDox('user_id 筛选仅返回该用户的提现申请')]
    public function handle_filters_by_user_id(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        $user = User::factory()->create();
        Withdraw::factory()->forUser($user)->create();
        Withdraw::factory()->create();

        $result = (new ListWithdraws)->forAdmin($admin)->handle(new Request(['user_id' => $user->id]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($user->id, $payload['data'][0]['user']['id']);
    }
}
