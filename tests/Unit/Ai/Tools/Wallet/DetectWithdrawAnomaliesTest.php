<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\DetectWithdrawAnomalies;
use App\Enums\AdminStatus;
use App\Enums\WithdrawAccountType;
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
 * 提现异常扫描工具测试
 *
 * 验证 DetectWithdrawAnomalies 的权限兜底与五类异常规则的命中口径。
 */
#[CoversClass(DetectWithdrawAnomalies::class)]
#[Group('ai')]
#[Group('wallet')]
class DetectWithdrawAnomaliesTest extends TestCase
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
        $result = (new DetectWithdrawAnomalies)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少提现权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);

        $result = (new DetectWithdrawAnomalies)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('大额提现超过阈值被命中')]
    public function detects_large_amount_withdraws(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);
        $user = User::factory()->create();

        Withdraw::factory()->forUser($user)->create(['amount' => 5000]);
        Withdraw::factory()->forUser($user)->create(['amount' => 100]);

        $result = (new DetectWithdrawAnomalies)->forAdmin($admin)->handle(new Request(['large_amount' => 1000]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['large_amount_withdraws']['total']);
        $this->assertSame(5000.0, $payload['large_amount_withdraws']['samples'][0]['amount']);
    }

    #[Test]
    #[TestDox('同一用户短时密集申请被命中，分散申请不命中')]
    public function detects_burst_applications(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);

        // 用户 A：1 小时内 3 笔申请 → 命中
        $userA = User::factory()->create();
        foreach ([0, 10, 20] as $minutes) {
            Withdraw::factory()->forUser($userA)->create(['created_at' => now()->subMinutes($minutes)]);
        }

        // 用户 B：3 笔申请分散在 3 天 → 不命中
        $userB = User::factory()->create();
        foreach ([0, 1, 2] as $days) {
            Withdraw::factory()->forUser($userB)->create(['created_at' => now()->subDays($days)]);
        }

        $result = (new DetectWithdrawAnomalies)->forAdmin($admin)->handle(new Request([
            'burst_count' => 3,
            'burst_hours' => 24,
        ]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['burst_applications']['total']);
        $this->assertSame($userA->id, $payload['burst_applications']['samples'][0]['user']['id']);
        $this->assertSame(3, $payload['burst_applications']['samples'][0]['application_count']);
    }

    #[Test]
    #[TestDox('新注册用户即提现被命中，老用户不命中')]
    public function detects_new_user_withdraws(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);

        $newUser = User::factory()->create(['created_at' => now()->subDays(2)]);
        Withdraw::factory()->forUser($newUser)->create(['created_at' => now()]);

        $oldUser = User::factory()->create(['created_at' => now()->subDays(20)]);
        Withdraw::factory()->forUser($oldUser)->create(['created_at' => now()]);

        $result = (new DetectWithdrawAnomalies)->forAdmin($admin)->handle(new Request(['new_user_days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['new_user_withdraws']['total']);
        $this->assertSame($newUser->id, $payload['new_user_withdraws']['samples'][0]['user']['id']);
        $this->assertArrayHasKey('user_registered_at', $payload['new_user_withdraws']['samples'][0]);
    }

    #[Test]
    #[TestDox('同一收款账户被多个账号使用被命中且账户脱敏')]
    public function detects_shared_accounts(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $account = ['account' => 'wx_merchant_001', 'real_name' => '张三'];

        Withdraw::factory()->forUser($userA)->create([
            'account_type' => WithdrawAccountType::WECHAT,
            'account_info' => $account,
        ]);
        Withdraw::factory()->forUser($userB)->create([
            'account_type' => WithdrawAccountType::WECHAT,
            'account_info' => $account,
        ]);

        // 单人独占账户不命中
        Withdraw::factory()->forUser($userA)->create([
            'account_type' => WithdrawAccountType::ALIPAY,
            'account_info' => ['account' => 'solo_account', 'real_name' => '李四'],
        ]);

        $result = (new DetectWithdrawAnomalies)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['shared_accounts']['total']);
        $group = $payload['shared_accounts']['samples'][0];
        $this->assertSame(2, $group['user_count']);
        $this->assertEqualsCanonicalizing([$userA->id, $userB->id], $group['user_ids']);
        $this->assertStringNotContainsString('wx_merchant_001', (string) $result);
    }

    #[Test]
    #[TestDox('同一下单 IP 被多个账号使用被命中')]
    public function detects_shared_ips(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.withdraws']);

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Withdraw::factory()->forUser($userA)->create(['client_ip' => '203.0.113.10']);
        Withdraw::factory()->forUser($userB)->create(['client_ip' => '203.0.113.10']);
        Withdraw::factory()->forUser($userA)->create(['client_ip' => '203.0.113.99']);

        $result = (new DetectWithdrawAnomalies)->forAdmin($admin)->handle(new Request([]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(1, $payload['shared_ips']['total']);
        $group = $payload['shared_ips']['samples'][0];
        $this->assertSame('203.0.113.10', $group['client_ip']);
        $this->assertSame(2, $group['user_count']);
        $this->assertEqualsCanonicalizing([$userA->id, $userB->id], $group['user_ids']);
    }
}
