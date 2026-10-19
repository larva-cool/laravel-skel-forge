<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\ListWalletTrades;
use App\Enums\AdminStatus;
use App\Enums\WalletTradeStatus;
use App\Enums\WalletTradeType;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminMenu;
use App\Models\User;
use App\Models\Wallet\WalletTrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 钱包流水查询工具测试
 */
#[CoversClass(ListWalletTrades::class)]
#[Group('ai')]
#[Group('wallet')]
class ListWalletTradesTest extends TestCase
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
     * 创建一条钱包流水
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createTrade(User $user, array $overrides = []): WalletTrade
    {
        return WalletTrade::query()->create(array_merge([
            'owner_type' => 'user',
            'owner_id' => $user->id,
            'amount' => 100.00,
            'before_balance' => 0.00,
            'after_balance' => 100.00,
            'before_frozen' => 0.00,
            'after_frozen' => 0.00,
            'type' => WalletTradeType::RECHARGE,
            'status' => WalletTradeStatus::SUCCESS,
            'description' => '充值到账',
        ], $overrides));
    }

    #[Test]
    #[TestDox('未注入管理员时工具执行被拒绝')]
    public function handle_denies_without_admin(): void
    {
        $result = (new ListWalletTrades)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少 wallet.index 权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['users.index']);

        $result = (new ListWalletTrades)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('管理员拥有权限时返回分页流水数据')]
    public function handle_returns_paginated_trades(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);
        $user = User::factory()->create();
        $this->createTrade($user);
        $this->createTrade($user, ['type' => WalletTradeType::PAYMENT, 'description' => '消费支出']);

        $result = (new ListWalletTrades)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['pagination']['total']);
        $this->assertArrayHasKey('before_balance', $payload['data'][0]);
        $this->assertArrayHasKey('type_label', $payload['data'][0]);
        $this->assertArrayHasKey('user', $payload['data'][0]);
    }

    #[Test]
    #[TestDox('type 与 user_id 组合筛选仅返回匹配流水')]
    public function handle_filters_by_type_and_user(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->createTrade($userA, ['type' => WalletTradeType::RECHARGE]);
        $this->createTrade($userA, ['type' => WalletTradeType::PAYMENT, 'description' => '消费支出']);
        $this->createTrade($userB, ['type' => WalletTradeType::RECHARGE]);

        $result = (new ListWalletTrades)->forAdmin($admin)->handle(new Request([
            'user_id' => $userA->id,
            'type' => 'recharge',
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['pagination']['total']);
        $this->assertSame($userA->id, $payload['data'][0]['user']['id']);
        $this->assertSame('recharge', $payload['data'][0]['type']);
    }
}
