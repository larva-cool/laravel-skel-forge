<?php

/**
 * This is NOT a freeware, use is subject to license terms.
 */
declare(strict_types=1);

namespace Tests\Unit\Ai\Tools\Wallet;

use App\Ai\Tools\Wallet\GetRechargeFunnel;
use App\Enums\AdminStatus;
use App\Enums\PayChannel;
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
 * 充值转化漏斗工具测试
 *
 * 验证 GetRechargeFunnel 的权限兜底、整体/分渠道转化率与平均支付耗时。
 */
#[CoversClass(GetRechargeFunnel::class)]
#[Group('ai')]
#[Group('wallet')]
class GetRechargeFunnelTest extends TestCase
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
        $result = (new GetRechargeFunnel)->handle(new Request([]));

        $this->assertStringContainsString('无法识别当前操作者身份', (string) $result);
    }

    #[Test]
    #[TestDox('管理员缺少充值订单权限时工具执行被拒绝')]
    public function handle_denies_admin_without_permission(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.index']);

        $result = (new GetRechargeFunnel)->forAdmin($admin)->handle(new Request([]));

        $this->assertStringContainsString('没有执行此操作所需的权限', (string) $result);
    }

    #[Test]
    #[TestDox('漏斗正确计算整体与分渠道转化率')]
    public function handle_computes_funnel_rates(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);
        $user = User::factory()->create();

        // manual 渠道：2 成功（耗时 10/30 分钟）、1 过期、1 待支付
        Recharge::factory()->forUser($user)->success()->create([
            'amount' => 100,
            'created_at' => now()->subMinutes(10),
        ]);
        Recharge::factory()->forUser($user)->success()->withBonus(20)->create([
            'amount' => 200,
            'created_at' => now()->subMinutes(30),
        ]);
        Recharge::factory()->forUser($user)->expired()->create();
        Recharge::factory()->forUser($user)->create();

        // wechat 渠道：1 成功
        Recharge::factory()->forUser($user)->success()->create([
            'amount' => 50,
            'pay_channel' => PayChannel::WECHAT,
        ]);

        $result = (new GetRechargeFunnel)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertIsArray($payload);

        // 整体：下单 5、成功 3、转化率 60%、入账 370
        $this->assertSame(5, $payload['overall']['created_count']);
        $this->assertSame(3, $payload['overall']['success_count']);
        $this->assertSame(60.0, $payload['overall']['pay_rate']);
        $this->assertSame(370.0, $payload['overall']['success_amount']);
        $this->assertSame(1, $payload['overall']['by_status']['expired']['count']);
        $this->assertSame(1, $payload['overall']['by_status']['pending']['count']);

        // 平均支付耗时：(10 + 30 + 0) / 3 ≈ 13.3 分钟
        $this->assertEqualsWithDelta(13.3, $payload['overall']['avg_pay_minutes'], 0.1);

        // 分渠道
        $this->assertSame(4, $payload['by_channel']['manual']['created_count']);
        $this->assertSame(2, $payload['by_channel']['manual']['success_count']);
        $this->assertSame(50.0, $payload['by_channel']['manual']['pay_rate']);
        $this->assertSame(100.0, $payload['by_channel']['wechat']['pay_rate']);
    }

    #[Test]
    #[TestDox('无订单时转化率与耗时返回 null')]
    public function handle_returns_null_rates_when_empty(): void
    {
        $admin = $this->createAdminWithPermissions(['wallet.orders']);

        $result = (new GetRechargeFunnel)->forAdmin($admin)->handle(new Request(['days' => 7]));

        $payload = json_decode((string) $result, true);
        $this->assertSame(0, $payload['overall']['created_count']);
        $this->assertNull($payload['overall']['pay_rate']);
        $this->assertNull($payload['overall']['avg_pay_minutes']);
        $this->assertSame([], $payload['by_channel']);
    }
}
